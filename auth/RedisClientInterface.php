<?php
/**
 * RedisClientInterface
 *
 * Thin interface over the Redis methods used by OTPManager.
 * Allows swapping in a stub during unit tests without depending on the
 * concrete php-redis extension class in the type signature.
 */
interface RedisClientInterface {
    public function zAdd(string $key, float $score, string $member): int;
    public function zCard(string $key): int;
    public function zRemRangeByScore(string $key, string $min, string $max): int;
    public function setEx(string $key, int $ttl, string $value): bool;
    public function exists(string $key): int;
    public function expire(string $key, int $ttl): bool;

    /**
     * Evaluate a Lua script atomically on the Redis server.
     *
     * @param string   $script Lua script body
     * @param string[] $keys   KEYS array passed to the script
     * @param string[] $args   ARGV array passed to the script
     * @return mixed           Value returned by the Lua script
     */
    public function evalScript(string $script, array $keys, array $args): mixed;
}
