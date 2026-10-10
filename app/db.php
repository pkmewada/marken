<?php
require_once __DIR__ . '/config.php';

const SCHEMA_VERSION = 4;
const SCHEMA_VERSION_FILE = __DIR__ . '/.schema-version';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $connect = fn() => new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    try {
        $pdo = $connect();
    } catch (PDOException $e) {
        if (!str_contains($e->getMessage(), '[1049]')) throw $e;   // 1049 = unknown database
        require_once __DIR__ . '/schema.php';
        installDatabase();
        $pdo = $connect();
    }

    // Upgrade an existing database once after code changes add tables/columns.
    if (@file_get_contents(SCHEMA_VERSION_FILE) !== (string)SCHEMA_VERSION) {
        require_once __DIR__ . '/schema.php';
        migrateSchema($pdo);
    }
    return $pdo;
}

function dbOne(string $sql, array $params = []): ?array {
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetch() ?: null;
}

function dbAll(string $sql, array $params = []): array {
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function dbRun(string $sql, array $params = []): int {
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->rowCount();
}

function toJson(array $val): ?string {
    return $val ? json_encode(array_values($val), JSON_UNESCAPED_UNICODE) : null;
}

function fromJson(?string $val): array {
    $decoded = $val ? json_decode($val, true) : null;
    return is_array($decoded) ? $decoded : [];
}
