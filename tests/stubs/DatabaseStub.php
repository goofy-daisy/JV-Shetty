<?php
/**
 * DatabaseStub
 *
 * Provides an in-memory SQLite PDO instance that mirrors the tables used by
 * OTPManager so that unit tests can exercise database interactions without a
 * running MySQL server.
 *
 * Call DatabaseStub::setup() in setUp() / the bootstrap to register the
 * static singleton so that Database::getInstance() returns the SQLite PDO.
 */
class DatabaseStub {

    private static ?PDO $instance = null;

    /**
     * Initialise the SQLite in-memory database and seed minimal schema.
     * Registers the instance so Database::getInstance() delegates here.
     */
    public static function setup(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // SQLite does not have NOW() — register it as a UDF so that MySQL-style
        // queries in the production code work unchanged during tests.
        $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'), 0);

        // Minimal schema required by OTPManager
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            );

            CREATE TABLE IF NOT EXISTS users (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                email          TEXT    NOT NULL UNIQUE,
                password_hash  TEXT    NOT NULL DEFAULT '',
                role_id        INTEGER NOT NULL DEFAULT 1,
                is_active      INTEGER NOT NULL DEFAULT 1,
                is_verified    INTEGER NOT NULL DEFAULT 0,
                login_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until   TEXT    NULL,
                deleted_at     TEXT    NULL
            );

            CREATE TABLE IF NOT EXISTS otp_codes (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                email       TEXT    NOT NULL,
                code_hash   TEXT    NOT NULL,
                status      TEXT    NOT NULL DEFAULT 'pending',
                attempts    INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
                expires_at  TEXT    NOT NULL,
                verified_at TEXT    NULL
            );

            CREATE TABLE IF NOT EXISTS audit_logs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NULL,
                action      TEXT    NOT NULL,
                entity_type TEXT    NULL,
                entity_id   INTEGER NULL,
                ip_address  TEXT    NULL,
                user_agent  TEXT    NULL,
                created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
            );
        ");

        // Seed a default role
        $pdo->exec("INSERT OR IGNORE INTO roles (id, name) VALUES (1, 'student')");

        self::$instance = $pdo;
        return $pdo;
    }

    public static function getInstance(): PDO {
        return self::setup();
    }

    public static function reset(): void {
        self::$instance = null;
    }
}

// ---- Shim: override Database::getInstance() to use SQLite during tests -----
// We do this only when the real Database class has NOT been loaded yet.
if (!class_exists('Database', false)) {
    class Database {
        public static function getInstance(): PDO {
            return DatabaseStub::setup();
        }
    }
}
