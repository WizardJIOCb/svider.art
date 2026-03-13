const API_URL = "./api.php";

const state = {
  artist: null,
  workshop: null,
  contacts: [],
  collections: [],
  works: [],
  media: [],
  siteSections: null,
};

let currentWorkId = null;
let currentCollectionId = null;

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function slugify(value) {
  return String(value || "")
    .toLowerCase()
    .trim()
    .replace(/[^a-zа-яё0-9]+/gi, "-")
    .replace(/^-+|-+$/g, "");
}

function setStatus(text, isError = false) {
  const node = document.querySelector("#saveStatus");
  if (!node) return;
  node.textContent = text;
  node.style.color = isError ? "#8f3f39" : "";
}

function getMediaMap() {
  return new Map(state.media.map((item) => [item.id, item]));
}

function getCollectionById(id) {
  return state.collections.find((item) => item.id === id);
}

function getWorkById(id) {
  return state.works.find((item) => item.id === id);
}

async function apiGet(action) {
  const response = await fetch(`${API_URL}?action=${encodeURIComponent(action)}`, {
    credentials: "same-origin",
  });
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }
  return response.json();
}

async function apiPostJson(payload) {
  const response = await fetch(API_URL, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.error || `Request failed: ${response.status}`);
  }
  return data;
}

async function apiUpload(formData) {
  const response = await fetch(`${API_URL}?action=upload-image`, {
    method: "POST",
    credentials: "same-origin",
    body: formData,
  });
  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.error || `Upload failed: ${response.status}`);
  }
  return data;
}

async function apiDeleteImage(mediaId) {
  const response = await fetch(`${API_URL}?action=delete-image`, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ mediaId }),
  });
  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.error || `Delete failed: ${response.status}`);
  }
  return data;
}

async function loadBootstrap() {
  setStatus("Загрузка данных...");
  const payload = await apiGet("bootstrap");
  Object.assign(state, payload.data);
  if (!currentWorkId && state.works.length) {
    currentWorkId = state.works[0].id;
  }
  if (!currentCollectionId && state.collections.length) {
    currentCollectionId = state.collections[0].id;
  }
  renderAll();
  setStatus("Данные загружены");
}

function renderAll() {
  renderDashboard();
  renderWorks();
  renderCollections();
  renderContacts();
  renderSettings();
}

function renderDashboard() {
  const node = document.querySelector("#dashboardStats");
  if (!node) return;
  node.innerHTML = `
    <article class="stat-card">
      <p class="admin-kicker">Гравюры</p>
      <p class="stat-card__value">${state.works.length}</p>
    </article>
    <article class="stat-card">
      <p class="admin-kicker">Коллекции</p>
      <p class="stat-card__value">${state.collections.length}</p>
    </article>
    <article class="stat-card">
      <p class="admin-kicker">Контакты</p>
      <p class="stat-card__value">${state.contacts.length}</p>
    </article>
    <article class="stat-card">
      <p class="admin-kicker">Медиа</p>
      <p class="stat-card__value">${state.media.length}</p>
    </article>
  `;
}

function renderWorks() {
  const listNode = document.querySelector("#worksList");
  const query = (document.querySelector("#workSearch")?.value || "").toLowerCase();
  const collectionSelect = document.querySelector("#workCollectionSelect");
  if (collectionSelect) {
    collectionSelect.innerHTML = state.collections
      .slice()
      .sort((a, b) => (a.order ?? 999) - (b.order ?? 999))
      .map((collection) => `<option value="${escapeHtml(collection.id)}">${escapeHtml(collection.title)}</option>`)
      .join("");
  }

  const items = state.works
    .slice()
    .sort((a, b) => (b.year ?? 0) - (a.year ?? 0) || (a.order ?? 999) - (b.order ?? 999))
    .filter((work) => {
      const haystack = `${work.title} ${work.shortDescription} ${work.slug}`.toLowerCase();
      return !query || haystack.includes(query);
    });

  listNode.innerHTML = items
    .map((work) => {
      const collection = getCollectionById(work.collectionId);
      return `
        <button class="item-card ${work.id === currentWorkId ? "is-active" : ""}" type="button" data-work-id="${escapeHtml(work.id)}">
          <strong>${escapeHtml(work.title)}</strong>
          <span class="item-card__meta">${escapeHtml(collection?.title || "Без коллекции")} · ${escapeHtml(work.year || "")}</span>
        </button>
      `;
    })
    .join("");

  listNode.querySelectorAll("[data-work-id]").forEach((button) => {
    button.addEventListener("click", () => {
      currentWorkId = button.dataset.workId;
      renderWorks();
    });
  });

  populateWorkForm();
}

