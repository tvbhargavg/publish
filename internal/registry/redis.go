package registry

import (
	"context"
	"strconv"
	"time"

	"github.com/redis/go-redis/v9"
)

const keyPrefix = "tunnel:"

// Redis is the production Registry.
type Redis struct{ c *redis.Client }

func NewRedis(addr, password string) *Redis {
	return &Redis{c: redis.NewClient(&redis.Options{Addr: addr, Password: password, DialTimeout: 3 * time.Second, ReadTimeout: 2 * time.Second, WriteTimeout: 2 * time.Second})}
}

func (r *Redis) Close() error { return r.c.Close() }

var claimScript = redis.NewScript(`
local cur = redis.call('HGET', KEYS[1], 'user_id')
if cur and cur ~= ARGV[1] then return 0 end
redis.call('DEL', KEYS[1])
redis.call('HSET', KEYS[1], 'user_id', ARGV[1], 'tunnel_id', ARGV[2], 'conn_id', ARGV[3],
  'local_port', ARGV[4], 'status', ARGV[5], 'created_at', ARGV[6], 'last_heartbeat', ARGV[6], 'subdomain', ARGV[8])
redis.call('PEXPIRE', KEYS[1], ARGV[7])
return 1`)

var touchScript = redis.NewScript(`
if redis.call('HGET', KEYS[1], 'tunnel_id') == ARGV[1] then
  redis.call('HSET', KEYS[1], 'last_heartbeat', ARGV[2])
  redis.call('PEXPIRE', KEYS[1], ARGV[3])
  return 1
end
return 0`)

var releaseScript = redis.NewScript(`
if redis.call('HGET', KEYS[1], 'tunnel_id') == ARGV[1] then return redis.call('DEL', KEYS[1]) end
return 0`)

func (r *Redis) Claim(ctx context.Context, e Entry, ttl time.Duration) error {
	n, err := claimScript.Run(ctx, r.c, []string{keyPrefix + e.Subdomain},
		e.UserID, e.TunnelID, e.ConnID, e.LocalPort, e.Status, time.Now().Unix(), ttl.Milliseconds(), e.Subdomain).Int()
	if err != nil {
		return err
	}
	if n == 0 {
		return ErrTaken
	}
	return nil
}

func (r *Redis) Touch(ctx context.Context, sub, tid string, ttl time.Duration) (bool, error) {
	n, err := touchScript.Run(ctx, r.c, []string{keyPrefix + sub}, tid, time.Now().Unix(), ttl.Milliseconds()).Int()
	return n == 1, err
}

func (r *Redis) Get(ctx context.Context, sub string) (*Entry, error) {
	m, err := r.c.HGetAll(ctx, keyPrefix+sub).Result()
	if err != nil {
		return nil, err
	}
	if len(m) == 0 {
		return nil, nil
	}
	port, _ := strconv.Atoi(m["local_port"])
	cr, _ := strconv.ParseInt(m["created_at"], 10, 64)
	hb, _ := strconv.ParseInt(m["last_heartbeat"], 10, 64)
	return &Entry{Subdomain: sub, TunnelID: m["tunnel_id"], ConnID: m["conn_id"], UserID: m["user_id"],
		LocalPort: port, Status: m["status"], CreatedAt: time.Unix(cr, 0), LastHeartbeat: time.Unix(hb, 0)}, nil
}

func (r *Redis) Release(ctx context.Context, sub, tid string) error {
	return releaseScript.Run(ctx, r.c, []string{keyPrefix + sub}, tid).Err()
}

func (r *Redis) Ping(ctx context.Context) error { return r.c.Ping(ctx).Err() }
