<?php
declare(strict_types=1);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$root = dirname(__DIR__);

$contentFiles = [
    "artist" => $root . "/content/artist.json",
    "workshop" => $root . "/content/workshop.json",
    "collections" => $root . "/content/collections.json",
    "works" => $root . "/content/works.json",
    "contacts" => $root . "/content/contacts.json",
    "media" => $root . "/content/media.json",
    "siteSections" => $root . "/content/site-sections.json",
];

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function loadJsonFile(string $path)
{
    if (!is_file($path)) {
        throw new RuntimeException("File not found: {$path}");
    }
    $data = json_decode((string) file_get_contents($path), true);
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
        "workshop" => $root . "/assets/media/workshop",
        default => throw new RuntimeException("Unsupported entity type"),
    };
}

function isLocalMediaPath(string $root, string $relativePath): bool
{
    $normalized = str_replace("\\", "/", $relativePath);
    return str_starts_with($normalized, "assets/media/") && is_file($root . "/" . $normalized);
}

try {
    $action = $_GET["action"] ?? null;

    if ($_SERVER["REQUEST_METHOD"] === "GET" && $action === "bootstrap") {
        $data = [];
        foreach ($contentFiles as $key => $path) {
            $data[$key] = loadJsonFile($path);
        }
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
                    $work["imageIds"] = array_values(array_unique(array_merge($work["imageIds"] ?? [], [$mediaId])));
                }
            }
            unset($work);
            saveJsonFile($contentFiles["works"], $works);
            respond(["ok" => true, "data" => ["media" => $media, "works" => $works, "mediaItem" => $mediaItem]]);
        }

        if ($entityType === "collection") {
            $collections = loadJsonFile($contentFiles["collections"]);
            foreach ($collections as &$collection) {
                if (($collection["id"] ?? "") === $entityId && empty($collection["coverImageId"])) {
                    $collection["coverImageId"] = $mediaId;
                }
            }
            unset($collection);
            saveJsonFile($contentFiles["collections"], $collections);
            respond(["ok" => true, "data" => ["media" => $media, "collections" => $collections, "mediaItem" => $mediaItem]]);
        }

        if ($entityType === "workshop") {
            $workshop = loadJsonFile($contentFiles["workshop"]);
            $workshop["imageIds"] = array_values(array_unique(array_merge($workshop["imageIds"] ?? [], [$mediaId])));
            saveJsonFile($contentFiles["workshop"], $workshop);
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
            $work["imageIds"] = array_values(array_filter($work["imageIds"] ?? [], fn($id): bool => $id !== $mediaId));
            $work["detailImageIds"] = array_values(array_filter($work["detailImageIds"] ?? [], fn($id): bool => $id !== $mediaId));
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
        $workshop["imageIds"] = array_values(array_filter($workshop["imageIds"] ?? [], fn($id): bool => $id !== $mediaId));
        saveJsonFile($contentFiles["workshop"], $workshop);

        respond([
            "ok" => true,
            "data" => [
                "media" => $media,
                "works" => $works,
                "collections" => $collections,
                "workshop" => $workshop,
            ],
        ]);
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
        if (!array_key_exists($section, $contentFiles)) {
            throw new RuntimeException("Недопустимый раздел для сохранения");
        }

        saveJsonFile($contentFiles[$section], $body["data"] ?? null);
        respond(["ok" => true]);
    }

    respond(["ok" => false, "error" => "Not found"], 404);
} catch (Throwable $error) {
    respond(["ok" => false, "error" => $error->getMessage()], 500);
}