function populateWorkForm() {
  const work = getWorkById(currentWorkId);
  const form = document.querySelector("#workForm");
  const titleNode = document.querySelector("#workEditorTitle");
  if (!form) return;

  if (!work) {
    form.reset();
    titleNode.textContent = "Выберите гравюру";
    return;
  }

  titleNode.textContent = work.title || "Работа";
  form.elements.id.value = work.id || "";
  form.elements.title.value = work.title || "";
  form.elements.slug.value = work.slug || "";
  form.elements.collectionId.value = work.collectionId || "";
  form.elements.year.value = work.year ?? "";
  form.elements.technique.value = work.technique || "";
  form.elements.width.value = work.width ?? "";
  form.elements.height.value = work.height ?? "";
  form.elements.dimensionsText.value = work.dimensionsText || "";
  form.elements.status.value = work.status || "unknown";
  form.elements.featured.checked = Boolean(work.featured);
  form.elements.order.value = work.order ?? "";
  form.elements.materials.value = (work.materials || []).join(", ");
  form.elements.shortDescription.value = work.shortDescription || "";
  form.elements.fullDescription.value = work.fullDescription || "";
  form.elements.sourceLinks.value = (work.sourceLinks || []).join("\n");

  renderWorkImages(work);
}

function renderWorkImages(work) {
  const node = document.querySelector("#workImagePreview");
  if (!node) return;
  const mediaMap = getMediaMap();
  const images = (work?.imageIds || [])
    .map((id) => mediaMap.get(id))
    .filter(Boolean);

  node.innerHTML = images.length
    ? images
        .map(
          (image) => `
            <article class="image-preview__card">
              <img src="/${escapeHtml(image.src)}" alt="${escapeHtml(image.alt || image.title || "")}" />
              <div class="image-preview__caption">
                <span>${escapeHtml(image.title || image.id)}</span>
                <button class="button button--danger button--tiny" type="button" data-delete-image-id="${escapeHtml(image.id)}">Удалить</button>
              </div>
            </article>
          `
        )
        .join("")
    : `<div class="note-card"><p>У этой работы пока нет привязанных изображений.</p></div>`;

  node.querySelectorAll("[data-delete-image-id]").forEach((button) => {
    button.addEventListener("click", async () => {
      const mediaId = button.dataset.deleteImageId;
      const image = images.find((item) => item.id === mediaId);
      const ok = window.confirm(
        `Удалить изображение${image?.title ? ` «${image.title}»` : ""}? Файл будет убран с сервера и отвязан от сайта.`
      );
      if (!ok) {
        return;
      }
      try {
        setStatus("Удаление изображения...");
        const result = await apiDeleteImage(mediaId);
        state.media = result.data.media;
        state.works = result.data.works;
        state.collections = result.data.collections;
        if (result.data.workshop) {
          state.workshop = result.data.workshop;
        }
        renderAll();
        setStatus("Изображение удалено");
      } catch (error) {
        handleError(error);
      }
    });
  });
}

