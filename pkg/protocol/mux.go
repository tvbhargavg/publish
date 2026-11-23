package protocol

import (
	"io"
	"net"
	"time"

	"github.com/hashicorp/yamux"
)

// Stream is one bidirectional multiplexed stream.
type Stream interface {
	net.Conn
}

// Session is a multiplexed connection. Implementations must be safe for
// concurrent use. The transport is swappable (yamux today, QUIC later).
type Session interface {
	OpenStream() (Stream, error)
	AcceptStream() (Stream, error)
	Close() error
	IsClosed() bool
	CloseChan() <-chan struct{}
}

// MuxConfig tunes the multiplexer.
type MuxConfig struct {
	KeepAliveInterval time.Duration
	StreamOpenTimeout time.Duration
}

func yamuxConfig(c MuxConfig) *yamux.Config {
	cfg := yamux.DefaultConfig()
	cfg.LogOutput = io.Discard
	if c.KeepAliveInterval > 0 {
		cfg.KeepAliveInterval = c.KeepAliveInterval
	}
	if c.StreamOpenTimeout > 0 {
		cfg.StreamOpenTimeout = c.StreamOpenTimeout
	}
	return cfg
}

type yamuxSession struct{ s *yamux.Session }

func (y yamuxSession) OpenStream() (Stream, error)   { return y.s.OpenStream() }
func (y yamuxSession) AcceptStream() (Stream, error) { return y.s.AcceptStream() }
func (y yamuxSession) Close() error                  { return y.s.Close() }
func (y yamuxSession) IsClosed() bool                { return y.s.IsClosed() }
func (y yamuxSession) CloseChan() <-chan struct{}    { return y.s.CloseChan() }

// NewClientSession wraps conn as the client side of a multiplexed session.
func NewClientSession(conn net.Conn, c MuxConfig) (Session, error) {
	s, err := yamux.Client(conn, yamuxConfig(c))
	if err != nil {
		return nil, err
	}
	return yamuxSession{s}, nil
}

// NewServerSession wraps conn as the server side.
func NewServerSession(conn net.Conn, c MuxConfig) (Session, error) {
	s, err := yamux.Server(conn, yamuxConfig(c))
	if err != nil {
		return nil, err
	}
	return yamuxSession{s}, nil
}
