<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . "/lib/content_store.php";

$overwrite = in_array("--overwrite", $argv, true);

$sections = [
    "artist" => $root . "/content/artist.json",
    "workshop" => $root . "/content/workshop.json",
    "collections" => $root . "/content/collections.json",
    "works" => $root . "/content/works.json",
    "exhibitions" => $root . "/content/exhibitions.json",
    "contacts" => $root . "/content/contacts.json",
    "media" => $root . "/content/media.json",
    "news" => $root . "/content/news.json",
    "requests" => $root . "/content/requests.json",
    "siteSections" => $root . "/content/site-sections.json",
    "browseSections" => $root . "/content/browse-sections.json",
    "collectionPage" => $root . "/content/collection-page.json",
    "workPage" => $root . "/content/work-page.json",
];

function loadJsonFileStrict(string $path)
{
    if (!is_file($path)) {
        throw new RuntimeException("File not found: {$path}");
    }

    $raw = (string) file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in {$path}: " . json_last_error_msg());
    }

    return $decoded;
}

try {
    $db = openContentStore($root);
    $imported = 0;
    $skipped = 0;

    foreach ($sections as $section => $path) {
        if (!$overwrite && contentStoreHasSection($db, $section)) {
            $skipped++;
            echo "skip {$section} (already exists)" . PHP_EOL;
            continue;
        }

        $data = loadJsonFileStrict($path);
        contentStoreSaveSection($db, $section, $data);
        $imported++;
        echo "import {$section}" . PHP_EOL;
    }

    echo PHP_EOL . "done: imported={$imported}, skipped={$skipped}" . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "migration failed: " . $error->getMessage() . PHP_EOL);
    exit(1);
}

