<?php
declare(strict_types=1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Content-Type: application/json; charset=UTF-8");

$root = __DIR__;
$requestsPath = $root . "/content/requests.json";
$contactsPath = $root . "/content/contacts.json";

require_once $root . "/lib/notifier.php";

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function loadJsonFile(string $path)
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

function saveJsonFile(string $path, $data): void
{
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

    $requests = loadJsonFile($requestsPath);
    if (!is_array($requests)) {
        $requests = [];
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

    array_unshift($requests, $request);
    saveJsonFile($requestsPath, $requests);

    $contacts = loadJsonFile($contactsPath);
    $notificationConfig = loadNotificationConfig($root);
    $siteConfig = loadSiteConfig($root);
    $notifications = notifyAboutRequest($request, is_array($contacts) ? $contacts : [], $notificationConfig, $siteConfig);

    $requests[0]["notifications"] = $notifications;
    saveJsonFile($requestsPath, $requests);

    respond([
        "ok" => true,
        "message" => "Заявка отправлена",
        "notifications" => $notifications,
        "emailSent" => (bool) (($notifications["email"]["sent"] ?? false)),
    ]);
} catch (Throwable $error) {
    respond(["ok" => false, "error" => $error->getMessage()], 500);
}
