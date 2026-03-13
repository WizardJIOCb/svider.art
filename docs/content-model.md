# Content Model

Дата: 2026-03-13

## Назначение

Этот документ фиксирует практическую модель контента для сайта мастерской Сергея Михайловича Свидера. Он нужен для наполнения сайта из структурированных данных, а не вручную по страницам.

## Сущности

Основные сущности:

- `artist`
- `workshop`
- `collection`
- `work`
- `exhibition`
- `contact`
- `media`

## Связи

- один `artist` связан с многими `work`
- один `artist` может быть связан с многими `collection`
- одна `collection` содержит много `work`
- один `workshop` связан с `artist`, `contact` и `media`
- один `exhibition` может ссылаться на `work` и `collection`
- один `media` может принадлежать любой сущности

## Минимальный набор данных для MVP

- 1 `artist`
- 1 `workshop`
- 4-8 `collection`
- 12-20 `work`
- 1-6 `contact`
- 20-40 `media`

## Правила наполнения

- Не выдумывать биографию, даты и параметры работ.
- Если поле не подтверждено, оставлять `null`, пустую строку или пустой массив.
- Названия работ и коллекций хранить в оригинальной форме.
- Описательные тексты с VK переписывать в едином тоне сайта.
- Для изображений хранить источник и статус прав на использование.

## Структура файлов

- `content/artist.json`
- `content/workshop.json`
- `content/collections.json`
- `content/works.json`
- `content/exhibitions.json`
- `content/contacts.json`
- `content/media.json`
- `content/asset-manifest.json`
- `content/site-sections.json`
- `content/browse-sections.json`
- `content/collection-page.json`
- `content/work-page.json`
- `docs/content-readiness.md`

## Порядок наполнения

1. Заполнить `artist.json`
2. Заполнить `workshop.json`
3. Собрать `collections.json`
4. Наполнить `works.json`
5. Добавить `contacts.json`
6. Связать изображения в `media.json`
7. Отдельно добрать `exhibitions.json`

## Производные файлы

- `content/asset-manifest.json` - сводный индекс работ, коллекций и локальных медиафайлов для проверки наполнения.
- `docs/content-readiness.md` - короткий отчет о готовности контента перед переходом к разработке интерфейса.
