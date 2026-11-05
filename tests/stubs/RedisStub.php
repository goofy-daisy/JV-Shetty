<?php
/**
 * RedisStub
 *
 * In-memory Redis substitute for unit testing.
 * Implements only the Redis methods used by OTPManager:
 *   zAdd, zCard, zRemRangeByScore, exists, setEx, expire
 */
class RedisStub implements RedisClientInterface {

    /** @var array<string, array<string, float>> Sorted sets: key → [member → score] */
    private array $sortedSets = [];

    /** @var array<string, array{value: string, expiresAt: int}> Scalar strings */
    private array $strings = [];

    // -------------------------------------------------------------------------
    // Sorted-set commands
    // -------------------------------------------------------------------------

    public function zAdd(string $key, float $score, string $member): int {
        $this->sortedSets[$key][$member] = $score;
        return 1;
    }

    public function zCard(string $key): int {
        return count($this->sortedSets[$key] ?? []);
    }

    /**
     * Remove sorted-set members with scores between $min and $max (inclusive).
     * Supports the '-inf' / '+inf' sentinels used by OTPManager.
     */
    public function zRemRangeByScore(string $key, string $min, string $max): int {
        if (!isset($this->sortedSets[$key])) {
            return 0;
        }

        $minVal = ($min === '-inf') ? PHP_FLOAT_MIN : (float) $min;
        $maxVal = ($max === '+inf') ? PHP_FLOAT_MAX : (float) $max;

        $removed = 0;
        foreach ($this->sortedSets[$key] as $member => $score) {
            if ($score >= $minVal && $score <= $maxVal) {
                unset($this->sortedSets[$key][$member]);
                $removed++;
            }
        }

        return $removed;
    }

    // -------------------------------------------------------------------------
    // String commands
    // -------------------------------------------------------------------------

    public function setEx(string $key, int $ttl, string $value): bool {
        $this->strings[$key] = [
            'value'     => $value,
            'expiresAt' => time() + $ttl,
        ];
        return true;
    }

    public function exists(string $key): int {
        if (!isset($this->strings[$key])) {
            return 0;
        }
        if ($this->strings[$key]['expiresAt'] <= time()) {
            unset($this->strings[$key]);
            return 0;
        }
        return 1;
    }

    public function expire(string $key, int $ttl): bool {
        if (isset($this->sortedSets[$key])) {
            // For sets we track expiry separately if needed; for tests this is fine
            return true;
        }
        if (isset($this->strings[$key])) {
            $this->strings[$key]['expiresAt'] = time() + $ttl;
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // evalScript (Lua emulation)
    // -------------------------------------------------------------------------

    /**
     * Emulate the atomic Lua rate-limiting script used by OTPManager.
     * Parses the Lua body to determine whether it is the sliding-window script
     * and executes equivalent PHP logic so tests never need a real Redis server.
     *
     * @param string   $script Lua script body (ignored beyond pattern detection)
     * @param string[] $keys   KEYS array (KEYS[1] = sorted-set key)
     * @param string[] $args   ARGV array: [now, window_seconds, limit, window_ttl]
     * @return int  1 if the request is allowed and the counter was incremented,
     *              0 if the rate limit has been reached.
     */
    public function evalScript(string $script, array $keys, array $args): mixed {
        $key         = $keys[0];
        $now         = (int) $args[0];
        $windowStart = $now - (int) $args[1];
        $limit       = (int) $args[2];
        $windowTtl   = (int) $args[3];

        // Prune stale entries
        $this->zRemRangeByScore($key, '-inf', (string) $windowStart);

        // Check count; return 0 if at or above limit
        if ($this->zCard($key) >= $limit) {
            return 0;
        }

        // Increment and set expiry
        $member = (string) $now . '_' . rand(0, 999999);
        $this->zAdd($key, (float) $now, $member);
        $this->expire($key, $windowTtl + 60);
        return 1;
    }
}
