// Package config loads and validates configuration from environment variables.
// A .env file (local dev only) can be loaded with LoadDotEnv before Load.
package config

import (
	"bufio"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	Env               string
	BaseDomain        string
	ServerHost        string
	ControlPort       int
	PublicHTTPPort    int
	PublicHTTPSPort   int
	AdminListenAddr   string
	PublicIP          string
	TLSMode           string
	TLSCertFile       string
	TLSKeyFile        string
	InternalAPIURL    string
	InternalSecret    string
	DevStaticToken    string
	RedisAddr         string
	RedisPassword     string
	MaxBodyBytes      int64
	MaxStreams        int
	RequestTimeout    time.Duration
	RateLimitPerIP    float64
	HeartbeatEvery    time.Duration
	TunnelTTL         time.Duration
	RevalidateEvery   time.Duration
	MaxTunnelsPerUser int
	ACMEEmail         string
	ACMEProvider      string
	ACMEToken         string
	ACMEStaging       bool
	ACMEStorage       string
	DashboardHost     string
	DashboardUpstream string
}

// LoadDotEnv sets variables from a KEY=VALUE file without overriding the
// real environment. A missing file is not an error.
func LoadDotEnv(path string) error {
	f, err := os.Open(path)
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if err != nil {
		return err
	}
	defer f.Close()
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		k, v, ok := strings.Cut(line, "=")
		if !ok {
			continue
		}
		v = strings.TrimSpace(v)
		if i := strings.Index(v, " #"); i >= 0 {
			v = strings.TrimSpace(v[:i])
		}
		v = strings.Trim(v, `"'`)
		k = strings.TrimSpace(k)
		if _, set := os.LookupEnv(k); !set {
			os.Setenv(k, v)
		}
	}
	return sc.Err()
}

func str(k, d string) string {
	if v, ok := os.LookupEnv(k); ok && v != "" {
		return v
	}
	return d
}

type loader struct{ errs []error }

func (l *loader) int(k string, d int) int {
	v := str(k, "")
	if v == "" {
		return d
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		l.errs = append(l.errs, fmt.Errorf("%s: invalid integer", k))
		return d
	}
	return n
}

func (l *loader) dur(k string, d time.Duration) time.Duration {
	v := str(k, "")
	if v == "" {
		return d
	}
	x, err := time.ParseDuration(v)
	if err != nil {
		l.errs = append(l.errs, fmt.Errorf("%s: invalid duration", k))
		return d
	}
	return x
}

func (l *loader) float(k string, d float64) float64 {
	v := str(k, "")
	if v == "" {
		return d
	}
	x, err := strconv.ParseFloat(v, 64)
	if err != nil {
		l.errs = append(l.errs, fmt.Errorf("%s: invalid number", k))
		return d
	}
	return x
}

