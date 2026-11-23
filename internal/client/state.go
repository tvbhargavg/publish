package client

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strconv"
	"syscall"
	"time"
)

// State describes a running tunnel process; stored per-PID so `status` and
// `stop` can find it.
type State struct {
	PID       int       `json:"pid"`
	LocalPort int       `json:"local_port"`
	URL       string    `json:"url"`
	Status    string    `json:"status"`
	StartedAt time.Time `json:"started_at"`
}

func runDir(base string) string { return filepath.Join(base, "run") }

func WriteState(base string, st State) error {
	if err := os.MkdirAll(runDir(base), 0o700); err != nil {
		return err
	}
	b, _ := json.Marshal(st)
	p := filepath.Join(runDir(base), strconv.Itoa(st.PID)+".json")
	tmp := p + ".tmp"
	if err := os.WriteFile(tmp, b, 0o600); err != nil {
		return err
	}
	return os.Rename(tmp, p)
}

func RemoveState(base string, pid int) {
	os.Remove(filepath.Join(runDir(base), strconv.Itoa(pid)+".json"))
}

// ListStates returns live tunnel processes, pruning stale files.
func ListStates(base string) []State {
	files, _ := filepath.Glob(filepath.Join(runDir(base), "*.json"))
	var out []State
	for _, f := range files {
		b, err := os.ReadFile(f)
		var st State
		if err != nil || json.Unmarshal(b, &st) != nil || !alive(st.PID) {
			os.Remove(f)
			continue
		}
		out = append(out, st)
	}
	return out
}

func alive(pid int) bool {
	p, err := os.FindProcess(pid)
	if err != nil {
		return false
	}
	return p.Signal(syscall.Signal(0)) == nil
}

// Stop asks a tunnel process to shut down gracefully.
func Stop(pid int) error {
	p, err := os.FindProcess(pid)
	if err != nil {
		return err
	}
	return p.Signal(syscall.SIGTERM)
}
