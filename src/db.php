<?php
declare(strict_types=1);

/**
 * One PDO connection. Defaults to SQLite for the local demo;
 * set DB_DSN / DB_USER / DB_PASS to point at MySQL, e.g.
 * DB_DSN="mysql:host=127.0.0.1;dbname=jkl_kredit;charset=utf8mb4".
 */
function db(?PDO $replace = null): PDO
{
    static $pdo = null;
    if ($replace !== null) {
        $pdo = $replace;
    }
    if ($pdo === null) {
        $dsn = getenv('DB_DSN') ?: 'sqlite:' . ROOT . '/storage/database.sqlite';
        $pdo = new PDO($dsn, getenv('DB_USER') ?: null, getenv('DB_PASS') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if (str_starts_with($dsn, 'sqlite')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
    return $pdo;
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
