package server

import (
	"context"
	"crypto/tls"
	"fmt"

	"github.com/caddyserver/certmagic"
	"github.com/libdns/cloudflare"

	"github.com/techvoot/mytunnel/internal/config"
)

// BuildTLS returns the TLS config for the control and public listeners, or nil
// for TLS_MODE=none (development only). TLS_MODE=file loads a (wildcard)
// certificate from disk; TLS_MODE=acme obtains and renews a wildcard
// certificate via the ACME DNS-01 challenge (Cloudflare DNS).
func BuildTLS(ctx context.Context, cfg *config.Config) (*tls.Config, error) {
	switch cfg.TLSMode {
	case "none":
		return nil, nil
	case "file":
		cert, err := tls.LoadX509KeyPair(cfg.TLSCertFile, cfg.TLSKeyFile)
		if err != nil {
			return nil, fmt.Errorf("load TLS cert: %w", err)
		}
		return &tls.Config{Certificates: []tls.Certificate{cert}, MinVersion: tls.VersionTLS12}, nil
	case "acme":
		certmagic.DefaultACME.Agreed = true
		certmagic.DefaultACME.Email = cfg.ACMEEmail
		if cfg.ACMEStaging {
			certmagic.DefaultACME.CA = certmagic.LetsEncryptStagingCA
		}
		certmagic.DefaultACME.DNS01Solver = &certmagic.DNS01Solver{
			DNSManager: certmagic.DNSManager{DNSProvider: &cloudflare.Provider{APIToken: cfg.ACMEToken}},
		}
		certmagic.Default.Storage = &certmagic.FileStorage{Path: cfg.ACMEStorage}
		cm := certmagic.NewDefault()
		if err := cm.ManageSync(ctx, []string{"*." + cfg.BaseDomain, cfg.BaseDomain}); err != nil {
			return nil, fmt.Errorf("acme: %w", err)
		}
		tc := cm.TLSConfig()
		tc.MinVersion = tls.VersionTLS12
		tc.NextProtos = nil // HTTP/1.1 only: tunnel forwards HTTP/1.1
		return tc, nil
	}
	return nil, fmt.Errorf("unknown TLS_MODE")
}