function syncWorkFormToState() {
  const form = document.querySelector("#workForm");
  const work = getWorkById(currentWorkId);
  if (!form || !work) return;

  work.id = form.elements.id.value.trim();
  work.title = form.elements.title.value.trim();
  work.slug = form.elements.slug.value.trim();
  work.collectionId = form.elements.collectionId.value;
  work.year = form.elements.year.value ? Number(form.elements.year.value) : null;
  work.technique = form.elements.technique.value.trim();
  work.width = form.elements.width.value ? Number(form.elements.width.value) : null;
  work.height = form.elements.height.value ? Number(form.elements.height.value) : null;
  work.dimensionsText = form.elements.dimensionsText.value.trim();
  work.status = form.elements.status.value;
  work.featured = form.elements.featured.checked;
  work.order = form.elements.order.value ? Number(form.elements.order.value) : null;
  work.materials = form.elements.materials.value
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
  work.shortDescription = form.elements.shortDescription.value.trim();
  work.fullDescription = form.elements.fullDescription.value.trim();
  work.sourceLinks = form.elements.sourceLinks.value
    .split("\n")
    .map((item) => item.trim())
    .filter(Boolean);
}

function syncCollectionWorkIds() {
  const map = new Map(state.collections.map((collection) => [collection.id, []]));
  state.works.forEach((work) => {
    if (map.has(work.collectionId)) {
      map.get(work.collectionId).push(work.id);
    }
  });
  state.collections.forEach((collection) => {
    collection.workIds = map.get(collection.id) || [];
  });
}

function renderCollections() {
  const listNode = document.querySelector("#collectionsList");
  const query = (document.querySelector("#collectionSearch")?.value || "").toLowerCase();

  const items = state.collections
    .slice()
    .sort((a, b) => (a.order ?? 999) - (b.order ?? 999))
    .filter((collection) => {
      const haystack = `${collection.title} ${collection.theme} ${collection.slug}`.toLowerCase();
      return !query || haystack.includes(query);
    });

  listNode.innerHTML = items
    .map(
      (collection) => `
        <button class="item-card ${collection.id === currentCollectionId ? "is-active" : ""}" type="button" data-collection-id="${escapeHtml(collection.id)}">
          <strong>${escapeHtml(collection.title)}</strong>
          <span class="item-card__meta">${escapeHtml(collection.season || "")} · ${escapeHtml(collection.yearStart || "")}</span>
        </button>
      `
    )
    .join("");

  listNode.querySelectorAll("[data-collection-id]").forEach((button) => {
    button.addEventListener("click", () => {
      currentCollectionId = button.dataset.collectionId;
      renderCollections();
    });
  });

  populateCollectionForm();
}

function populateCollectionForm() {
  const collection = getCollectionById(currentCollectionId);
  const form = document.querySelector("#collectionForm");
  const titleNode = document.querySelector("#collectionEditorTitle");
  if (!form) return;
  if (!collection) {
    form.reset();
    titleNode.textContent = "Выберите коллекцию";
    return;
  }

  titleNode.textContent = collection.title || "Коллекция";
  form.elements.id.value = collection.id || "";
  form.elements.title.value = collection.title || "";
  form.elements.slug.value = collection.slug || "";
  form.elements.season.value = collection.season || "";
  form.elements.theme.value = collection.theme || "";
  form.elements.period.value = collection.period || "";
  form.elements.yearStart.value = collection.yearStart ?? "";
  form.elements.yearEnd.value = collection.yearEnd ?? "";
  form.elements.featured.checked = Boolean(collection.featured);
  form.elements.order.value = collection.order ?? "";
  form.elements.shortDescription.value = collection.shortDescription || "";
  form.elements.fullDescription.value = collection.fullDescription || "";
  form.elements.sourceLinks.value = (collection.sourceLinks || []).join("\n");
}

