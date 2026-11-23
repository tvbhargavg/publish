package logging

import (
	"bytes"
	"log/slog"
	"strings"
	"testing"
)

func TestRedaction(t *testing.T) {
	var b bytes.Buffer
	New(&b, slog.LevelInfo).Info("x", "auth_token", "supersecret", "user", "bob")
	out := b.String()
	if strings.Contains(out, "supersecret") || !strings.Contains(out, "bob") {
		t.Fatalf("bad redaction: %s", out)
	}
}
