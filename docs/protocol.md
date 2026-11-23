# Tunnel Protocol v1
Transport: TLS over TCP (TLS 1.2+), then a yamux session (client = yamux client). Plain TCP only when `TLS_MODE=none` (development; rejected in production).
Control stream: first stream opened by the client. Frames = 4-byte big-endian length + JSON `{type,data}`; max 64 KiB (oversized/zero frames are rejected).

| Step | Client → Server | Server → Client |
|---|---|---|
| 1 | `hello{version,token,client_version}` | `hello_ok{server_time}` or `error{auth_failed\|version_unsupported}` |
| 2 | `register{proto:"http",local_port,requested_subdomain?}` | `registered{tunnel_id,subdomain,url}` or `error{subdomain_unavailable\|limit_exceeded\|...}` |
| 3 | `ping{seq}` every 15s | `pong{seq}`; or `error{auth_failed}` if token revoked / tunnel removed from the registry |

Liveness: the client closes the session if no pong arrives within 3 heartbeat intervals; the server drops a tunnel after `TUNNEL_TTL` (default 45s) without a ping. yamux keepalive is also on.
Data streams: for each public request the server opens a new yamux stream: `stream{request_id}` frame, then raw HTTP/1.1 request bytes; the client dials only `127.0.0.1:<registered port>` and writes the raw HTTP response back, then closes. No new TCP/TLS connection per request.
Reconnect: the client retries with exponential backoff (1s→30s, jitter) and re-requests its last subdomain; the server lets the same user replace a stale session (the old session is closed, the registry entry's tunnel id changes so the old session can no longer touch/release it). Auth/version/bad-request errors are fatal (no retry).
Error codes: auth_failed, version_unsupported, limit_exceeded, subdomain_unavailable, bad_request, internal.

## Multiplexer evaluation
| Option | Verdict |
|---|---|
| **yamux** (chosen) | Per-stream flow control, keepalive, battle-tested (Consul/Nomad), MPL-2.0 (free, no usage limits), tiny. TCP head-of-line blocking accepted. |
| smux | Lighter, weaker flow control. Fallback candidate. |
| QUIC | Better on lossy links; UDP often blocked; heavier. Possible future transport (behind `protocol.Session`). |
| WebSocket + custom framing | Reinvents flow control; useful only for proxy traversal. |
