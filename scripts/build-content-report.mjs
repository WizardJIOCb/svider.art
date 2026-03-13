import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();
const contentDir = path.join(rootDir, "content");
const docsDir = path.join(rootDir, "docs");

const readJson = (fileName) =>
  JSON.parse(fs.readFileSync(path.join(contentDir, fileName), "utf8"));

const works = readJson("works.json");
const collections = readJson("collections.json");
const media = readJson("media.json");

const collectionById = new Map(collections.map((item) => [item.id, item]));
const mediaById = new Map(media.map((item) => [item.id, item]));

const workManifest = works.map((work) => {
  const images = (work.imageIds || [])
    .map((id) => mediaById.get(id))
    .filter(Boolean)
    .map((item) => ({
      id: item.id,
      title: item.title,
      src: item.src,
      thumbnail: item.thumbnail,
      width: item.width ?? null,
      height: item.height ?? null,
      sourceLink: item.sourceLink ?? null,
      copyrightStatus: item.copyrightStatus ?? null,
    }));

  return {
    id: work.id,
    slug: work.slug,
    title: work.title,
    collectionId: work.collectionId,
    collectionTitle: collectionById.get(work.collectionId)?.title ?? null,
    year: work.year ?? null,
    technique: work.technique ?? null,
    dimensionsText: work.dimensionsText ?? null,
    status: work.status ?? null,
    sourceLinks: work.sourceLinks || [],
    imageIds: work.imageIds || [],
    imagePaths: images.map((item) => item.src),
    images,
  };
});

const collectionSummary = collections
  .map((collection) => {
    const count = works.filter((work) => work.collectionId === collection.id).length;
    return {
      id: collection.id,
      title: collection.title,
      slug: collection.slug,
      workCount: count,
    };
  })
  .sort((a, b) => b.workCount - a.workCount || a.title.localeCompare(b.title, "ru"));

const localMedia = media.filter(
  (item) => typeof item.src === "string" && item.src.startsWith("assets/")
);

const manifest = {
  generatedAt: new Date().toISOString(),
  summary: {
    works: works.length,
    collections: collections.length,
    media: media.length,
    localMedia: localMedia.length,
    worksWithImages: workManifest.filter((item) => item.imagePaths.length > 0).length,
  },
  collections: collectionSummary,
  works: workManifest,
};

fs.writeFileSync(
  path.join(contentDir, "asset-manifest.json"),
  `${JSON.stringify(manifest, null, 2)}\n`,
  "utf8"
);

const topCollections = collectionSummary
  .map((item) => `- ${item.title}: ${item.workCount} работ`)
  .join("\n");

const firstWorks = workManifest
  .slice(0, 12)
  .map(
    (item) =>
      `- ${item.title} (${item.collectionTitle ?? "без коллекции"}) -> ${
        item.imagePaths[0] ?? "без изображения"
      }`
  )
  .join("\n");

const report = `# Готовность контента

Дата: ${new Date().toISOString().slice(0, 10)}

## Что уже собрано

- Работы: ${works.length}
- Коллекции: ${collections.length}
- Медиафайлы: ${media.length}
- Локальные медиафайлы: ${localMedia.length}
- Работы с изображениями: ${workManifest.filter((item) => item.imagePaths.length > 0).length} из ${works.length}

## Что это значит для следующего этапа

Контентная база уже пригодна для разработки сайта: каталог можно строить на реальных данных, а карточки и detail-страницы можно показывать с локальными файлами, без зависимости от внешних ссылок.

## Покрытие по коллекциям

${topCollections}

## Примеры связки работа -> файл

${firstWorks}

## Где лежат файлы

- Работы: \`assets/media/works/\`
- Коллекции: \`assets/media/collections/\`
- Мастерская: \`assets/media/workshop/\`
- Сводный манифест: \`content/asset-manifest.json\`

## Что ещё стоит добрать позже

- Права на публикацию изображений для продакшена
- Дополнительные detail-изображения для отдельных работ
- Финально подтверждённые цены и наличие
- Полный список выставок и биографии из первоисточника мастера
`;

fs.writeFileSync(path.join(docsDir, "content-readiness.md"), `${report}\n`, "utf8");

console.log(
  JSON.stringify(
    {
      assetManifest: "content/asset-manifest.json",
      contentReadiness: "docs/content-readiness.md",
      works: works.length,
      collections: collections.length,
      media: media.length,
      worksWithImages: workManifest.filter((item) => item.imagePaths.length > 0).length,
    },
    null,
    2
  )
);
