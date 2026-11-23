package main

import (
	"crypto/x509"
	"os"
)

func loadCA(path string) *x509.CertPool {
	pem, err := os.ReadFile(path)
	if err != nil {
		fatal(err)
	}
	pool, _ := x509.SystemCertPool()
	if pool == nil {
		pool = x509.NewCertPool()
	}
	if !pool.AppendCertsFromPEM(pem) {
		fatal(os.ErrInvalid)
	}
	return pool
}
