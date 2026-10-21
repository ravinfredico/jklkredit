<?php
declare(strict_types=1);

/**
 * Where the database is, in this order:
 *   1. DB_DSN (+ DB_USER, DB_PASS), e.g. "mysql:host=127.0.0.1;dbname=jkl_kredit;charset=utf8mb4"
 *   2. DATABASE_URL or POSTGRES_URL, e.g. what Vercel's Neon integration sets
 *   3. SQLite in storage/ (local demo), or in /tmp on Vercel when no database is attached
 * Returns [dsn, user, password].
 */
function db_config(): array
{
    if ($dsn = getenv('DB_DSN')) {
        return [$dsn, getenv('DB_USER') ?: null, getenv('DB_PASS') ?: null];
    }
    $url = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL');
    if ($url) {
        $u = parse_url($url);
        $scheme = $u['scheme'] ?? '';
        parse_str($u['query'] ?? '', $query);
        $db = ltrim($u['path'] ?? '', '/');
        if (str_starts_with($scheme, 'postgres')) {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $u['host'], $u['port'] ?? 5432, $db, $query['sslmode'] ?? 'require');
        } elseif ($scheme === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $u['host'], $u['port'] ?? 3306, $db);
        } else {
            throw new RuntimeException("Unsupported database URL scheme: $scheme");
        }
        return [$dsn, isset($u['user']) ? urldecode($u['user']) : null, isset($u['pass']) ? urldecode($u['pass']) : null];
    }
    $dir = getenv('VERCEL') ? '/tmp' : ROOT . '/storage';
    return ['sqlite:' . $dir . '/database.sqlite', null, null];
}

function db(?PDO $replace = null): PDO
{
    static $pdo = null;
    if ($replace !== null) {
        $pdo = $replace;
    }
    if ($pdo === null) {
        [$dsn, $user, $pass] = db_config();
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if (db_driver() === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
    return $pdo;
}

/** "sqlite", "mysql" or "pgsql". */
function db_driver(): string
{
    return strtok(db_config()[0], ':');
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function param(string $name): string
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $value = q('SELECT value FROM parameters WHERE name = ?', [$name])->fetchColumn();
        if ($value === false) {
            throw new RuntimeException("Parameter $name is missing");
        }
        $cache[$name] = (string) $value;
    }
    return $cache[$name];
}

function in_transaction(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Sessions live in the database, so they work when requests land on
 * different serverless instances (Vercel) as well as on one server.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string
    {
        return (string) (q('SELECT data FROM sessions WHERE id = ?', [$id])->fetchColumn() ?: '');
    }

    public function write(string $id, string $data): bool
    {
        if (q('UPDATE sessions SET data = ?, updated_at = ? WHERE id = ?', [$data, time(), $id])->rowCount() === 0) {
            try {
                q('INSERT INTO sessions (id, data, updated_at) VALUES (?, ?, ?)', [$id, $data, time()]);
            } catch (PDOException) {
                // Another request inserted it first; nothing to do.
            }
        }
        return true;
    }

    public function destroy(string $id): bool
    {
        q('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return q('DELETE FROM sessions WHERE updated_at < ?', [time() - $max_lifetime])->rowCount();
    }
}
