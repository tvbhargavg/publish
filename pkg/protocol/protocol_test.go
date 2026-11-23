package protocol

import (
	"bytes"
	"encoding/binary"
	"net"
	"testing"
)

func TestFrameRoundTrip(t *testing.T) {
	var b bytes.Buffer
	if err := WriteFrame(&b, TypeHello, Hello{Version: 1, Token: "t"}); err != nil {
		t.Fatal(err)
	}
	env, err := ReadFrame(&b)
	if err != nil || env.Type != TypeHello {
		t.Fatal(err, env)
	}
	var h Hello
	if err := env.Decode(&h); err != nil || h.Token != "t" {
		t.Fatal(err, h)
	}
}

func TestOversizedFrameRejected(t *testing.T) {
	var b bytes.Buffer
	binary.Write(&b, binary.BigEndian, uint32(MaxFrameSize+1))
	if _, err := ReadFrame(&b); err != ErrFrameTooLarge {
		t.Fatalf("got %v", err)
	}
}

func TestZeroFrameRejected(t *testing.T) {
	b := bytes.NewReader([]byte{0, 0, 0, 0})
	if _, err := ReadFrame(b); err != ErrFrameTooLarge {
		t.Fatal(err)
	}
}

func TestMuxConcurrentStreams(t *testing.T) {
	a, b := net.Pipe()
	cs, err := NewClientSession(a, MuxConfig{})
	if err != nil {
		t.Fatal(err)
	}
	ss, err := NewServerSession(b, MuxConfig{})
	if err != nil {
		t.Fatal(err)
	}
	defer cs.Close()
	defer ss.Close()
	go func() {
		for {
			st, err := ss.AcceptStream()
			if err != nil {
				return
			}
			go func() { buf := make([]byte, 5); st.Read(buf); st.Write(buf); st.Close() }()
		}
	}()
	done := make(chan error, 20)
	for i := 0; i < 20; i++ {
		go func() {
			st, err := cs.OpenStream()
			if err != nil {
				done <- err
				return
			}
			st.Write([]byte("hello"))
			buf := make([]byte, 5)
			_, err = st.Read(buf)
			if err == nil && string(buf) != "hello" {
				err = net.ErrClosed
			}
			done <- err
		}()
	}
	for i := 0; i < 20; i++ {
		if err := <-done; err != nil {
			t.Fatal(err)
		}
	}
}
