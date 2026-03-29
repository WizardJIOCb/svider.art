<?php
declare(strict_types=1);

function loadDatabaseConfig(string $root): array
{
    $defaults = [
        "host" => "127.0.0.1",
        "port" => 5432,
        "dbname" => "svider",
        "user" => "svider",
        "password" => "svider",
        "schema" => "public",
        "dsn" => "",
    ];

    $configPath = $root . "/config/db.php";
    if (is_file($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            $defaults = array_merge($defaults, $loaded);
        }
    }

    $envMap = [
        "DB_HOST" => "host",
        "DB_PORT" => "port",
        "DB_NAME" => "dbname",
        "DB_USER" => "user",
        "DB_PASSWORD" => "password",
        "DB_SCHEMA" => "schema",
        "DB_DSN" => "dsn",
    ];

    foreach ($envMap as $envName => $targetKey) {
        $value = getenv($envName);
        if ($value === false || $value === "") {
            continue;
        }

        $defaults[$targetKey] = $targetKey === "port" ? (int) $value : $value;
    }

    return $defaults;
}

function openContentStore(string $root): PDO
{
    $cfg = loadDatabaseConfig($root);
    $dsn = trim((string) ($cfg["dsn"] ?? ""));
    if ($dsn === "") {
        $dsn = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s",
            (string) $cfg["host"],
            (int) $cfg["port"],
            (string) $cfg["dbname"]
        );
    }

    try {
        $pdo = new PDO(
            $dsn,
            (string) ($cfg["user"] ?? ""),
            (string) ($cfg["password"] ?? ""),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $error) {
        throw new RuntimeException("Database connection failed: " . $error->getMessage(), 0, $error);
    }

    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($cfg["schema"] ?? "public")) ?: "public";
    $pdo->exec("SET search_path TO {$schema}, public");
    ensureContentStoreTable($pdo);

    return $pdo;
}

function ensureContentStoreTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS content_store (
            section TEXT PRIMARY KEY,
            data JSONB NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
}

function contentStoreHasSection(PDO $pdo, string $section): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM content_store WHERE section = :section");
    $stmt->execute([":section" => $section]);
    return (bool) $stmt->fetchColumn();
}

function contentStoreLoadSection(PDO $pdo, string $section)
{
    $stmt = $pdo->prepare("SELECT data::text AS data_json FROM content_store WHERE section = :section");
    $stmt->execute([":section" => $section]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException("Missing content section: {$section}");
    }

    $decoded = json_decode((string) $row["data_json"], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in DB section {$section}: " . json_last_error_msg());
    }

    return $decoded;
}

function contentStoreSaveSection(PDO $pdo, string $section, $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException("Failed to encode JSON for section {$section}");
    }

    $stmt = $pdo->prepare(
        "INSERT INTO content_store (section, data, updated_at)
         VALUES (:section, CAST(:data AS jsonb), NOW())
         ON CONFLICT (section)
         DO UPDATE SET data = EXCLUDED.data, updated_at = NOW()"
    );
    $stmt->execute([
        ":section" => $section,
        ":data" => $json,
    ]);
}

