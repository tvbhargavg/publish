package config

import (
	"os"
	"path/filepath"
	"testing"
)

func TestDefaultsValid(t *testing.T) {
	t.Setenv("APP_ENV", "development")
	if _, err := Load(); err != nil {
		t.Fatal(err)
	}
}

func TestProductionRejectsInsecure(t *testing.T) {
	t.Setenv("APP_ENV", "production")
	t.Setenv("TLS_MODE", "none")
	t.Setenv("INTERNAL_API_SECRET", "change-me")
	t.Setenv("DEV_STATIC_TOKEN", "x")
	_, err := Load()
	if err == nil {
		t.Fatal("expected errors")
	}
}

func TestBadInt(t *testing.T) {
	t.Setenv("TUNNEL_CONTROL_PORT", "abc")
	if _, err := Load(); err == nil {
		t.Fatal("expected error")
	}
}

func TestDotEnvDoesNotOverride(t *testing.T) {
	p := filepath.Join(t.TempDir(), ".env")
	os.WriteFile(p, []byte("FOO_TEST_A=1 # c\nFOO_TEST_B=\"two\"\n"), 0o600)
	t.Setenv("FOO_TEST_A", "keep")
	os.Unsetenv("FOO_TEST_B")
	if err := LoadDotEnv(p); err != nil {
		t.Fatal(err)
	}
	if os.Getenv("FOO_TEST_A") != "keep" || os.Getenv("FOO_TEST_B") != "two" {
		t.Fatal("dotenv semantics wrong")
	}
	os.Unsetenv("FOO_TEST_B")
}
