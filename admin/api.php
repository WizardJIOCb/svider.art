<?php
declare(strict_types=1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$root = dirname(__DIR__);
require_once $root . "/lib/content_store.php";

$contentFiles = [
    "artist" => $root . "/content/artist.json",
    "workshop" => $root . "/content/workshop.json",
    "collections" => $root . "/content/collections.json",
    "works" => $root . "/content/works.json",
    "contacts" => $root . "/content/contacts.json",
    "media" => $root . "/content/media.json",
    "news" => $root . "/content/news.json",
    "requests" => $root . "/content/requests.json",
    "siteSections" => $root . "/content/site-sections.json",
    "browseSections" => $root . "/content/browse-sections.json",
    "collectionPage" => $root . "/content/collection-page.json",
];
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
$sectionByPath = [];
foreach ($publicContentFiles as $section => $path) {
    $sectionByPath[$path] = $section;
}
$contentDb = null;
$runtimeSettingsSection = "runtimeSettings";
$runtimeSettingsCache = null;

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=UTF-8");
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

function slugify(string $value): string
{
    $value = trim($value);
    $value = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function getUploadTarget(string $root, string $entityType): string
{
    return match ($entityType) {
        "work" => $root . "/assets/media/works",
        "collection" => $root . "/assets/media/collections",
        "news" => $root . "/assets/media/news",
        "workshop" => $root . "/assets/media/workshop",
        default => throw new RuntimeException("Unsupported entity type"),
    };
}

function isLocalMediaPath(string $root, string $relativePath): bool
{
    $normalized = str_replace("\\", "/", $relativePath);
    return str_starts_with($normalized, "assets/media/") && is_file($root . "/" . $normalized);
}

function bumpCacheVersion(string $root): void
{
    $version = str_replace('.', '', sprintf('%.6f', microtime(true)));
    $payload = [
        "version" => $version,
        "updatedAt" => gmdate("c"),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return;
    }
    @file_put_contents($root . "/cache-version.json", $json . PHP_EOL, LOCK_EX);
}

function normalizeIdList($value): array
{
    if (!is_array($value)) {
        $value = is_string($value) && trim($value) !== "" ? [$value] : [];
    }

    $normalized = [];
    foreach ($value as $item) {
        $id = trim((string) $item);
        if ($id === "") {
            continue;
        }
        $normalized[] = $id;
    }

    return array_values(array_unique($normalized));
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

function saveRuntimeSettings(array $settings): void
{
    global $runtimeSettingsSection, $runtimeSettingsCache;
    $normalized = [
        "fileFallbackEnabled" => (bool) ($settings["fileFallbackEnabled"] ?? false),
    ];
    contentStoreSaveSection(getContentDb(), $runtimeSettingsSection, $normalized);
    $runtimeSettingsCache = $normalized;
}

function isFileFallbackEnabled(): bool
{
    return (bool) (getRuntimeSettings()["fileFallbackEnabled"] ?? false);
}

try {
    $action = $_GET["action"] ?? null;

    if ($_SERVER["REQUEST_METHOD"] === "GET" && $action === "public-content") {
        $data = [];
        foreach ($publicContentFiles as $key => $path) {
            $data[$key] = loadJsonFile($path);
        }
        respond(["ok" => true, "data" => $data]);
    }

    if ($_SERVER["REQUEST_METHOD"] === "GET" && $action === "bootstrap") {
        $data = [];
        foreach ($contentFiles as $key => $path) {
            $data[$key] = loadJsonFile($path);
        }
        $data["runtimeSettings"] = getRuntimeSettings();
        respond(["ok" => true, "data" => $data]);
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "upload-image") {
        if (!isset($_FILES["file"]) || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
            throw new RuntimeException("Файл не загружен");
        }

        $entityType = (string) ($_POST["entityType"] ?? "");
        $entityId = (string) ($_POST["entityId"] ?? "");
        $title = trim((string) ($_POST["title"] ?? ""));
        $alt = trim((string) ($_POST["alt"] ?? ""));

        if ($entityType === "" || $entityId === "") {
            throw new RuntimeException("Не указаны entityType или entityId");
        }

        $targetDir = getUploadTarget($root, $entityType);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Не удалось создать папку загрузки");
        }

        $originalName = $_FILES["file"]["name"] ?? "upload";
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ["jpg", "jpeg", "png", "webp", "gif", "svg"];
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException("Неподдерживаемый формат файла");
        }

        $baseSlug = slugify($title ?: $entityId) ?: "image";
        $mediaId = "media-{$entityType}-{$baseSlug}-" . time();
        $fileName = $mediaId . "." . $ext;
        $relativePath = "assets/media/" . match ($entityType) {
            "work" => "works",
            "collection" => "collections",
            "news" => "news",
            "workshop" => "workshop",
        } . "/" . $fileName;
        $absolutePath = $root . "/" . $relativePath;

        if (!move_uploaded_file($_FILES["file"]["tmp_name"], $absolutePath)) {
            throw new RuntimeException("Не удалось сохранить файл на сервере");
        }

        $width = null;
        $height = null;
        if ($ext !== "svg") {
            $size = @getimagesize($absolutePath);
            if (is_array($size)) {
                $width = $size[0] ?? null;
                $height = $size[1] ?? null;
            }
        }

        $media = loadJsonFile($contentFiles["media"]);
        $mediaItem = [
            "id" => $mediaId,
            "type" => "image",
            "title" => $title ?: $entityId,
            "alt" => $alt ?: ($title ?: $entityId),
            "src" => $relativePath,
            "thumbnail" => $relativePath,
            "width" => $width,
            "height" => $height,
            "relatedEntityType" => $entityType,
            "relatedEntityId" => $entityId,
            "sourceLink" => "",
            "copyrightStatus" => "uploaded",
        ];
        $media[] = $mediaItem;
        saveJsonFile($contentFiles["media"], $media);

        if ($entityType === "work") {
            $works = loadJsonFile($contentFiles["works"]);
            foreach ($works as &$work) {
                if (($work["id"] ?? "") === $entityId) {
                    $work["imageIds"] = normalizeIdList(array_merge(normalizeIdList($work["imageIds"] ?? []), [$mediaId]));
                }
            }
            unset($work);
            saveJsonFile($contentFiles["works"], $works);
            bumpCacheVersion($root);
            respond(["ok" => true, "data" => ["media" => $media, "works" => $works, "mediaItem" => $mediaItem]]);
        }

        if ($entityType === "collection") {
            $collections = loadJsonFile($contentFiles["collections"]);
            foreach ($collections as &$collection) {
                if (($collection["id"] ?? "") === $entityId) {
                    $collection["coverImageId"] = $mediaId;
                }
            }
            unset($collection);
            saveJsonFile($contentFiles["collections"], $collections);
            bumpCacheVersion($root);
            respond(["ok" => true, "data" => ["media" => $media, "collections" => $collections, "mediaItem" => $mediaItem]]);
        }

        if ($entityType === "news") {
            $news = loadJsonFile($contentFiles["news"]);
            if (!isset($news["items"]) || !is_array($news["items"])) {
                $news["items"] = [];
            }
            $newsFound = false;
            foreach ($news["items"] as &$item) {
                if (($item["id"] ?? "") === $entityId) {
                    $newsFound = true;
                    // Put freshly uploaded image first so the news card updates immediately.
                    $item["imageIds"] = normalizeIdList(array_merge([$mediaId], normalizeIdList($item["imageIds"] ?? [])));
                }
            }
            unset($item);

            if (!$newsFound) {
                throw new RuntimeException("Новость не найдена. Сначала сохраните новость, затем загрузите изображение.");
            }

            saveJsonFile($contentFiles["news"], $news);
            bumpCacheVersion($root);
            respond(["ok" => true, "data" => ["media" => $media, "news" => $news, "mediaItem" => $mediaItem]]);
        }

        if ($entityType === "workshop") {
            $workshop = loadJsonFile($contentFiles["workshop"]);
            // Put freshly uploaded workshop image first so the public block updates immediately.
            $workshop["imageIds"] = normalizeIdList(array_merge([$mediaId], normalizeIdList($workshop["imageIds"] ?? [])));
            saveJsonFile($contentFiles["workshop"], $workshop);
            bumpCacheVersion($root);
            respond(["ok" => true, "data" => ["media" => $media, "workshop" => $workshop, "mediaItem" => $mediaItem]]);
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "delete-image") {
        $body = json_decode((string) file_get_contents("php://input"), true);
        if (!is_array($body)) {
            throw new RuntimeException("Некорректный JSON payload");
        }

        $mediaId = (string) ($body["mediaId"] ?? "");
        if ($mediaId === "") {
            throw new RuntimeException("Не указан mediaId");
        }

        $media = loadJsonFile($contentFiles["media"]);
        $target = null;
        foreach ($media as $item) {
            if (($item["id"] ?? "") === $mediaId) {
                $target = $item;
                break;
            }
        }

        if ($target === null) {
            throw new RuntimeException("Изображение не найдено");
        }

        $relativePath = (string) ($target["src"] ?? "");
        if ($relativePath !== "" && isLocalMediaPath($root, $relativePath)) {
            @unlink($root . "/" . $relativePath);
        }

        $media = array_values(array_filter($media, fn(array $item): bool => ($item["id"] ?? "") !== $mediaId));
        saveJsonFile($contentFiles["media"], $media);

        $works = loadJsonFile($contentFiles["works"]);
        foreach ($works as &$work) {
            $work["imageIds"] = array_values(array_filter(normalizeIdList($work["imageIds"] ?? []), fn($id): bool => $id !== $mediaId));
            $work["detailImageIds"] = array_values(array_filter(normalizeIdList($work["detailImageIds"] ?? []), fn($id): bool => $id !== $mediaId));
        }
        unset($work);
        saveJsonFile($contentFiles["works"], $works);

        $collections = loadJsonFile($contentFiles["collections"]);
        foreach ($collections as &$collection) {
            if (($collection["coverImageId"] ?? "") === $mediaId) {
                $collection["coverImageId"] = "";
            }
        }
        unset($collection);
        saveJsonFile($contentFiles["collections"], $collections);

        $workshop = loadJsonFile($contentFiles["workshop"]);
        $workshop["imageIds"] = array_values(array_filter(normalizeIdList($workshop["imageIds"] ?? []), fn($id): bool => $id !== $mediaId));
        saveJsonFile($contentFiles["workshop"], $workshop);

        $news = loadJsonFile($contentFiles["news"]);
        if (!isset($news["items"]) || !is_array($news["items"])) {
            $news["items"] = [];
        }
        foreach ($news["items"] as &$item) {
            $item["imageIds"] = array_values(array_filter(normalizeIdList($item["imageIds"] ?? []), fn($id): bool => $id !== $mediaId));
        }
        unset($item);
        saveJsonFile($contentFiles["news"], $news);
        bumpCacheVersion($root);

        respond([
            "ok" => true,
            "data" => [
                "media" => $media,
                "works" => $works,
                "collections" => $collections,
                "workshop" => $workshop,
                "news" => $news,
            ],
        ]);
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "set-collection-cover") {
        $body = json_decode((string) file_get_contents("php://input"), true);
        if (!is_array($body)) {
            throw new RuntimeException("Invalid JSON payload");
        }

        $collectionId = trim((string) ($body["collectionId"] ?? ""));
        $mediaId = trim((string) ($body["mediaId"] ?? ""));
        if ($collectionId === "" || $mediaId === "") {
            throw new RuntimeException("Missing collectionId or mediaId");
        }

        $media = loadJsonFile($contentFiles["media"]);
        $mediaExists = false;
        foreach ($media as $item) {
            if (($item["id"] ?? "") === $mediaId) {
                $mediaExists = true;
                break;
            }
        }
        if (!$mediaExists) {
            throw new RuntimeException("Image not found");
        }

        $collections = loadJsonFile($contentFiles["collections"]);
        $found = false;
        foreach ($collections as &$collection) {
            if (($collection["id"] ?? "") === $collectionId) {
                $collection["coverImageId"] = $mediaId;
                $found = true;
                break;
            }
        }
        unset($collection);

        if (!$found) {
            throw new RuntimeException("Collection not found");
        }

        saveJsonFile($contentFiles["collections"], $collections);
        bumpCacheVersion($root);
        respond(["ok" => true, "data" => ["collections" => $collections]]);
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $body = json_decode((string) file_get_contents("php://input"), true);
        if (!is_array($body)) {
            throw new RuntimeException("Некорректный JSON payload");
        }

        if (($body["action"] ?? "") !== "save") {
            throw new RuntimeException("Неизвестное действие");
        }

        $section = (string) ($body["section"] ?? "");
        if ($section === $runtimeSettingsSection) {
            $incoming = is_array($body["data"] ?? null) ? $body["data"] : [];
            saveRuntimeSettings($incoming);
            respond(["ok" => true, "data" => getRuntimeSettings()]);
        }
        if (!array_key_exists($section, $contentFiles)) {
            throw new RuntimeException("Недопустимый раздел для сохранения");
        }

        saveJsonFile($contentFiles[$section], $body["data"] ?? null);
        bumpCacheVersion($root);
        respond(["ok" => true]);
    }

    respond(["ok" => false, "error" => "Not found"], 404);
} catch (Throwable $error) {
    respond(["ok" => false, "error" => $error->getMessage()], 500);
}
