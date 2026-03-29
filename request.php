<?php
declare(strict_types=1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Content-Type: application/json; charset=UTF-8");

$root = __DIR__;
$requestsPath = $root . "/content/requests.json";
$contactsPath = $root . "/content/contacts.json";
require_once $root . "/lib/content_store.php";
$contentDb = null;
$sectionByPath = [
    $requestsPath => "requests",
    $contactsPath => "contacts",
];
$runtimeSettingsSection = "runtimeSettings";
$runtimeSettingsCache = null;
$publicContentFiles = [
    "artist" => $root . "/content/artist.json",
    "workshop" => $root . "/content/workshop.json",
    "collections" => $root . "/content/collections.json",
    "works" => $root . "/content/works.json",
    "exhibitions" => $root . "/content/exhibitions.json",
    "contacts" => $root . "/content/contacts.json",
    "media" => $root . "/content/media.json",
    "news" => $root . "/content/news.json",
    "siteSections" => $root . "/content/site-sections.json",
    "browseSections" => $root . "/content/browse-sections.json",
    "collectionPage" => $root . "/content/collection-page.json",
    "workPage" => $root . "/content/work-page.json",
];

require_once $root . "/lib/notifier.php";

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function loadJsonFile(string $path)
{
    global $sectionByPath;

    if (isset($sectionByPath[$path])) {
        $contentDb = getContentDb();
        $section = $sectionByPath[$path];
        if (contentStoreHasSection($contentDb, $section)) {
            return contentStoreLoadSection($contentDb, $section);
        }

        if (!isFileFallbackEnabled()) {
            throw new RuntimeException("DB section not found and file fallback is disabled: {$section}");
        }
    }

    if (!is_file($path)) {
        throw new RuntimeException("File not found: {$path}");
    }

    $raw = (string) file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in {$path}: " . json_last_error_msg());
    }

    if (isset($sectionByPath[$path])) {
        contentStoreSaveSection($contentDb, $sectionByPath[$path], $data);
    }

    return $data;
}

function saveJsonFile(string $path, $data): void
{
    global $sectionByPath;

    if (isset($sectionByPath[$path])) {
        $contentDb = getContentDb();
        contentStoreSaveSection($contentDb, $sectionByPath[$path], $data);
        return;
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException("Failed to encode JSON for {$path}");
    }

    $json .= PHP_EOL;
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException("Failed to write file: {$path}");
    }
}

function normalizeString(mixed $value): string
{
    return trim((string) ($value ?? ""));
}

function loadJsonFileStrict(string $path)
{
    if (!is_file($path)) {
        throw new RuntimeException("File not found: {$path}");
    }

    $raw = (string) file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in {$path}: " . json_last_error_msg());
    }

    return $data;
}

function getContentDb(): PDO
{
    global $contentDb, $root;
    if ($contentDb instanceof PDO) {
        return $contentDb;
    }

    $contentDb = openContentStore($root);
    return $contentDb;
}

function loadRuntimeSettingsFromDb(): array
{
    global $runtimeSettingsSection;
    $db = getContentDb();
    if (!contentStoreHasSection($db, $runtimeSettingsSection)) {
        return ["fileFallbackEnabled" => false];
    }

    $raw = contentStoreLoadSection($db, $runtimeSettingsSection);
    if (!is_array($raw)) {
        return ["fileFallbackEnabled" => false];
    }

    return [
        "fileFallbackEnabled" => (bool) ($raw["fileFallbackEnabled"] ?? false),
    ];
}

function getRuntimeSettings(bool $reload = false): array
{
    global $runtimeSettingsCache;
    if ($runtimeSettingsCache !== null && !$reload) {
        return $runtimeSettingsCache;
    }

    $runtimeSettingsCache = loadRuntimeSettingsFromDb();
    return $runtimeSettingsCache;
}

function isFileFallbackEnabled(): bool
{
    return (bool) (getRuntimeSettings()["fileFallbackEnabled"] ?? false);
}

if ($_SERVER["REQUEST_METHOD"] === "GET" && (string) ($_GET["action"] ?? "") === "public-content") {
    try {
        $db = getContentDb();
        $payload = [];
        foreach ($publicContentFiles as $section => $path) {
            if (contentStoreHasSection($db, $section)) {
                $payload[$section] = contentStoreLoadSection($db, $section);
                continue;
            }

            if (!isFileFallbackEnabled()) {
                throw new RuntimeException("DB section not found and file fallback is disabled: {$section}");
            }

            $fallback = loadJsonFileStrict($path);
            contentStoreSaveSection($db, $section, $fallback);
            $payload[$section] = $fallback;
        }

        respond(["ok" => true, "data" => $payload]);
    } catch (Throwable $error) {
        respond(["ok" => false, "error" => $error->getMessage()], 500);
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(["ok" => false, "error" => "Method not allowed"], 405);
}

try {
    $body = json_decode((string) file_get_contents("php://input"), true);
    if (!is_array($body)) {
        throw new RuntimeException("Некорректный JSON payload");
    }

    $name = normalizeString($body["name"] ?? "");
    $contact = normalizeString($body["contact"] ?? "");
    $requestType = normalizeString($body["requestType"] ?? "individual");
    $workTitle = normalizeString($body["workTitle"] ?? "");
    $size = normalizeString($body["size"] ?? "");
    $city = normalizeString($body["city"] ?? "");
    $preferredChannel = normalizeString($body["preferredChannel"] ?? "");
    $message = normalizeString($body["message"] ?? "");
    $source = normalizeString($body["source"] ?? "site");

    if ($name === "" || $contact === "" || $message === "") {
        throw new RuntimeException("Заполните имя, контакт и текст обращения");
    }

    $request = [
        "id" => "request-" . gmdate("YmdHis") . "-" . bin2hex(random_bytes(3)),
        "submittedAt" => gmdate("c"),
        "status" => "new",
        "source" => $source,
        "requestType" => $requestType,
        "workTitle" => $workTitle,
        "name" => $name,
        "contact" => $contact,
        "size" => $size,
        "city" => $city,
        "preferredChannel" => $preferredChannel,
        "message" => $message,
        "adminNote" => "",
        "notifications" => [],
    ];

    $contentDb = getContentDb();
    $contentDb->beginTransaction();
    $requests = loadJsonFile($requestsPath);
    if (!is_array($requests)) {
        $requests = [];
    }
    array_unshift($requests, $request);
    saveJsonFile($requestsPath, $requests);
    $contentDb->commit();

    $contacts = loadJsonFile($contactsPath);
    $notificationConfig = loadNotificationConfig($root);
    $siteConfig = loadSiteConfig($root);
    $notifications = notifyAboutRequest($request, is_array($contacts) ? $contacts : [], $notificationConfig, $siteConfig);

    $contentDb->beginTransaction();
    $requests = loadJsonFile($requestsPath);
    if (!is_array($requests)) {
        $requests = [];
    }
    foreach ($requests as &$storedRequest) {
        if (($storedRequest["id"] ?? "") === $request["id"]) {
            $storedRequest["notifications"] = $notifications;
            break;
        }
    }
    unset($storedRequest);
    saveJsonFile($requestsPath, $requests);
    $contentDb->commit();

    respond([
        "ok" => true,
        "message" => "Заявка отправлена",
        "notifications" => $notifications,
        "emailSent" => (bool) (($notifications["email"]["sent"] ?? false)),
    ]);
} catch (Throwable $error) {
    if ($contentDb instanceof PDO && $contentDb->inTransaction()) {
        $contentDb->rollBack();
    }
    respond(["ok" => false, "error" => $error->getMessage()], 500);
}
