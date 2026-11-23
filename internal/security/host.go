// Package security holds request/host validation helpers.
package security

import (
	"net"
	"regexp"
	"strings"
)

var labelRe = regexp.MustCompile(`^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])$`)

// ValidSubdomain reports whether s is an acceptable tunnel label (3-63 chars).
func ValidSubdomain(s string) bool { return labelRe.MatchString(s) }

// TunnelSubdomain extracts the tunnel label from a Host header value
// ("abc123.base.com[:port]"). It rejects IPs, the apex, nested labels and
// anything not directly under baseDomain.
func TunnelSubdomain(host, baseDomain string) (string, bool) {
	host = strings.ToLower(strings.TrimSpace(host))
	if h, _, err := net.SplitHostPort(host); err == nil {
		host = h
	}
	host = strings.TrimSuffix(host, ".")
	if net.ParseIP(host) != nil {
		return "", false
	}
	suffix := "." + baseDomain
	if !strings.HasSuffix(host, suffix) {
		return "", false
	}
	sub := strings.TrimSuffix(host, suffix)
	if !ValidSubdomain(sub) {
		return "", false
	}
	return sub, true
}

var reserved = map[string]bool{
	"www": true, "api": true, "admin": true, "app": true, "dashboard": true, "mail": true,
	"ftp": true, "ns1": true, "ns2": true, "smtp": true, "tunnel": true, "tunnels": true,
	"status": true, "auth": true, "login": true, "static": true, "assets": true, "internal": true,
	"localhost": true, "root": true, "support": true, "help": true, "docs": true,
}

// Reserved reports whether a label is reserved for platform use.
func Reserved(s string) bool { return reserved[s] }
