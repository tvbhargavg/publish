package main

import (
	"bufio"
	"context"
	"crypto/tls"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/techvoot/mytunnel/internal/client"
	"github.com/techvoot/mytunnel/pkg/protocol"
)

var version = "0.1.0"

func confDir() string {
	d, err := os.UserConfigDir()
	if err != nil {
		d = os.TempDir()
	}
	return filepath.Join(d, "mytunnel")
}

func credPath() string { return filepath.Join(confDir(), "credentials") }

func loadToken() string {
	if t := os.Getenv("MYTUNNEL_TOKEN"); t != "" {
		return t
	}
	b, err := os.ReadFile(credPath())
	if err != nil {
		return ""
	}
	return strings.TrimSpace(string(b))
}

func main() {
	if len(os.Args) < 2 {
		usage()
		os.Exit(2)
	}
	switch os.Args[1] {
	case "version":
		fmt.Println("mytunnel", version)
	case "login":
		login()
	case "logout":
		if err := os.Remove(credPath()); err != nil && !os.IsNotExist(err) {
			fatal(err)
		}
		fmt.Println("Logged out")
	case "http":
		httpCmd(os.Args[2:])
	case "status":
		sts := client.ListStates(confDir())
		if len(sts) == 0 {
			fmt.Println("No active tunnels")
			return
		}
		for _, s := range sts {
			fmt.Printf("pid=%d port=%d status=%s url=%s since=%s\n", s.PID, s.LocalPort, s.Status, s.URL, s.StartedAt.Format(time.RFC3339))
		}
	case "stop":
		stopCmd(os.Args[2:])
	default:
		usage()
		os.Exit(2)
	}
}

func usage() {
	fmt.Fprintln(os.Stderr, "usage: mytunnel <login|http <port> [--subdomain name]|status|stop [port]|logout|version>")
}

func fatal(err error) {
	fmt.Fprintln(os.Stderr, "error:", err)
	os.Exit(1)
}

func stopCmd(args []string) {
	sts := client.ListStates(confDir())
	n := 0
	for _, s := range sts {
		if len(args) == 1 && strconv.Itoa(s.LocalPort) != args[0] {
			continue
		}
		if err := client.Stop(s.PID); err == nil {
			fmt.Printf("Stopped tunnel for port %d (pid %d)\n", s.LocalPort, s.PID)
			n++
		}
	}
	if n == 0 {
		fmt.Println("No matching tunnels")
	}
}

func login() {
	tok := os.Getenv("MYTUNNEL_TOKEN")
	if tok == "" {
		fmt.Fprint(os.Stderr, "Enter auth token: ")
		line, _ := bufio.NewReader(os.Stdin).ReadString('\n')
		tok = strings.TrimSpace(line)
	}
	if tok == "" {
		fatal(fmt.Errorf("empty token"))
	}
	if err := os.MkdirAll(confDir(), 0o700); err != nil {
		fatal(err)
	}
	if err := os.WriteFile(credPath(), []byte(tok+"\n"), 0o600); err != nil {
		fatal(err)
	}
	fmt.Println("Token saved")
}

func httpCmd(args []string) {
	fs := flag.NewFlagSet("http", flag.ExitOnError)
	server := fs.String("server", envOr("MYTUNNEL_SERVER", "localhost:4443"), "tunnel server host:port")
	noTLS := fs.Bool("no-tls", os.Getenv("MYTUNNEL_NO_TLS") == "1", "connect without TLS (local development only)")
	caFile := fs.String("ca-file", os.Getenv("MYTUNNEL_CA_FILE"), "extra CA certificate (PEM) to trust, for dev certs")
	sub := fs.String("subdomain", "", "request a specific subdomain")
	// allow `http 8000 --flag` as well as `http --flag 8000`
	var pos []string
	rest := args
	for len(rest) > 0 {
		_ = fs.Parse(rest)
		if fs.NArg() == 0 {
			break
		}
		pos = append(pos, fs.Arg(0))
		rest = fs.Args()[1:]
	}
	if len(pos) != 1 {
		fatal(fmt.Errorf("usage: mytunnel http <port>"))
	}
	port, err := strconv.Atoi(pos[0])
	if err != nil || port < 1 || port > 65535 {
		fatal(fmt.Errorf("invalid port %q", pos[0]))
	}
	tok := loadToken()
	if tok == "" {
		fatal(fmt.Errorf("not logged in: run `mytunnel login`"))
	}
	var tc *tls.Config
	if !*noTLS {
		tc = &tls.Config{MinVersion: tls.VersionTLS12}
		if *caFile != "" {
			tc.RootCAs = loadCA(*caFile)
		}
	} else {
		fmt.Fprintln(os.Stderr, "WARNING: TLS disabled; use only for local development")
	}
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	st := client.State{PID: os.Getpid(), LocalPort: port, Status: "connecting", StartedAt: time.Now()}
	save := func() { _ = client.WriteState(confDir(), st) }
	save()
	defer client.RemoveState(confDir(), st.PID)

	err = client.RunForever(ctx, client.Options{
		ServerAddr: *server, Token: tok, LocalPort: port, TLS: tc, Version: version, RequestedSubdomain: *sub,
		OnConnect: func(reg protocol.Registered) {
			st.URL, st.Status = reg.URL, "online"
			save()
			fmt.Println("Tunnel connected")
			fmt.Printf("Forwarding %s -> http://localhost:%d\n", reg.URL, port)
		},
		OnStatus: func(s string) {
			if strings.HasPrefix(s, "reconnecting") {
				st.Status = "reconnecting"
				save()
				fmt.Println(s)
			}
		},
		OnRequest: func(m, p string, status int, d time.Duration) {
			fmt.Printf("%s %-6s %s %d (%s)\n", time.Now().Format("15:04:05"), m, p, status, d.Round(time.Millisecond))
		},
	})
	if err != nil {
		fatal(err)
	}
	fmt.Println("Tunnel closed")
}

func envOr(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}
