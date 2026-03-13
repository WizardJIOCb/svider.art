import fs from "node:fs/promises";
import path from "node:path";

const rootDir = process.cwd();
const mediaPath = path.join(rootDir, "content", "media.json");
const assetsDir = path.join(rootDir, "assets", "media");

function ensureRelativeAssetPath(relativePath) {
  return relativePath.replaceAll("\\", "/");
}

function inferExtension(url) {
  try {
    const parsed = new URL(url);
    const ext = path.extname(parsed.pathname);
    return ext || ".bin";
  } catch {
    return ".bin";
  }
}

function folderForMedia(item) {
  if (item.relatedEntityType === "work") {
    return "works";
  }
  if (item.relatedEntityType === "collection") {
    return "collections";
  }
  if (item.relatedEntityType === "workshop") {
    return "workshop";
  }
  return "misc";
}

async function downloadFile(url, destination) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Failed to download ${url}: ${response.status}`);
  }

  const arrayBuffer = await response.arrayBuffer();
  await fs.writeFile(destination, Buffer.from(arrayBuffer));
}

async function main() {
  const raw = await fs.readFile(mediaPath, "utf8");
  const media = JSON.parse(raw);

  await fs.mkdir(assetsDir, { recursive: true });

  for (const item of media) {
    if (item.type !== "image" || !item.src) {
      continue;
    }

    const extension = inferExtension(item.src);
    const folder = folderForMedia(item);
    const fileName = `${item.id}${extension}`;
    const absoluteDir = path.join(assetsDir, folder);
    const absolutePath = path.join(absoluteDir, fileName);
    const relativePath = ensureRelativeAssetPath(path.join("assets", "media", folder, fileName));

    await fs.mkdir(absoluteDir, { recursive: true });

    try {
      await fs.access(absolutePath);
    } catch {
      await downloadFile(item.src, absolutePath);
    }

    item.src = relativePath;
    item.thumbnail = relativePath;
  }

  await fs.writeFile(mediaPath, `${JSON.stringify(media, null, 2)}\n`, "utf8");

  console.log(`Cached ${media.length} media entries into ${assetsDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
