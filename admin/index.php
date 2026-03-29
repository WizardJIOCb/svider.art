<!doctype html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Админка svider.art</title>
    <link rel="icon" type="image/svg+xml" href="/assets/admin-favicon.svg" />
    <link rel="stylesheet" href="./admin.css" />
  </head>
  <body>
    <div class="admin-shell">
      <aside class="admin-sidebar">
        <div>
          <p class="admin-kicker">svider.art</p>
          <h1>Админка</h1>
          <p class="admin-subtitle">Редактирование работ, коллекций, контактов и основных текстов сайта.</p>
        </div>

        <nav class="admin-nav">
          <button class="admin-nav__item is-active" type="button" data-tab="dashboard">Обзор</button>
          <button class="admin-nav__item" type="button" data-tab="news">Новости</button>
          <button class="admin-nav__item" type="button" data-tab="requests">Заявки</button>
          <button class="admin-nav__item" type="button" data-tab="seasons">Сезоны</button>
          <button class="admin-nav__item" type="button" data-tab="works">Гравюры</button>
          <button class="admin-nav__item" type="button" data-tab="collections">Коллекции</button>
          <button class="admin-nav__item" type="button" data-tab="contacts">Контакты</button>
          <button class="admin-nav__item" type="button" data-tab="settings">Настройки</button>
        </nav>

        <div class="admin-sidebar__footer">
          <button class="button button--ghost" type="button" id="reloadData">Перезагрузить данные</button>
          <a class="button button--ghost" href="/" target="_blank" rel="noreferrer">Открыть сайт</a>
        </div>
      </aside>

      <main class="admin-main">
        <header class="admin-header">
          <div>
            <p class="admin-kicker">Панель управления</p>
            <h2 id="pageTitle">Обзор</h2>
          </div>
          <div class="admin-header__actions">
            <span class="admin-status" id="saveStatus">Данные загружены</span>
          </div>
        </header>

        <section class="admin-panel is-active" data-panel="dashboard">
          <div class="stat-grid" id="dashboardStats"></div>
          <div class="note-card">
            <h3>Что умеет админка</h3>
            <p>Можно редактировать карточки гравюр, коллекции, контакты, основные тексты сайта и загружать изображения для работ прямо на сервер.</p>
          </div>
        </section>

        <section class="admin-panel" data-panel="news">
          <div class="split-layout">
            <div class="list-card">
              <div class="list-card__top">
                <input class="search-input" id="newsSearch" type="search" placeholder="Поиск по новостям" />
                <button class="button button--primary" type="button" id="addNews">Новая новость</button>
              </div>
              <div class="item-list" id="newsList"></div>
            </div>

            <div class="editor-card">
              <div class="editor-card__header">
                <div>
                  <p class="admin-kicker">Редактор новости</p>
                  <h3 id="newsEditorTitle">Выберите новость</h3>
                </div>
                <div class="editor-card__actions">
                  <button class="button button--ghost" type="button" id="deleteNews">Удалить</button>
                  <button class="button button--primary" type="button" id="saveNews">Сохранить новости</button>
                </div>
              </div>

              <form class="form-grid" id="newsSectionForm">
                <h4>Заголовок секции</h4>
                <label>
                  Kicker
                  <input name="kicker" type="text" />
                </label>
                <label>
                  Заголовок
                  <input name="title" type="text" />
                </label>
                <label class="full-width">
                  Текст секции
                  <textarea name="text" rows="3"></textarea>
                </label>
              </form>

              <form class="form-grid" id="newsForm">
                <h4>Новость</h4>
                <label>
                  Заголовок
                  <input name="title" type="text" />
                </label>
                <label>
                  Slug
                  <input name="slug" type="text" />
                </label>
                <label>
                  ID
                  <input name="id" type="text" />
                </label>
                <label>
                  Дата публикации
                  <input name="publishedAt" type="date" />
                </label>
                <label class="checkbox-field">
                  <input name="published" type="checkbox" />
                  <span>Опубликовано</span>
                </label>
                <label class="checkbox-field">
                  <input name="featured" type="checkbox" />
                  <span>Показывать как важную</span>
                </label>
                <label>
                  Порядок
                  <input name="order" type="number" />
                </label>
                <label class="full-width">
                  Короткий анонс
                  <textarea name="summary" rows="3"></textarea>
                </label>
                <label class="full-width">
                  HTML-текст новости
                  <textarea name="bodyHtml" rows="8"></textarea>
                </label>
              </form>

              <div class="upload-card">
                <div>
                  <p class="admin-kicker">Изображения новости</p>
                  <h4>Галерея новости</h4>
                </div>
                <div class="upload-grid">
                  <input id="newsImageFile" type="file" accept="image/*" />
                  <input id="newsImageTitle" type="text" placeholder="Заголовок изображения" />
                  <input id="newsImageAlt" type="text" placeholder="Alt-текст" />
                  <button class="button button--primary" type="button" id="uploadNewsImage">Загрузить изображение</button>
                </div>
                <div class="image-preview" id="newsImagePreview"></div>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel" data-panel="requests">
          <div class="split-layout">
            <div class="list-card">
              <div class="list-card__top">
                <input class="search-input" id="requestSearch" type="search" placeholder="Поиск по заявкам" />
              </div>
              <div class="item-list" id="requestsList"></div>
            </div>

            <div class="editor-card">
              <div class="editor-card__header">
                <div>
                  <p class="admin-kicker">Заявки с сайта</p>
                  <h3 id="requestEditorTitle">Выберите заявку</h3>
                </div>
              <div class="editor-card__actions">
                  <button class="button button--ghost" type="button" id="deleteRequest">Удалить</button>
                  <button class="button button--primary" type="button" id="saveRequests">Сохранить заявки</button>
                </div>
              </div>

              <form class="form-grid" id="requestForm">
                <label>
                  ID
                  <input name="id" type="text" readonly />
                </label>
                <label>
                  Дата
                  <input name="submittedAt" type="text" readonly />
                </label>
                <label>
                  Статус
                  <select name="status">
                    <option value="new">Новая</option>
                    <option value="in_progress">В работе</option>
                    <option value="done">Завершена</option>
                    <option value="archived">В архиве</option>
                  </select>
                </label>
                <label>
                  Источник
                  <input name="source" type="text" readonly />
                </label>
                <label>
                  Тип запроса
                  <input name="requestType" type="text" readonly />
                </label>
                <label>
                  Работа или серия
                  <input name="workTitle" type="text" readonly />
                </label>
                <label>
                  Имя
                  <input name="name" type="text" readonly />
                </label>
                <label>
                  Контакт
                  <input name="contact" type="text" readonly />
                </label>
                <label>
                  Размер / формат
                  <input name="size" type="text" readonly />
                </label>
                <label>
                  Город
                  <input name="city" type="text" readonly />
                </label>
                <label class="full-width">
                  Предпочтительный способ связи
                  <input name="preferredChannel" type="text" readonly />
                </label>
                <label class="full-width">
                  Текст обращения
                  <textarea name="message" rows="6" readonly></textarea>
                </label>
                <label class="full-width">
                  Комментарий администратора
                  <textarea name="adminNote" rows="4"></textarea>
                </label>
                <label class="full-width">
                  Статусы уведомлений
                  <textarea name="notificationsSummary" rows="4" readonly></textarea>
                </label>
              </form>

            </div>
          </div>
        </section>

        <section class="admin-panel" data-panel="seasons">
          <div class="editor-card">
            <div class="editor-card__header">
              <div>
                <p class="admin-kicker">Сезоны и коллекции</p>
                <h3>Блок на главной и страница коллекции</h3>
              </div>
              <div class="editor-card__actions">
                <button class="button button--primary" type="button" id="saveSeasons">Сохранить Сезоны</button>
              </div>
            </div>

            <div class="settings-grid">
              <form class="form-grid" id="seasonSectionForm">
                <h4>Блок «Сезоны, серии и авторские циклы»</h4>
                <label>
                  Kicker
                  <input name="kicker" type="text" />
                </label>
                <label>
                  Заголовок
                  <input name="title" type="text" />
                </label>
                <label class="full-width">
                  Описание
                  <textarea name="text" rows="3"></textarea>
                </label>
                <label>
                  Заголовок страницы коллекций
                  <input name="pageTitle" type="text" />
                </label>
                <label class="full-width">
                  Лид страницы коллекций
                  <textarea name="pageLead" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Примечание страницы коллекций
                  <textarea name="pageNote" rows="2"></textarea>
                </label>
                <label>
                  Метка карточки
                  <input name="cardMetaLabel" type="text" />
                </label>
                <label>
                  Подпись количества работ
                  <input name="workCountLabel" type="text" />
                </label>
              </form>

              <form class="form-grid" id="seasonCollectionPageForm">
                <h4>Шаблон страницы коллекции</h4>
                <label>
                  Заголовок (шаблон)
                  <input name="heroTitleTemplate" type="text" />
                </label>
                <label>
                  Подзаголовок (шаблон)
                  <input name="heroSubtitleTemplate" type="text" />
                </label>
                <label class="full-width">
                  Описание (шаблон)
                  <textarea name="heroDescriptionTemplate" rows="3"></textarea>
                </label>
                <label>
                  Заголовок блока «О коллекции»
                  <input name="leadTitle" type="text" />
                </label>
                <label class="full-width">
                  Текст блока «О коллекции»
                  <textarea name="leadText" rows="3"></textarea>
                </label>
                <label>
                  Заголовок блока «Работы внутри коллекции»
                  <input name="worksTitle" type="text" />
                </label>
                <label class="full-width">
                  Текст блока «Работы внутри коллекции»
                  <textarea name="worksText" rows="3"></textarea>
                </label>
              </form>
            </div>
          </div>

          <div class="split-layout" style="margin-top: 1.2rem;">
            <div class="list-card">
              <div class="list-card__top">
                <input class="search-input" id="seasonCollectionSearch" type="search" placeholder="Поиск по сезонам и коллекциям" />
                <button class="button button--primary" type="button" id="addSeasonCollection">Новый сезон</button>
              </div>
              <div class="item-list" id="seasonCollectionsList"></div>
            </div>

            <div class="editor-card">
              <div class="editor-card__header">
                <div>
                  <p class="admin-kicker">Редактор сезона</p>
                  <h3 id="seasonCollectionEditorTitle">Выберите сезон</h3>
                </div>
                <div class="editor-card__actions">
                  <button class="button button--ghost" type="button" id="deleteSeasonCollection">Удалить сезон</button>
                </div>
              </div>

              <form class="form-grid" id="seasonCollectionForm">
                <label>
                  Название
                  <input name="title" type="text" />
                </label>
                <label>
                  Slug
                  <input name="slug" type="text" />
                </label>
                <label>
                  ID
                  <input name="id" type="text" />
                </label>
                <label>
                  Тип
                  <input name="season" type="text" />
                </label>
                <label>
                  Тема
                  <input name="theme" type="text" />
                </label>
                <label>
                  Период
                  <input name="period" type="text" />
                </label>
                <label>
                  Год начала
                  <input name="yearStart" type="number" />
                </label>
                <label>
                  Год окончания
                  <input name="yearEnd" type="number" />
                </label>
                <label class="checkbox-field">
                  <input name="featured" type="checkbox" />
                  <span>Показывать на главной</span>
                </label>
                <label>
                  Порядок
                  <input name="order" type="number" />
                </label>
                <label class="full-width">
                  Краткое описание
                  <textarea name="shortDescription" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Полное описание
                  <textarea name="fullDescription" rows="5"></textarea>
                </label>
                <label class="full-width">
                  Лид на странице коллекции (персональный)
                  <textarea name="detailLeadText" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Кураторский текст (персональный)
                  <textarea name="detailCuratorText" rows="4"></textarea>
                </label>
                <label class="full-width">
                  Текст про технику/характер цикла (персональный)
                  <textarea name="detailProcessText" rows="4"></textarea>
                </label>
                <label class="full-width">
                  Источники через новую строку
                  <textarea name="sourceLinks" rows="3"></textarea>
                </label>
              </form>

              <div class="upload-card">
                <div>
                  <p class="admin-kicker">Изображения сезона</p>
                  <h4>Обложка и галерея</h4>
                </div>
                <div class="upload-grid">
                  <input id="seasonCollectionImageFile" type="file" accept="image/*" />
                  <input id="seasonCollectionImageTitle" type="text" placeholder="Заголовок изображения" />
                  <input id="seasonCollectionImageAlt" type="text" placeholder="Alt-текст" />
                  <button class="button button--primary" type="button" id="uploadSeasonCollectionImage">Загрузить изображение</button>
                </div>
                <div class="image-preview" id="seasonCollectionImagePreview"></div>
              </div>

              <div class="upload-card">
                <div class="list-card__top" style="padding: 0;">
                  <div>
                    <p class="admin-kicker">Работы внутри сезона</p>
                    <h4 id="seasonWorkEditorTitle">Выберите работу</h4>
                  </div>
                  <div class="editor-card__actions">
                    <button class="button button--ghost" type="button" id="deleteSeasonWork">Удалить работу</button>
                    <button class="button button--primary" type="button" id="addSeasonWork">Добавить работу</button>
                  </div>
                </div>
                <div style="margin-top: 0.75rem;">
                  <input class="search-input" id="seasonWorkSearch" type="search" placeholder="Поиск работ внутри сезона" />
                </div>
                <div class="item-list" id="seasonWorksList" style="max-height: 320px;"></div>

                <form class="form-grid" id="seasonWorkForm">
                  <label>
                    Название
                    <input name="title" type="text" />
                  </label>
                  <label>
                    Slug
                    <input name="slug" type="text" />
                  </label>
                  <label>
                    ID
                    <input name="id" type="text" />
                  </label>
                  <label>
                    Год
                    <input name="year" type="number" />
                  </label>
                  <label>
                    Техника
                    <input name="technique" type="text" />
                  </label>
                  <label>
                    Порядок
                    <input name="order" type="number" />
                  </label>
                  <label>
                    Статус
                    <select name="status">
                      <option value="unknown">Статус уточняется</option>
                      <option value="available">В наличии</option>
                      <option value="on_request">По запросу</option>
                      <option value="private_collection">В частной коллекции</option>
                    </select>
                  </label>
                  <label class="checkbox-field">
                    <input name="featured" type="checkbox" />
                    <span>Показывать в избранном</span>
                  </label>
                  <label class="full-width">
                    Материалы через запятую
                    <input name="materials" type="text" />
                  </label>
                  <label class="full-width">
                    Короткое описание
                    <textarea name="shortDescription" rows="3"></textarea>
                  </label>
                  <label class="full-width">
                    Полное описание
                    <textarea name="fullDescription" rows="4"></textarea>
                  </label>
                  <label class="full-width">
                    Источники через новую строку
                    <textarea name="sourceLinks" rows="3"></textarea>
                  </label>
                </form>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel" data-panel="works">
          <div class="split-layout">
            <div class="list-card">
              <div class="list-card__top">
                <input class="search-input" id="workSearch" type="search" placeholder="Поиск по гравюрам" />
                <button class="button button--primary" type="button" id="addWork">Новая работа</button>
              </div>
              <div class="item-list" id="worksList"></div>
            </div>

            <div class="editor-card">
              <div class="editor-card__header">
                <div>
                  <p class="admin-kicker">Редактор работы</p>
                  <h3 id="workEditorTitle">Выберите гравюру</h3>
                </div>
                <div class="editor-card__actions">
                  <button class="button button--ghost" type="button" id="deleteWork">Удалить</button>
                  <button class="button button--primary" type="button" id="saveWorks">Сохранить работы</button>
                </div>
              </div>

              <form class="form-grid" id="workForm">
                <label>
                  Название
                  <input name="title" type="text" />
                </label>
                <label>
                  Slug
                  <input name="slug" type="text" />
                </label>
                <label>
                  ID
                  <input name="id" type="text" />
                </label>
                <label>
                  Коллекция
                  <select name="collectionId" id="workCollectionSelect"></select>
                </label>
                <label>
                  Год
                  <input name="year" type="number" />
                </label>
                <label>
                  Техника
                  <input name="technique" type="text" />
                </label>
                <label>
                  Ширина, см
                  <input name="width" type="number" step="0.1" />
                </label>
                <label>
                  Высота, см
                  <input name="height" type="number" step="0.1" />
                </label>
                <label>
                  Размер текстом
                  <input name="dimensionsText" type="text" />
                </label>
                <label>
                  Статус
                  <select name="status">
                    <option value="unknown">Статус уточняется</option>
                    <option value="available">В наличии</option>
                    <option value="on_request">По запросу</option>
                    <option value="private_collection">В частной коллекции</option>
                  </select>
                </label>
                <label class="checkbox-field">
                  <input name="featured" type="checkbox" />
                  <span>Показывать в избранном</span>
                </label>
                <label>
                  Порядок
                  <input name="order" type="number" />
                </label>
                <label class="full-width">
                  Материалы через запятую
                  <input name="materials" type="text" />
                </label>
                <label class="full-width">
                  Короткое описание
                  <textarea name="shortDescription" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Полное описание
                  <textarea name="fullDescription" rows="6"></textarea>
                </label>
                <label class="full-width">
                  Источники через новую строку
                  <textarea name="sourceLinks" rows="4"></textarea>
                </label>
              </form>

              <div class="upload-card">
                <div>
                  <p class="admin-kicker">Изображение работы</p>
                  <h4>Загрузка на сервер</h4>
                </div>
                <div class="upload-grid">
                  <input id="workImageFile" type="file" accept="image/*" />
                  <input id="workImageTitle" type="text" placeholder="Заголовок изображения" />
                  <input id="workImageAlt" type="text" placeholder="Alt-текст" />
                  <button class="button button--primary" type="button" id="uploadWorkImage">Загрузить изображение</button>
                </div>
                <div class="image-preview" id="workImagePreview"></div>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel" data-panel="collections">
          <div class="split-layout">
            <div class="list-card">
              <div class="list-card__top">
                <input class="search-input" id="collectionSearch" type="search" placeholder="Поиск по коллекциям" />
                <button class="button button--primary" type="button" id="addCollection">Новая коллекция</button>
              </div>
              <div class="item-list" id="collectionsList"></div>
            </div>

            <div class="editor-card">
              <div class="editor-card__header">
                <div>
                  <p class="admin-kicker">Редактор коллекции</p>
                  <h3 id="collectionEditorTitle">Выберите коллекцию</h3>
                </div>
                <div class="editor-card__actions">
                  <button class="button button--ghost" type="button" id="deleteCollection">Удалить</button>
                  <button class="button button--primary" type="button" id="saveCollections">Сохранить коллекции</button>
                </div>
              </div>

              <form class="form-grid" id="collectionForm">
                <label>
                  Название
                  <input name="title" type="text" />
                </label>
                <label>
                  Slug
                  <input name="slug" type="text" />
                </label>
                <label>
                  ID
                  <input name="id" type="text" />
                </label>
                <label>
                  Тип
                  <input name="season" type="text" />
                </label>
                <label>
                  Тема
                  <input name="theme" type="text" />
                </label>
                <label>
                  Период
                  <input name="period" type="text" />
                </label>
                <label>
                  Год начала
                  <input name="yearStart" type="number" />
                </label>
                <label>
                  Год окончания
                  <input name="yearEnd" type="number" />
                </label>
                <label class="checkbox-field">
                  <input name="featured" type="checkbox" />
                  <span>Показывать на главной</span>
                </label>
                <label>
                  Порядок
                  <input name="order" type="number" />
                </label>
                <label class="full-width">
                  Краткое описание
                  <textarea name="shortDescription" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Полное описание
                  <textarea name="fullDescription" rows="6"></textarea>
                </label>
                <label class="full-width">
                  Источники через новую строку
                  <textarea name="sourceLinks" rows="4"></textarea>
                </label>
              </form>

              <div class="upload-card">
                <div>
                  <p class="admin-kicker">Изображения коллекции</p>
                  <h4>Обложка и галерея</h4>
                </div>
                <div class="upload-grid">
                  <input id="collectionImageFile" type="file" accept="image/*" />
                  <input id="collectionImageTitle" type="text" placeholder="Заголовок изображения" />
                  <input id="collectionImageAlt" type="text" placeholder="Alt-текст" />
                  <button class="button button--primary" type="button" id="uploadCollectionImage">Загрузить изображение</button>
                </div>
                <div class="image-preview" id="collectionImagePreview"></div>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel" data-panel="contacts">
          <div class="editor-card">
            <div class="editor-card__header">
              <div>
                <p class="admin-kicker">Контакты</p>
                <h3>Редактор контактных данных</h3>
              </div>
              <div class="editor-card__actions">
                <button class="button button--ghost" type="button" id="addContact">Добавить контакт</button>
                <button class="button button--primary" type="button" id="saveContacts">Сохранить контакты</button>
              </div>
            </div>
            <div class="contact-editor" id="contactsEditor"></div>
          </div>
        </section>

        <section class="admin-panel" data-panel="settings">
          <div class="editor-card">
            <div class="editor-card__header">
              <div>
                <p class="admin-kicker">Основные данные</p>
                <h3>Мастерская и тексты сайта</h3>
              </div>
              <div class="editor-card__actions">
                <button class="button button--primary" type="button" id="saveSettings">Сохранить настройки</button>
              </div>
            </div>

            <div class="settings-grid">
              <form class="form-grid" id="artistForm">
                <h4>Сергей Свидер</h4>
                <label>
                  Полное имя
                  <input name="fullName" type="text" />
                </label>
                <label>
                  Короткое имя
                  <input name="shortName" type="text" />
                </label>
                <label>
                  Город
                  <input name="city" type="text" />
                </label>
                <label>
                  Титул
                  <input name="title" type="text" />
                </label>
                <label class="full-width">
                  Короткая биография
                  <textarea name="shortBio" rows="4"></textarea>
                </label>
                <label class="full-width">
                  Полная биография
                  <textarea name="fullBio" rows="8"></textarea>
                </label>
              </form>

              <form class="form-grid" id="workshopForm">
                <h4>Мастерская</h4>
                <label>
                  Название
                  <input name="name" type="text" />
                </label>
                <label>
                  Город
                  <input name="city" type="text" />
                </label>
                <label class="full-width">
                  Короткое описание
                  <textarea name="descriptionShort" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Полное описание
                  <textarea name="descriptionFull" rows="6"></textarea>
                </label>
                <label class="full-width">
                  Текст о процессе
                  <textarea name="processText" rows="5"></textarea>
                </label>
              </form>

              <form class="form-grid full-span" id="heroForm">
                <h4>Главный экран</h4>
                <label>
                  Подзаголовок
                  <input name="kicker" type="text" />
                </label>
                <label class="full-width">
                  Заголовок
                  <textarea name="title" rows="3"></textarea>
                </label>
                <label class="full-width">
                  Основной текст
                  <textarea name="text" rows="4"></textarea>
                </label>
                <label class="full-width">
                  Дополнительный текст
                  <textarea name="supportText" rows="4"></textarea>
                </label>
              </form>

              <form class="form-grid" id="brandForm">
                <h4>Шапка сайта</h4>
                <label>
                  Подпись над именем
                  <input name="eyebrow" type="text" />
                </label>
                <label class="full-width">
                  Имя мастера
                  <input name="name" type="text" />
                </label>
              </form>

              <form class="form-grid" id="heroNotesForm">
                <h4>Блоки под героем</h4>
                <label>
                  Метка «Подход»
                  <input name="approachLabel" type="text" />
                </label>
                <label class="full-width">
                  Текст «Подход»
                  <textarea name="approachText" rows="3"></textarea>
                </label>
                <label>
                  Метка «Коллекции»
                  <input name="collectionsLabel" type="text" />
                </label>
                <label class="full-width">
                  Текст «Коллекции»
                  <textarea name="collectionsText" rows="3"></textarea>
                </label>
              </form>

              <form class="form-grid full-span" id="visibilityForm">
                <h4>Отображение блоков</h4>
                <label class="checkbox-field">
                  <input name="featuredCollectionsEnabled" type="checkbox" />
                  <span>Показывать блок «Избранные работы» на главной</span>
                </label>
              </form>
            </div>
          </div>
        </section>
      </main>
    </div>

    <script src="./admin.js"></script>
  </body>
</html>
