<?php
/**
 * RedisFactory
 *
 * Creates and returns a connected Redis instance (or a test stub) using the
 * constants defined in config/redis.php.
 *
 * Usage:
 *   $redis = RedisFactory::connect();
 *   $otpManager = new OTPManager($redis);
 */

class RedisFactory {

    private static ?RedisClientInterface $instance = null;

    /**
     * Return a shared Redis connection (singleton).
     *
     * @throws RuntimeException if the connection cannot be established.
     */
    public static function connect(): RedisClientInterface {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $redis = new Redis();

        try {
            $connected = $redis->connect(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
            if (!$connected) {
                throw new RuntimeException('Redis::connect() returned false.');
            }

            if (REDIS_PASS !== '') {
                $redis->auth(REDIS_PASS);
            }

            if (REDIS_DB !== 0) {
                $redis->select(REDIS_DB);
            }
        } catch (Exception $e) {
            throw new RuntimeException('Failed to connect to Redis: ' . $e->getMessage());
        }

        self::$instance = new RedisAdapter($redis);
        return self::$instance;
    }

    /** Reset the singleton (useful for testing). */
    public static function reset(): void {
        self::$instance = null;
    }
}

/**
 * RedisAdapter
 *
 * Wraps the concrete Redis object so it satisfies RedisClientInterface
 * without requiring the Redis class itself to implement that interface.
 */
class RedisAdapter implements RedisClientInterface {

    private Redis $redis;

    public function __construct(Redis $redis) {
        $this->redis = $redis;
    }

    public function zAdd(string $key, float $score, string $member): int {
        return (int) $this->redis->zAdd($key, $score, $member);
    }

    public function zCard(string $key): int {
        return (int) $this->redis->zCard($key);
    }

    public function zRemRangeByScore(string $key, string $min, string $max): int {
        return (int) $this->redis->zRemRangeByScore($key, $min, $max);
    }

    public function setEx(string $key, int $ttl, string $value): bool {
        return (bool) $this->redis->setEx($key, $ttl, $value);
    }

    public function exists(string $key): int {
        return (int) $this->redis->exists($key);
    }

    public function expire(string $key, int $ttl): bool {
        return (bool) $this->redis->expire($key, $ttl);
    }

    public function evalScript(string $script, array $keys, array $args): mixed {
        return $this->redis->eval($script, array_merge($keys, $args), count($keys));
    }
}