// Load reads configuration from the environment and validates it.
func Load() (*Config, error) {
	l := &loader{}
	c := &Config{
		Env:               str("APP_ENV", "development"),
		BaseDomain:        strings.ToLower(str("BASE_DOMAIN", "localhost")),
		ServerHost:        str("TUNNEL_SERVER_HOST", "localhost"),
		ControlPort:       l.int("TUNNEL_CONTROL_PORT", 4443),
		PublicHTTPPort:    l.int("PUBLIC_HTTP_PORT", 8080),
		PublicHTTPSPort:   l.int("PUBLIC_HTTPS_PORT", 8443),
		AdminListenAddr:   str("ADMIN_LISTEN_ADDR", "127.0.0.1:9090"),
		PublicIP:          str("PUBLIC_IP", ""),
		TLSMode:           str("TLS_MODE", "none"),
		TLSCertFile:       str("TLS_CERT_FILE", ""),
		TLSKeyFile:        str("TLS_KEY_FILE", ""),
		InternalAPIURL:    str("INTERNAL_API_URL", ""),
		InternalSecret:    str("INTERNAL_API_SECRET", ""),
		DevStaticToken:    str("DEV_STATIC_TOKEN", ""),
		RedisAddr:         str("REDIS_ADDR", ""),
		RedisPassword:     str("REDIS_PASSWORD", ""),
		MaxBodyBytes:      int64(l.int("MAX_BODY_BYTES", 10<<20)),
		MaxStreams:        l.int("MAX_STREAMS_PER_TUNNEL", 100),
		RequestTimeout:    l.dur("REQUEST_TIMEOUT", 30*time.Second),
		RateLimitPerIP:    l.float("RATE_LIMIT_PER_IP_RPS", 50),
		HeartbeatEvery:    l.dur("HEARTBEAT_INTERVAL", 15*time.Second),
		TunnelTTL:         l.dur("TUNNEL_TTL", 45*time.Second),
		RevalidateEvery:   l.dur("REVALIDATE_INTERVAL", 60*time.Second),
		MaxTunnelsPerUser: l.int("MAX_TUNNELS_PER_USER", 5),
		ACMEEmail:         str("ACME_EMAIL", ""),
		ACMEProvider:      str("ACME_DNS_PROVIDER", ""),
		ACMEToken:         str("ACME_DNS_API_TOKEN", ""),
		ACMEStaging:       str("ACME_STAGING", "false") == "true",
		ACMEStorage:       str("ACME_STORAGE", "./certs"),
		DashboardUpstream: str("DASHBOARD_UPSTREAM", ""),
	}
	c.DashboardHost = strings.ToLower(str("DASHBOARD_HOST", "app."+c.BaseDomain))
	errs := append(l.errs, c.Validate()...)
	return c, errors.Join(errs...)
}

// Validate checks values and rejects insecure production settings.
func (c *Config) Validate() []error {
	var e []error
	switch c.Env {
	case "development", "staging", "production":
	default:
		e = append(e, fmt.Errorf("APP_ENV must be development|staging|production"))
	}
	switch c.TLSMode {
	case "none", "file", "acme":
	default:
		e = append(e, fmt.Errorf("TLS_MODE must be none|file|acme"))
	}
	if c.TLSMode == "file" && (c.TLSCertFile == "" || c.TLSKeyFile == "") {
		e = append(e, fmt.Errorf("TLS_MODE=file requires TLS_CERT_FILE and TLS_KEY_FILE"))
	}
	if c.TLSMode == "acme" && (c.ACMEEmail == "" || c.ACMEProvider != "cloudflare" || c.ACMEToken == "") {
		e = append(e, fmt.Errorf("TLS_MODE=acme requires ACME_EMAIL, ACME_DNS_PROVIDER=cloudflare and ACME_DNS_API_TOKEN"))
	}
	if c.BaseDomain == "" || strings.ContainsAny(c.BaseDomain, "/: ") {
		e = append(e, fmt.Errorf("BASE_DOMAIN invalid"))
	}
	for name, p := range map[string]int{"TUNNEL_CONTROL_PORT": c.ControlPort, "PUBLIC_HTTP_PORT": c.PublicHTTPPort, "PUBLIC_HTTPS_PORT": c.PublicHTTPSPort} {
		if p < 1 || p > 65535 {
			e = append(e, fmt.Errorf("%s out of range", name))
		}
	}
	if c.Env == "production" {
		if c.TLSMode == "none" {
			e = append(e, fmt.Errorf("TLS_MODE=none is not allowed in production"))
		}
		if c.RedisAddr == "" || c.InternalAPIURL == "" {
			e = append(e, fmt.Errorf("REDIS_ADDR and INTERNAL_API_URL are required in production"))
		}
		if c.DevStaticToken != "" {
			e = append(e, fmt.Errorf("DEV_STATIC_TOKEN must not be set in production"))
		}
		if len(c.InternalSecret) < 32 || strings.HasPrefix(c.InternalSecret, "change-me") {
			e = append(e, fmt.Errorf("INTERNAL_API_SECRET must be a random value of 32+ chars in production"))
		}
	}
	return e
}
