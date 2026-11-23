package test

import (
	"context"
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/pem"
	"math/big"
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/techvoot/mytunnel/internal/client"
	"github.com/techvoot/mytunnel/internal/config"
	"github.com/techvoot/mytunnel/internal/server"
	"github.com/techvoot/mytunnel/pkg/protocol"
)

func writeWildcardCert(t *testing.T, dir string) (certFile, keyFile string, pool *x509.CertPool) {
	key, _ := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	tpl := &x509.Certificate{
		SerialNumber: big.NewInt(1), Subject: pkix.Name{CommonName: "*.example.test"},
		DNSNames: []string{"*.example.test", "example.test", "localhost"}, IPAddresses: []net.IP{net.ParseIP("127.0.0.1")},
		NotBefore: time.Now().Add(-time.Hour), NotAfter: time.Now().Add(time.Hour),
		KeyUsage: x509.KeyUsageDigitalSignature, ExtKeyUsage: []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth}, IsCA: true, BasicConstraintsValid: true,
	}
	der, _ := x509.CreateCertificate(rand.Reader, tpl, tpl, &key.PublicKey, key)
	kb, _ := x509.MarshalECPrivateKey(key)
	certFile, keyFile = filepath.Join(dir, "c.pem"), filepath.Join(dir, "k.pem")
	os.WriteFile(certFile, pem.EncodeToMemory(&pem.Block{Type: "CERTIFICATE", Bytes: der}), 0o600)
	os.WriteFile(keyFile, pem.EncodeToMemory(&pem.Block{Type: "EC PRIVATE KEY", Bytes: kb}), 0o600)
	c, _ := x509.ParseCertificate(der)
	pool = x509.NewCertPool()
	pool.AddCert(c)
	return
}

func TestTLSControlConnection(t *testing.T) {
	dir := t.TempDir()
	cf, kf, pool := writeWildcardCert(t, dir)
	t.Setenv("BASE_DOMAIN", "example.test")
	t.Setenv("TLS_MODE", "file")
	t.Setenv("TLS_CERT_FILE", cf)
	t.Setenv("TLS_KEY_FILE", kf)
	cfg, err := config.Load()
	if err != nil {
		t.Fatal(err)
	}
	tc, err := server.BuildTLS(context.Background(), cfg)
	if err != nil || tc == nil {
		t.Fatal(err)
	}
	// Reuse setup's server wiring but with a TLS listener.
	t.Setenv("TLS_MODE", "none")
	e := setup(t)
	ln, _ := tls.Listen("tcp", "127.0.0.1:0", tc)
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	go e.srv.ServeControl(ctx, ln)

	up := make(chan struct{}, 1)
	go client.Run(ctx, client.Options{
		ServerAddr: ln.Addr().String(), Token: "secret", LocalPort: localApp(t),
		TLS:       &tls.Config{RootCAs: pool, ServerName: "localhost", MinVersion: tls.VersionTLS12},
		OnConnect: func(protocol.Registered) { up <- struct{}{} },
	})
	select {
	case <-up:
	case <-time.After(5 * time.Second):
		t.Fatal("TLS tunnel did not connect")
	}
	// A client that does not trust the cert must fail.
	err = client.Run(ctx, client.Options{ServerAddr: ln.Addr().String(), Token: "secret", LocalPort: 1,
		TLS: &tls.Config{ServerName: "localhost", MinVersion: tls.VersionTLS12}})
	if err == nil {
		t.Fatal("untrusted cert accepted")
	}
}