function syncCollectionFormToState() {
  const form = document.querySelector("#collectionForm");
  const collection = getCollectionById(currentCollectionId);
  if (!form || !collection) return;

  collection.id = form.elements.id.value.trim();
  collection.title = form.elements.title.value.trim();
  collection.slug = form.elements.slug.value.trim();
  collection.season = form.elements.season.value.trim();
  collection.theme = form.elements.theme.value.trim();
  collection.period = form.elements.period.value.trim();
  collection.yearStart = form.elements.yearStart.value ? Number(form.elements.yearStart.value) : null;
  collection.yearEnd = form.elements.yearEnd.value ? Number(form.elements.yearEnd.value) : null;
  collection.featured = form.elements.featured.checked;
  collection.order = form.elements.order.value ? Number(form.elements.order.value) : null;
  collection.shortDescription = form.elements.shortDescription.value.trim();
  collection.fullDescription = form.elements.fullDescription.value.trim();
  collection.sourceLinks = form.elements.sourceLinks.value
    .split("\n")
    .map((item) => item.trim())
    .filter(Boolean);
}

function renderContacts() {
  const node = document.querySelector("#contactsEditor");
  if (!node) return;
  node.innerHTML = state.contacts
    .map(
      (contact, index) => `
        <div class="contact-row" data-contact-index="${index}">
          <input data-field="label" type="text" value="${escapeHtml(contact.label || "")}" placeholder="Название" />
          <input data-field="type" type="text" value="${escapeHtml(contact.type || "")}" placeholder="Тип" />
          <input data-field="value" type="text" value="${escapeHtml(contact.value || "")}" placeholder="Значение" />
          <input data-field="href" type="text" value="${escapeHtml(contact.href || "")}" placeholder="Ссылка" />
          <label class="checkbox-field"><input data-field="primary" type="checkbox" ${contact.primary ? "checked" : ""} /><span>primary</span></label>
        </div>
      `
    )
    .join("");

  node.querySelectorAll(".contact-row").forEach((row) => {
    const index = Number(row.dataset.contactIndex);
    row.querySelectorAll("[data-field]").forEach((input) => {
      input.addEventListener("input", () => syncContactRow(index, row));
      input.addEventListener("change", () => syncContactRow(index, row));
    });
  });
}

function syncContactRow(index, row) {
  const contact = state.contacts[index];
  if (!contact) return;
  row.querySelectorAll("[data-field]").forEach((input) => {
    const field = input.dataset.field;
    if (field === "primary") {
      contact[field] = input.checked;
    } else {
      contact[field] = input.value.trim();
    }
  });
}

function renderSettings() {
  const artistForm = document.querySelector("#artistForm");
  const workshopForm = document.querySelector("#workshopForm");
  const heroForm = document.querySelector("#heroForm");
  if (artistForm && state.artist) {
    artistForm.elements.fullName.value = state.artist.fullName || "";
    artistForm.elements.shortName.value = state.artist.shortName || "";
    artistForm.elements.city.value = state.artist.city || "";
    artistForm.elements.title.value = state.artist.title || "";
    artistForm.elements.shortBio.value = state.artist.shortBio || "";
    artistForm.elements.fullBio.value = state.artist.fullBio || "";
  }
  if (workshopForm && state.workshop) {
    workshopForm.elements.name.value = state.workshop.name || "";
    workshopForm.elements.city.value = state.workshop.city || "";
    workshopForm.elements.descriptionShort.value = state.workshop.descriptionShort || "";
    workshopForm.elements.descriptionFull.value = state.workshop.descriptionFull || "";
    workshopForm.elements.processText.value = state.workshop.processText || "";
  }
  if (heroForm && state.siteSections?.home?.hero) {
    const hero = state.siteSections.home.hero;
    heroForm.elements.kicker.value = hero.kicker || "";
    heroForm.elements.title.value = hero.title || "";
    heroForm.elements.text.value = hero.text || "";
    heroForm.elements.supportText.value = hero.supportText || "";
  }
}

