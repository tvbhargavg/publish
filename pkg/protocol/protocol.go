// Package protocol defines the wire protocol between the publish client and
// tunnel server. See docs/protocol.md.
//
// Layout: one TLS connection carries one multiplexed session (see Mux).
// The client opens the first stream (control stream) and exchanges
// length-prefixed JSON control messages. For each public HTTP request the
// server opens a new data stream to the client: a StreamHeader frame
// followed by the raw HTTP/1.1 request bytes; the client writes the raw
// HTTP response back on the same stream and closes it.
package protocol

import (
	"encoding/binary"
	"encoding/json"
	"errors"
	"fmt"
	"io"
)

// Version is the protocol version; bump on incompatible changes.
const Version = 1

// MaxFrameSize bounds a single control frame to protect against memory abuse.
const MaxFrameSize = 64 * 1024

// Message types.
const (
	TypeHello      = "hello"
	TypeHelloOK    = "hello_ok"
	TypeRegister   = "register"
	TypeRegistered = "registered"
	TypePing       = "ping"
	TypePong       = "pong"
	TypeError      = "error"
	TypeStream     = "stream" // data-stream header
)

// Error codes.
const (
	ErrAuth       = "auth_failed"
	ErrVersion    = "version_unsupported"
	ErrLimit      = "limit_exceeded"
	ErrSubdomain  = "subdomain_unavailable"
	ErrBadRequest = "bad_request"
	ErrInternal   = "internal"
)

// Envelope wraps every control message.
type Envelope struct {
	Type string          `json:"type"`
	Data json.RawMessage `json:"data,omitempty"`
}

type Hello struct {
	Version       int    `json:"version"`
	Token         string `json:"token"`
	ClientVersion string `json:"client_version,omitempty"`
}

type HelloOK struct {
	ServerTime int64 `json:"server_time"`
}

type Register struct {
	Proto              string `json:"proto"` // "http"
	LocalPort          int    `json:"local_port"`
	RequestedSubdomain string `json:"requested_subdomain,omitempty"`
}

type Registered struct {
	TunnelID  string `json:"tunnel_id"`
	Subdomain string `json:"subdomain"`
	URL       string `json:"url"`
}

type Ping struct {
	Seq uint64 `json:"seq"`
}

type Error struct {
	Code    string `json:"code"`
	Message string `json:"message"`
}

func (e *Error) Error() string { return e.Code + ": " + e.Message }

// StreamHeader precedes raw request bytes on a data stream.
type StreamHeader struct {
	RequestID  string `json:"request_id"`
	RemoteAddr string `json:"remote_addr,omitempty"`
}

// WriteFrame writes a 4-byte big-endian length followed by the JSON envelope.
func WriteFrame(w io.Writer, typ string, data any) error {
	env := Envelope{Type: typ}
	if data != nil {
		b, err := json.Marshal(data)
		if err != nil {
			return err
		}
		env.Data = b
	}
	b, err := json.Marshal(env)
	if err != nil {
		return err
	}
	if len(b) > MaxFrameSize {
		return fmt.Errorf("frame too large: %d", len(b))
	}
	buf := make([]byte, 4+len(b))
	binary.BigEndian.PutUint32(buf, uint32(len(b)))
	copy(buf[4:], b)
	_, err = w.Write(buf)
	return err
}

// ErrFrameTooLarge is returned when a peer announces an oversized frame.
var ErrFrameTooLarge = errors.New("protocol: frame too large")

// ReadFrame reads one envelope.
func ReadFrame(r io.Reader) (*Envelope, error) {
	var hdr [4]byte
	if _, err := io.ReadFull(r, hdr[:]); err != nil {
		return nil, err
	}
	n := binary.BigEndian.Uint32(hdr[:])
	if n == 0 || n > MaxFrameSize {
		return nil, ErrFrameTooLarge
	}
	b := make([]byte, n)
	if _, err := io.ReadFull(r, b); err != nil {
		return nil, err
	}
	var env Envelope
	if err := json.Unmarshal(b, &env); err != nil {
		return nil, fmt.Errorf("protocol: bad frame: %w", err)
	}
	return &env, nil
}

// Decode unmarshals the envelope payload into v.
func (e *Envelope) Decode(v any) error {
	if len(e.Data) == 0 {
		return errors.New("protocol: empty payload")
	}
	return json.Unmarshal(e.Data, v)
}
