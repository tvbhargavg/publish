package main

import (
	"context"
	"crypto/tls"
	"fmt"
	"log/slog"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/techvoot/mytunnel/internal/authclient"
	"github.com/techvoot/mytunnel/internal/config"
	"github.com/techvoot/mytunnel/internal/logging"
	"github.com/techvoot/mytunnel/internal/registry"
	"github.com/techvoot/mytunnel/internal/server"
)

func main() {
	if err := run(); err != nil {
		fmt.Fprintln(os.Stderr, "fatal:", err)
		os.Exit(1)
	}
}

func run() error {
	if err := config.LoadDotEnv(".env"); err != nil {
		return err
	}
	cfg, err := config.Load()
	if err != nil {
		return err
	}
	log := logging.New(os.Stdout, slog.LevelInfo)
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	var backend authclient.Backend
	if cfg.InternalAPIURL != "" && cfg.DevStaticToken == "" {
		backend = authclient.NewHTTP(cfg.InternalAPIURL, cfg.InternalSecret)
	} else if cfg.DevStaticToken != "" && cfg.Env != "production" {
		log.Warn("using static development token authentication")
		backend = authclient.NewStatic(cfg.DevStaticToken)
	} else {
		return fmt.Errorf("set INTERNAL_API_URL (Laravel) or DEV_STATIC_TOKEN (development only)")
	}

	var reg registry.Registry = registry.NewMemory()
	if cfg.RedisAddr != "" {
		rr := registry.NewRedis(cfg.RedisAddr, cfg.RedisPassword)
		defer rr.Close()
		pctx, c := context.WithTimeout(ctx, 5*time.Second)
		err := rr.Ping(pctx)
		c()
		if err != nil {
			return fmt.Errorf("redis: %w", err)
		}
		reg = rr
	} else {
		log.Warn("REDIS_ADDR not set: using in-memory registry (single node, development only)")
	}

	tlsConf, err := server.BuildTLS(ctx, cfg)
	if err != nil {
		return err
	}
	srv := server.New(cfg, log, backend, reg)

	ctlLn, err := net.Listen("tcp", fmt.Sprintf(":%d", cfg.ControlPort))
	if err != nil {
		return err
	}
	if tlsConf != nil {
		ctlLn = tls.NewListener(ctlLn, tlsConf)
	}
	pubPort := cfg.PublicHTTPPort
	if tlsConf != nil {
		pubPort = cfg.PublicHTTPSPort
	}
	pub := &http.Server{
		Addr: fmt.Sprintf(":%d", pubPort), Handler: srv.PublicHandler(), TLSConfig: tlsConf,
		ReadHeaderTimeout: 10 * time.Second, IdleTimeout: 60 * time.Second, MaxHeaderBytes: 32 << 10,
	}
	admin := &http.Server{Addr: cfg.AdminListenAddr, Handler: srv.AdminHandler(), ReadHeaderTimeout: 5 * time.Second}

	errc := make(chan error, 3)
	go func() { errc <- srv.ServeControl(ctx, ctlLn) }()
	go func() {
		if tlsConf != nil {
			errc <- pub.ListenAndServeTLS("", "")
		} else {
			errc <- pub.ListenAndServe()
		}
	}()
	go func() { errc <- admin.ListenAndServe() }()
	log.Info("tunnel server started", "env", cfg.Env, "base_domain", cfg.BaseDomain, "control_port", cfg.ControlPort, "public_port", pubPort, "tls", cfg.TLSMode, "redis", cfg.RedisAddr != "")

	select {
	case <-ctx.Done():
	case err := <-errc:
		if err != nil && err != http.ErrServerClosed {
			return err
		}
	}
	sctx, c := context.WithTimeout(context.Background(), 10*time.Second)
	defer c()
	_ = pub.Shutdown(sctx)
	_ = admin.Shutdown(sctx)
	log.Info("shutdown complete")
	return nil
}