function syncSettingsFormsToState() {
  const artistForm = document.querySelector("#artistForm");
  const workshopForm = document.querySelector("#workshopForm");
  const heroForm = document.querySelector("#heroForm");

  Object.assign(state.artist, {
    fullName: artistForm.elements.fullName.value.trim(),
    shortName: artistForm.elements.shortName.value.trim(),
    city: artistForm.elements.city.value.trim(),
    title: artistForm.elements.title.value.trim(),
    shortBio: artistForm.elements.shortBio.value.trim(),
    fullBio: artistForm.elements.fullBio.value.trim(),
  });

  Object.assign(state.workshop, {
    name: workshopForm.elements.name.value.trim(),
    city: workshopForm.elements.city.value.trim(),
    descriptionShort: workshopForm.elements.descriptionShort.value.trim(),
    descriptionFull: workshopForm.elements.descriptionFull.value.trim(),
    processText: workshopForm.elements.processText.value.trim(),
  });

  Object.assign(state.siteSections.home.hero, {
    kicker: heroForm.elements.kicker.value.trim(),
    title: heroForm.elements.title.value.trim(),
    text: heroForm.elements.text.value.trim(),
    supportText: heroForm.elements.supportText.value.trim(),
  });
}

async function saveSection(section, data) {
  await apiPostJson({
    action: "save",
    section,
    data,
  });
}

async function saveWorks() {
  syncWorkFormToState();
  syncCollectionWorkIds();
  setStatus("Сохранение работ...");
  await saveSection("works", state.works);
  await saveSection("collections", state.collections);
  renderAll();
  setStatus("Работы сохранены");
}

async function deleteCurrentWork() {
  const work = getWorkById(currentWorkId);
  if (!work) {
    return;
  }
  const ok = window.confirm(
    `Удалить работу «${work.title}»? Это действие уберет ее из каталога и коллекции. Изображения останутся на сервере, их можно удалить отдельно.`
  );
  if (!ok) {
    return;
  }

  state.works = state.works.filter((item) => item.id !== currentWorkId);
  currentWorkId = state.works[0]?.id || null;
  syncCollectionWorkIds();
  renderWorks();
  renderCollections();
  renderDashboard();

  setStatus("Удаление работы...");
  await saveSection("works", state.works);
  await saveSection("collections", state.collections);
  setStatus("Работа удалена");
}

async function saveCollections() {
  syncCollectionFormToState();
  setStatus("Сохранение коллекций...");
  await saveSection("collections", state.collections);
  renderAll();
  setStatus("Коллекции сохранены");
}

async function saveContacts() {
  setStatus("Сохранение контактов...");
  await saveSection("contacts", state.contacts);
  setStatus("Контакты сохранены");
}

async function saveSettings() {
  syncSettingsFormsToState();
  setStatus("Сохранение настроек...");
  await saveSection("artist", state.artist);
  await saveSection("workshop", state.workshop);
  await saveSection("siteSections", state.siteSections);
  setStatus("Настройки сохранены");
}

function createEmptyWork() {
  const timestamp = Date.now();
  return {
    id: `work-${timestamp}`,
    title: "Новая работа",
    slug: `novaya-rabota-${timestamp}`,
    artistId: "sergey-svider",
    collectionId: state.collections[0]?.id || "",
    year: new Date().getFullYear(),
    technique: "сухая игла",
    materials: ["пластик", "бумага"],
    width: null,
    height: null,
    dimensionsText: "",
    edition: "",
    status: "unknown",
    price: null,
    priceOnRequest: true,
    shortDescription: "",
    fullDescription: "",
    imageIds: [],
    detailImageIds: [],
    tags: [],
    featured: false,
    order: (state.works.at(-1)?.order || 0) + 1,
    sourceLinks: [],
  };
}

function createEmptyCollection() {
  const timestamp = Date.now();
  return {
    id: `collection-${timestamp}`,
    title: "Новая коллекция",
    slug: `novaya-kollekciya-${timestamp}`,
    coverImageId: "",
    shortDescription: "",
    fullDescription: "",
    theme: "",
    season: "сезон",
    period: "",
    yearStart: new Date().getFullYear(),
    yearEnd: new Date().getFullYear(),
    workIds: [],
    featured: false,
    order: (state.collections.at(-1)?.order || 0) + 1,
    sourceLinks: [],
  };
}

