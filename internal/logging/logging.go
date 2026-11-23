// Package logging provides structured slog setup with secret redaction.
package logging

import (
	"io"
	"log/slog"
	"strings"
)

var sensitive = []string{"token", "secret", "password", "authorization", "api_key", "apikey"}

// New returns a JSON logger that redacts attributes whose key looks sensitive.
func New(w io.Writer, level slog.Level) *slog.Logger {
	return slog.New(slog.NewJSONHandler(w, &slog.HandlerOptions{
		Level: level,
		ReplaceAttr: func(_ []string, a slog.Attr) slog.Attr {
			k := strings.ToLower(a.Key)
			for _, s := range sensitive {
				if strings.Contains(k, s) {
					return slog.String(a.Key, "[REDACTED]")
				}
			}
			return a
		},
	}))
}