async function uploadWorkImage() {
  const work = getWorkById(currentWorkId);
  if (!work) {
    setStatus("Сначала выберите работу", true);
    return;
  }
  const fileInput = document.querySelector("#workImageFile");
  const file = fileInput.files?.[0];
  if (!file) {
    setStatus("Выберите файл изображения", true);
    return;
  }
  const formData = new FormData();
  formData.append("file", file);
  formData.append("entityType", "work");
  formData.append("entityId", work.id);
  formData.append("title", document.querySelector("#workImageTitle").value.trim() || work.title);
  formData.append("alt", document.querySelector("#workImageAlt").value.trim() || `Гравюра «${work.title}»`);

  setStatus("Загрузка изображения...");
  const result = await apiUpload(formData);
  state.media = result.data.media;
  state.works = result.data.works;
  renderWorks();
  fileInput.value = "";
  document.querySelector("#workImageTitle").value = "";
  document.querySelector("#workImageAlt").value = "";
  setStatus("Изображение загружено");
}

function bindTabs() {
  const tabs = document.querySelectorAll("[data-tab]");
  tabs.forEach((button) => {
    button.addEventListener("click", () => {
      const tab = button.dataset.tab;
      document.querySelectorAll("[data-tab]").forEach((item) => item.classList.toggle("is-active", item === button));
      document.querySelectorAll("[data-panel]").forEach((panel) => panel.classList.toggle("is-active", panel.dataset.panel === tab));
      document.querySelector("#pageTitle").textContent = button.textContent.trim();
    });
  });
}

function bindActions() {
  document.querySelector("#reloadData").addEventListener("click", loadBootstrap);
  document.querySelector("#workSearch").addEventListener("input", renderWorks);
  document.querySelector("#collectionSearch").addEventListener("input", renderCollections);
  document.querySelector("#workForm").addEventListener("input", syncWorkFormToState);
  document.querySelector("#collectionForm").addEventListener("input", syncCollectionFormToState);
  document.querySelector("#saveWorks").addEventListener("click", () => saveWorks().catch(handleError));
  document.querySelector("#saveCollections").addEventListener("click", () => saveCollections().catch(handleError));
  document.querySelector("#saveContacts").addEventListener("click", () => saveContacts().catch(handleError));
  document.querySelector("#saveSettings").addEventListener("click", () => saveSettings().catch(handleError));
  document.querySelector("#uploadWorkImage").addEventListener("click", () => uploadWorkImage().catch(handleError));

  document.querySelector("#addWork").addEventListener("click", () => {
    const work = createEmptyWork();
    state.works.unshift(work);
    currentWorkId = work.id;
    renderWorks();
  });

  document.querySelector("#deleteWork").addEventListener("click", () => {
    deleteCurrentWork().catch(handleError);
  });

  document.querySelector("#addCollection").addEventListener("click", () => {
    const collection = createEmptyCollection();
    state.collections.push(collection);
    currentCollectionId = collection.id;
    renderCollections();
    renderWorks();
  });

  document.querySelector("#deleteCollection").addEventListener("click", () => {
    if (!currentCollectionId) return;
    state.collections = state.collections.filter((item) => item.id !== currentCollectionId);
    if (state.collections.length) {
      const fallbackId = state.collections[0].id;
      state.works.forEach((work) => {
        if (work.collectionId === currentCollectionId) {
          work.collectionId = fallbackId;
        }
      });
    }
    currentCollectionId = state.collections[0]?.id || null;
    syncCollectionWorkIds();
    renderCollections();
    renderWorks();
  });

  document.querySelector("#addContact").addEventListener("click", () => {
    state.contacts.push({
      id: `contact-${Date.now()}`,
      type: "phone",
      label: "Новый контакт",
      value: "",
      href: "",
      primary: false,
    });
    renderContacts();
  });
}

function handleError(error) {
  console.error(error);
  setStatus(error.message || "Произошла ошибка", true);
}

document.addEventListener("DOMContentLoaded", () => {
  bindTabs();
  bindActions();
  loadBootstrap().catch(handleError);
});
