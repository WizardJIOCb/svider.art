const contentFiles = {
  artist: "./content/artist.json",
  workshop: "./content/workshop.json",
  collections: "./content/collections.json",
  works: "./content/works.json",
  exhibitions: "./content/exhibitions.json",
  contacts: "./content/contacts.json",
  media: "./content/media.json",
  news: "./content/news.json",
  siteSections: "./content/site-sections.json",
  browseSections: "./content/browse-sections.json",
  collectionPage: "./content/collection-page.json",
  workPage: "./content/work-page.json",
};

const NEWS_PAGE_SIZE = 3;
let currentNewsPage = 1;

async function loadJson(filePath) {
  const response = await fetch(filePath, { cache: "no-store" });
  if (!response.ok) {
    throw new Error(`Failed to load ${filePath}`);
  }
  return response.json();
}

function makeMediaMap(media) {
  return new Map(media.map((item) => [item.id, item]));
}

function normalizeIdList(value) {
  if (Array.isArray(value)) {
    return value.map((id) => String(id || "").trim()).filter(Boolean);
  }
  if (typeof value === "string" && value.trim()) {
    return [value.trim()];
  }
  return [];
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function byOrder(a, b) {
  return (a.order ?? 999) - (b.order ?? 999);
}

function slugifyRoute(prefix, slug) {
  return `#${prefix}/${slug}`;
}

function formatYearRange(start, end) {
  if (start && end && start !== end) {
    return `${start}-${end}`;
  }
  return String(start || end || "");
}

function formatStatus(status, statusLabels) {
  return statusLabels?.[status] || status || "";
}

function formatCount(value, one, few, many) {
  const mod10 = value % 10;
  const mod100 = value % 100;

  if (mod10 === 1 && mod100 !== 11) {
    return `${value} ${one}`;
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return `${value} ${few}`;
  }
  return `${value} ${many}`;
}

function formatRussianDate(value) {
  if (!value) {
    return "";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("ru-RU", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date);
}

function updateDocumentMeta(title, description) {
  document.title = title;
  const meta = document.querySelector('meta[name="description"]');
  if (meta && description) {
    meta.setAttribute("content", description);
  }
}

function getPrimaryContact(contacts) {
  return contacts.find((item) => item.primary && item.href) || contacts[0];
}

function pickFeaturedWork(works) {
  const candidates = works.filter((item) => item.imageIds?.length);
  if (!candidates.length) {
    return null;
  }

  const randomIndex = Math.floor(Math.random() * candidates.length);
  return candidates[randomIndex];
}

function pickFeaturedCollection(collections) {
  return collections
    .slice()
    .sort((a, b) => {
      if (a.featured && !b.featured) {
        return -1;
      }
      if (!a.featured && b.featured) {
        return 1;
      }
      return byOrder(a, b);
    })
    .find((item) => item.coverImageId);
}

function sortWorksForCatalog(works) {
  return works.slice().sort((a, b) => {
    const byYear = (b.year ?? 0) - (a.year ?? 0);
    if (byYear) {
      return byYear;
    }
    const byManualOrder = byOrder(a, b);
    if (byManualOrder) {
      return byManualOrder;
    }
    return String(a.title || "").localeCompare(String(b.title || ""), "ru");
  });
}

function setupMobileMenu() {
  const toggle = document.querySelector("#menuToggle");
  const nav = document.querySelector("#siteNav");

  if (!toggle || !nav) {
    return;
  }

  const closeMenu = () => {
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Открыть меню");
    nav.classList.remove("is-open");
  };

  const openMenu = () => {
    toggle.setAttribute("aria-expanded", "true");
    toggle.setAttribute("aria-label", "Закрыть меню");
    nav.classList.add("is-open");
  };

  toggle.addEventListener("click", () => {
    const expanded = toggle.getAttribute("aria-expanded") === "true";
    if (expanded) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      closeMenu();
    });
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 780) {
      closeMenu();
    }
  });
}

function setupLightbox() {
  const lightbox = document.querySelector("#lightbox");
  const lightboxImage = document.querySelector("#lightboxImage");
  const lightboxClose = document.querySelector("#lightboxClose");
  const lightboxBackdrop = document.querySelector("#lightboxBackdrop");

  if (!lightbox || !lightboxImage || !lightboxClose || !lightboxBackdrop) {
    return;
  }

  const closeLightbox = () => {
    lightbox.hidden = true;
    lightbox.setAttribute("aria-hidden", "true");
    lightboxImage.removeAttribute("src");
    lightboxImage.removeAttribute("alt");
    document.body.classList.remove("has-lightbox");
  };

  const openLightbox = (src, alt) => {
    lightboxImage.src = src;
    lightboxImage.alt = alt || "";
    lightbox.hidden = false;
    lightbox.setAttribute("aria-hidden", "false");
    document.body.classList.add("has-lightbox");
  };

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-lightbox-src]");
    if (!trigger) {
      return;
    }

    event.preventDefault();
    openLightbox(trigger.getAttribute("data-lightbox-src"), trigger.getAttribute("data-lightbox-alt") || "");
  });

  lightboxClose.addEventListener("click", closeLightbox);
  lightboxBackdrop.addEventListener("click", closeLightbox);

  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !lightbox.hidden) {
      closeLightbox();
    }
  });
}

function setupRequestModal() {
  const modal = document.querySelector("#requestModal");
  const backdrop = document.querySelector("#requestModalBackdrop");
  const closeButton = document.querySelector("#requestModalClose");
  const kickerNode = document.querySelector("#requestModalKicker");
  const titleNode = document.querySelector("#requestModalTitle");
  const leadNode = document.querySelector("#requestModalLead");
  const stepsNode = document.querySelector("#requestModalSteps");
  const contactsNode = document.querySelector("#requestModalContacts");
  const noteNode = document.querySelector("#requestModalNote");
  const sourceTitle = document.querySelector("#orderTitle");
  const sourceText = document.querySelector("#orderText");
  const sourceSteps = document.querySelector("#orderSteps");
  const sourceContacts = document.querySelector("#orderContacts");
  const sourceNote = document.querySelector("#orderNote");

  if (
    !modal ||
    !backdrop ||
    !closeButton ||
    !kickerNode ||
    !titleNode ||
    !leadNode ||
    !stepsNode ||
    !contactsNode ||
    !noteNode ||
    !sourceTitle ||
    !sourceText ||
    !sourceSteps ||
    !sourceContacts ||
    !sourceNote
  ) {
    return;
  }

  const closeModal = () => {
    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("has-modal");
  };

  const openModal = (workTitle) => {
    kickerNode.textContent = "Запрос";
    titleNode.textContent = sourceTitle.textContent || "Готовая работа или индивидуальный запрос";
    leadNode.textContent = workTitle
      ? `Вы можете обсудить работу «${workTitle}» или задать вопрос мастерской напрямую.`
      : sourceText.textContent || "";
    stepsNode.innerHTML = sourceSteps.innerHTML;
    contactsNode.innerHTML = sourceContacts.innerHTML;
    noteNode.textContent = sourceNote.textContent || "";
    const form = document.querySelector("#requestModalFormShell [data-request-form]");
    if (form) {
      form.dataset.source = "work-detail";
      form.elements.sourceLabel.value = "Страница работы";
      form.elements.requestType.value = "ready_work";
      form.elements.workTitle.value = workTitle || "";
      const statusNode = form.querySelector("[data-request-status]");
      if (statusNode) {
        statusNode.textContent = "";
        statusNode.classList.remove("is-error", "is-success");
      }
    }
    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("has-modal");
  };

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-open-request-modal]");
    if (!trigger) {
      return;
    }

    event.preventDefault();
    openModal(trigger.getAttribute("data-request-title") || "");
  });

  closeButton.addEventListener("click", closeModal);
  backdrop.addEventListener("click", closeModal);

  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeModal();
    }
  });
}

function mountRequestForm(target, defaults = {}) {
  const template = document.querySelector("#requestFormTemplate");
  if (!template || !target) {
    return null;
  }

  target.innerHTML = "";
  const fragment = template.content.cloneNode(true);
  const form = fragment.querySelector("[data-request-form]");
  if (!form) {
    return null;
  }

  form.dataset.source = defaults.source || "site";
  if (defaults.workTitle) {
    form.elements.workTitle.value = defaults.workTitle;
  }
  if (defaults.requestType) {
    form.elements.requestType.value = defaults.requestType;
  }
  form.elements.sourceLabel.value = defaults.sourceLabel || "Сайт мастерской";
  target.appendChild(fragment);
  return target.querySelector("[data-request-form]");
}

function setupRequestForms() {
  const homepageShell = document.querySelector("#requestFormShell");
  const modalShell = document.querySelector("#requestModalFormShell");

  mountRequestForm(homepageShell, {
    source: "homepage",
    sourceLabel: "Главная страница",
  });

  mountRequestForm(modalShell, {
    source: "work-detail",
    sourceLabel: "Страница работы",
  });

  document.addEventListener("submit", async (event) => {
    const form = event.target.closest("[data-request-form]");
    if (!form) {
      return;
    }

    event.preventDefault();
    const statusNode = form.querySelector("[data-request-status]");
    const submitButton = form.querySelector('button[type="submit"]');
    const payload = {
      name: form.elements.name.value.trim(),
      contact: form.elements.contact.value.trim(),
      requestType: form.elements.requestType.value,
      workTitle: form.elements.workTitle.value.trim(),
      size: form.elements.size.value.trim(),
      city: form.elements.city.value.trim(),
      preferredChannel: form.elements.preferredChannel.value.trim(),
      message: form.elements.message.value.trim(),
      source: form.dataset.source || "site",
    };

    if (statusNode) {
      statusNode.textContent = "Отправка заявки...";
      statusNode.classList.remove("is-error", "is-success");
    }
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const response = await fetch("./request.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || "Не удалось отправить заявку");
      }

      form.reset();
      form.elements.sourceLabel.value = payload.source === "work-detail" ? "Страница работы" : "Главная страница";
      form.dataset.source = payload.source;
      if (payload.workTitle) {
        form.elements.workTitle.value = payload.workTitle;
      }
      if (payload.requestType) {
        form.elements.requestType.value = payload.requestType;
      }
      if (statusNode) {
        statusNode.textContent = data.emailSent
          ? "Заявка отправлена. Мы также отправили уведомление на почту мастерской."
          : "Заявка отправлена. Мы получили её в мастерской.";
        statusNode.classList.add("is-success");
      }
    } catch (error) {
      if (statusNode) {
        statusNode.textContent = error.message || "Не удалось отправить заявку";
        statusNode.classList.add("is-error");
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
}

function renderHero(artist, mediaMap, siteSections, collections, works) {
  const hero = siteSections?.home?.hero || {};
  const visual = document.querySelector("#heroVisual");
  const collectionMap = new Map(collections.map((item) => [item.id, item]));
  const featuredWork = pickFeaturedWork(works);
  const featuredCollection = pickFeaturedCollection(collections);
  const heroMedia =
    mediaMap.get(featuredWork?.imageIds?.[0]) ||
    mediaMap.get(featuredCollection?.coverImageId) ||
    mediaMap.get(artist.galleryImageIds?.[1]) ||
    mediaMap.get(artist.portraitImageId);
  const currentCollection = featuredWork ? collectionMap.get(featuredWork.collectionId) : featuredCollection;
  const heroMeta = [
    formatCount(collections.length, "коллекция", "коллекции", "коллекций"),
    formatCount(works.length, "работа", "работы", "работ"),
    `${collections.filter((item) => item.featured).length || Math.min(collections.length, 4)} ключевых цикла`,
  ];
  const artworkMeta = [
    currentCollection?.title || null,
    featuredWork?.year ? String(featuredWork.year) : null,
    featuredWork?.dimensionsText || null,
  ].filter(Boolean);
  const artworkTitle = featuredWork?.title || currentCollection?.title || artist.fullName;
  const artworkCaption = featuredWork
    ? "Гравюра воспринимается здесь как подлинный лист: с фактурой бумаги, характером отпечатка и вниманием к детали."
    : "Мастерская Сергея Свидера. Коллекции и сезонные серии раскрываются как единый художественный архив.";

  document.querySelector("#heroKicker").textContent =
    hero.kicker || "Авторская печатная графика";
  document.querySelector("#heroTitle").textContent =
    hero.title || "Гравюра как пространство линии, времени и ручного отпечатка";
  document.querySelector("#heroText").innerHTML =
    hero.text || escapeHtml(artist.fullBio || artist.shortBio || "");
  document.querySelector("#heroSupport").textContent =
    hero.supportText || artist.artisticStatement || "";
  document.querySelector("#heroPoints").innerHTML = (hero.points || [])
    .map((point) => `<span>${escapeHtml(point)}</span>`)
    .join("");
  document.querySelector("#heroPrimaryCta").textContent =
    hero.primaryCta || "Смотреть каталог";
  document.querySelector("#heroSecondaryCta").textContent =
    hero.secondaryCta || "Заказать гравюру";
  document.querySelector("#heroMeta").innerHTML = heroMeta
    .map((item) => `<span>${escapeHtml(item)}</span>`)
    .join("");

  visual.innerHTML = `
    <figure class="hero-figure">
      <div class="hero-figure__topline">
        <span class="hero-figure__label">Лист из каталога</span>
        ${artworkMeta.length ? `<span class="hero-figure__meta">${escapeHtml(artworkMeta.join(" • "))}</span>` : ""}
      </div>
      <div class="hero-figure__artwork">
        ${
          heroMedia
            ? `<img src="${heroMedia.src}" alt="${escapeHtml(heroMedia.alt || artworkTitle)}" />`
            : ""
        }
      </div>
      <figcaption class="hero-figure__caption">
        <strong>${escapeHtml(artworkTitle)}</strong>
        <span>${escapeHtml(artworkCaption)}</span>
        <div class="hero-figure__footer">
          <span>${escapeHtml(artist.shortName)}</span>
          <span>${escapeHtml(artist.city || "")}</span>
        </div>
      </figcaption>
    </figure>
  `;
}

function renderIntro(artist, workshop, exhibitions) {
  document.querySelector("#artistSummary").innerHTML = `
    <h3>${escapeHtml(artist.fullName)}</h3>
    <p>${escapeHtml(artist.shortBio)}</p>
    <p class="intro-note">${escapeHtml(artist.artisticStatement)}</p>
  `;

  document.querySelector("#workshopSummary").innerHTML = `
    <h3>${escapeHtml(workshop.name)}</h3>
    <p>${escapeHtml(workshop.descriptionShort)}</p>
    <p>${escapeHtml(workshop.processText)}</p>
  `;

  const exhibitionText = exhibitions
    .slice()
    .sort((a, b) => String(b.dateStart).localeCompare(String(a.dateStart)))
    .slice(0, 2)
    .map(
      (item) =>
        `<p><strong>${escapeHtml(item.title)}</strong><br />${escapeHtml(item.city)}, ${escapeHtml(item.venue)}</p>`,
    )
    .join("");

  document.querySelector("#exhibitionSummary").innerHTML =
    exhibitionText ||
    `<p class="empty-note">Выставочный архив будет дополняться по мере уточнения материалов.</p>`;
}

function renderSectionCopy(siteSections, browseSections, works) {
  const home = siteSections?.home || {};
  const intro = home.intro || {};
  const collections = browseSections?.collections?.section || {};
  const catalog = browseSections?.catalog?.section || {};
  const order = siteSections?.order || {};
  const workshop = siteSections?.workshop || {};
  const contacts = siteSections?.contacts || {};
  const featuredCount = works
    .slice()
    .sort((a, b) => {
      if (a.featured && !b.featured) {
        return -1;
      }
      if (!a.featured && b.featured) {
        return 1;
      }
      return byOrder(a, b);
    })
    .slice(0, 8).length;

  document.querySelector("#introArtistKicker").textContent =
    intro.artistKicker || "О мастере";
  document.querySelector("#introWorkshopKicker").textContent =
    intro.workshopKicker || "О мастерской";
  document.querySelector("#introExhibitionsKicker").textContent =
    intro.exhibitionsKicker || "Выставки";

  document.querySelector("#collectionsKicker").textContent =
    collections.kicker || "Коллекции";
  document.querySelector("#collectionsTitle").textContent =
    collections.title || "Сезоны, серии и авторские циклы";
  document.querySelector("#collectionsText").textContent = collections.text || "";

  document.querySelector("#catalogKicker").textContent =
    catalog.kicker || "Каталог";
  document.querySelector("#catalogTitle").textContent =
    catalog.title || "Избранные работы";
  document.querySelector("#catalogText").textContent = catalog.text || "";
  document.querySelector("#catalogStat").textContent = (
    browseSections?.catalog?.statTemplate || "{count} работ в текущей базе"
  ).replace("{count}", String(featuredCount));

  document.querySelector("#orderKicker").textContent = order.kicker || "Заказать";
  document.querySelector("#orderTitle").textContent =
    order.title || "Готовая работа или индивидуальный запрос";
  document.querySelector("#orderText").textContent = order.text || "";
  document.querySelector("#orderSteps").innerHTML = (order.steps || [])
    .map(
      (step, index) => `
        <div>
          <span class="order-step__index">${index + 1}</span>
          <span>${escapeHtml(step)}</span>
        </div>
      `,
    )
    .join("");
  document.querySelector("#orderNote").textContent = order.note || "";

  document.querySelector("#workshopKicker").textContent =
    workshop.kicker || "О мастерской";
  document.querySelector("#workshopTitle").textContent =
    workshop.pageTitle || "О мастерской";

  document.querySelector("#contactsKicker").textContent =
    contacts.kicker || "Контакты";
  document.querySelector("#contactsTitle").textContent =
    contacts.title || "Связаться с мастерской";
  document.querySelector("#contactsText").textContent = contacts.text || "";
}

function renderNews(newsData, mediaMap) {
  const sectionNode = document.querySelector("#news");
  const grid = document.querySelector("#newsGrid");
  const paginationNode = document.querySelector("#newsPagination");
  if (!sectionNode || !grid || !paginationNode) {
    return;
  }

  const section = newsData?.section || {};
  const items = (newsData?.items || [])
    .filter((item) => item.published !== false)
    .slice()
    .sort((a, b) => {
      const byDate = String(b.publishedAt || "").localeCompare(String(a.publishedAt || ""));
      if (byDate) {
        return byDate;
      }
      if (a.featured && !b.featured) {
        return -1;
      }
      if (!a.featured && b.featured) {
        return 1;
      }
      return byOrder(a, b);
    });

  document.querySelector("#newsKicker").textContent = section.kicker || "Новости";
  document.querySelector("#newsTitle").textContent = section.title || "Новости мастерской";
  document.querySelector("#newsText").textContent =
    section.text || "Выставки, новые листы, обновления коллекций и важные события мастерской.";

  if (!items.length) {
    sectionNode.hidden = true;
    paginationNode.hidden = true;
    paginationNode.innerHTML = "";
    return;
  }

  const totalPages = Math.max(1, Math.ceil(items.length / NEWS_PAGE_SIZE));
  currentNewsPage = Math.min(Math.max(1, currentNewsPage), totalPages);
  const start = (currentNewsPage - 1) * NEWS_PAGE_SIZE;
  const pagedItems = items.slice(start, start + NEWS_PAGE_SIZE);

  sectionNode.hidden = false;
  grid.innerHTML = pagedItems
    .map((item) => {
      const images = normalizeIdList(item.imageIds).map((id) => mediaMap.get(id)).filter(Boolean);
      const gallery = images.length
        ? `
          <div class="news-card__gallery ${images.length === 1 ? "news-card__gallery--single" : ""}">
            ${images
              .map(
                (image) => `
                  <figure class="news-card__image">
                    <img
                      src="${image.src}"
                      alt="${escapeHtml(image.alt || image.title || item.title)}"
                      loading="lazy"
                      data-lightbox-src="${escapeHtml(image.src)}"
                      data-lightbox-alt="${escapeHtml(image.alt || image.title || item.title)}"
                    />
                  </figure>
                `
              )
              .join("")}
          </div>
        `
        : "";

      return `
        <article class="news-card">
          <div class="news-card__top">
            <span class="news-card__date">${escapeHtml(formatRussianDate(item.publishedAt))}</span>
            ${item.featured ? `<span class="news-card__badge">Важно</span>` : ""}
          </div>
          <h3>${escapeHtml(item.title)}</h3>
          ${item.summary ? `<p class="news-card__summary">${escapeHtml(item.summary)}</p>` : ""}
          ${gallery}
          <div class="news-card__body">
            ${item.bodyHtml || ""}
          </div>
        </article>
      `;
    })
    .join("");

  if (totalPages <= 1) {
    paginationNode.hidden = true;
    paginationNode.innerHTML = "";
    return;
  }

  paginationNode.hidden = false;
  paginationNode.innerHTML = `
    <button class="news-pagination__button" type="button" data-news-page="${currentNewsPage - 1}" ${currentNewsPage <= 1 ? "disabled" : ""}>Назад</button>
    <span class="news-pagination__status">Страница ${currentNewsPage} из ${totalPages}</span>
    <button class="news-pagination__button" type="button" data-news-page="${currentNewsPage + 1}" ${currentNewsPage >= totalPages ? "disabled" : ""}>Вперёд</button>
  `;

  paginationNode.querySelectorAll("[data-news-page]").forEach((button) => {
    button.addEventListener("click", () => {
      const nextPage = Number(button.getAttribute("data-news-page"));
      if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage > totalPages || nextPage === currentNewsPage) {
        return;
      }
      currentNewsPage = nextPage;
      renderNews(newsData, mediaMap);
    });
  });
}

function renderFeaturedCollections(collections, mediaMap) {
  const rail = document.querySelector("#featuredCollectionsRail");
  if (!rail) {
    return;
  }

  const featured = collections
    .slice()
    .filter((collection) => (collection.workIds?.length || 0) > 0)
    .sort((a, b) => {
      if (a.featured && !b.featured) {
        return -1;
      }
      if (!a.featured && b.featured) {
        return 1;
      }
      return (b.workIds?.length || 0) - (a.workIds?.length || 0) || byOrder(a, b);
    })
    .slice(0, 2);

  rail.innerHTML = featured
    .map((collection, index) => {
      const cover = mediaMap.get(collection.coverImageId);
      return `
        <article class="featured-collection-card">
          <a class="card-link" href="${slugifyRoute("collection", collection.slug)}" aria-label="${escapeHtml(collection.title)}">
            <div class="featured-collection-card__media">
              ${
                cover
                  ? `<img src="${cover.src}" alt="${escapeHtml(cover.alt || collection.title)}" />`
                  : ""
              }
            </div>
            <div class="featured-collection-card__body">
              <span class="featured-collection-card__index">0${index + 1}</span>
              <h3>${escapeHtml(collection.title)}</h3>
              <p>${escapeHtml(collection.shortDescription || "")}</p>
              <div class="meta">
                <span>${escapeHtml(collection.season || "Серия")}</span>
                <span>${formatCount(collection.workIds?.length || 0, "лист", "листа", "листов")}</span>
              </div>
            </div>
          </a>
        </article>
      `;
    })
    .join("");
}

function renderCollections(collections, mediaMap, browseSections) {
  const grid = document.querySelector("#collectionGrid");
  const metaLabel = browseSections?.collections?.cardMetaLabel || "Коллекция";
  const workCountLabel = browseSections?.collections?.workCountLabel || "работ";

  grid.innerHTML = collections
    .slice()
    .filter((collection) => (collection.workIds?.length || 0) > 0)
    .sort(byOrder)
    .slice(0, 6)
    .map((collection) => {
      const cover = mediaMap.get(collection.coverImageId);
      return `
        <article class="collection-card">
          <a class="card-link" href="${slugifyRoute("collection", collection.slug)}" aria-label="${escapeHtml(collection.title)}">
            <div class="collection-card__cover">
              ${
                cover
                  ? `<img src="${cover.src}" alt="${escapeHtml(cover.alt || collection.title)}" />`
                  : ""
              }
            </div>
            <div class="collection-card__body">
              <p class="section-kicker">${escapeHtml(metaLabel)}</p>
              <h3>${escapeHtml(collection.title)}</h3>
              <p>${escapeHtml(collection.shortDescription || "")}</p>
              <div class="meta">
                <span>${escapeHtml(collection.season || "Серия")}</span>
                <span>${collection.workIds?.length || 0} ${escapeHtml(workCountLabel)}</span>
                ${
                  collection.yearStart
                    ? `<span>${escapeHtml(
                        formatYearRange(collection.yearStart, collection.yearEnd || collection.yearStart),
                      )}</span>`
                    : ""
                }
              </div>
              <span class="card-arrow" aria-hidden="true">Смотреть</span>
            </div>
          </a>
        </article>
      `;
    })
    .join("");
}

function renderWorks(works, collections, mediaMap, browseSections) {
  const collectionMap = new Map(collections.map((item) => [item.id, item]));
  const grid = document.querySelector("#workGrid");
  const fallbackLabel = browseSections?.catalog?.cardFallbackLabel || "Работа";
  const featured = works
    .slice()
    .sort((a, b) => {
      if (a.featured && !b.featured) {
        return -1;
      }
      if (!a.featured && b.featured) {
        return 1;
      }
      return byOrder(a, b);
    })
    .slice(0, 8);

  grid.innerHTML = featured
    .map((work) => {
      const image = mediaMap.get(work.imageIds?.[0]);
      const collection = collectionMap.get(work.collectionId);
      return `
        <article class="work-card">
          <a class="card-link" href="${slugifyRoute("work", work.slug)}" aria-label="${escapeHtml(work.title)}">
            <div class="work-card__cover">
              ${
                image
                  ? `<img src="${image.src}" alt="${escapeHtml(image.alt || work.title)}" />`
                  : `<div class="work-card__fallback">${escapeHtml(work.title)}</div>`
              }
            </div>
            <div class="work-card__body">
              <p class="section-kicker">${escapeHtml(collection?.title || fallbackLabel)}</p>
              <h3>${escapeHtml(work.title)}</h3>
              <p>${escapeHtml(work.shortDescription || "")}</p>
              <div class="meta">
                ${work.year ? `<span>${work.year}</span>` : ""}
                ${work.technique ? `<span>${escapeHtml(work.technique)}</span>` : ""}
                ${work.dimensionsText ? `<span>${escapeHtml(work.dimensionsText)}</span>` : ""}
                ${work.featured ? `<span>Выбор мастерской</span>` : ""}
              </div>
              <span class="card-arrow" aria-hidden="true">О работе</span>
            </div>
          </a>
        </article>
      `;
    })
    .join("");
}

function renderCatalogPage(browseSections) {
  const page = browseSections?.catalog?.page || {};
  const filters = browseSections?.catalog?.filters || {};

  return `
    <div class="detail-shell catalog-page">
      <div class="detail-breadcrumbs">
        <a href="#top">Главная</a>
        <span>/</span>
        <span>Каталог</span>
      </div>

      <section class="detail-hero detail-hero--catalog">
        <div class="detail-hero__copy">
          <p class="section-kicker">Каталог</p>
          <h1>${escapeHtml(page.title || "Каталог гравюр")}</h1>
          <p class="detail-hero__text">${escapeHtml(page.lead || "")}</p>
        </div>
        <div class="detail-card detail-card--catalog-intro">
          <p class="section-kicker">О просмотре</p>
          <p>${escapeHtml(page.note || "")}</p>
          <div class="catalog-page__summary" id="catalogPageSummary"></div>
        </div>
      </section>

      <section class="detail-section">
        <div class="catalog-filters">
          <label class="catalog-filter">
            <span>${escapeHtml(filters.searchLabel || "Поиск")}</span>
            <input id="catalogSearch" type="search" placeholder="${escapeHtml(filters.searchPlaceholder || "Название или описание")}" />
          </label>
          <label class="catalog-filter">
            <span>${escapeHtml(filters.collectionLabel || "Коллекция")}</span>
            <select id="catalogCollection"></select>
          </label>
          <label class="catalog-filter">
            <span>${escapeHtml(filters.yearLabel || "Год")}</span>
            <select id="catalogYear"></select>
          </label>
          <label class="catalog-filter">
            <span>${escapeHtml(filters.techniqueLabel || "Техника")}</span>
            <select id="catalogTechnique"></select>
          </label>
          <button class="button button--ghost catalog-filter__reset" id="catalogReset" type="button">${escapeHtml(filters.resetLabel || "Сбросить")}</button>
        </div>
      </section>

      <section class="detail-section">
        <div class="catalog-results__head">
          <p class="section-kicker">Все работы</p>
          <p class="catalog-results__count" id="catalogPageCount"></p>
        </div>
        <div class="catalog-results-grid" id="catalogPageGrid"></div>
      </section>
    </div>
  `;
}

function setupCatalogPage(data) {
  const { works, collections, mediaMap, browseSections } = data;
  const filtersCopy = browseSections?.catalog?.filters || {};
  const collectionMap = new Map(collections.map((item) => [item.id, item]));
  const searchInput = document.querySelector("#catalogSearch");
  const collectionSelect = document.querySelector("#catalogCollection");
  const yearSelect = document.querySelector("#catalogYear");
  const techniqueSelect = document.querySelector("#catalogTechnique");
  const resetButton = document.querySelector("#catalogReset");
  const grid = document.querySelector("#catalogPageGrid");
  const count = document.querySelector("#catalogPageCount");
  const summary = document.querySelector("#catalogPageSummary");

  if (!searchInput || !collectionSelect || !yearSelect || !techniqueSelect || !resetButton || !grid || !count) {
    return;
  }

  const sortedWorks = sortWorksForCatalog(works);
  const years = [...new Set(sortedWorks.map((item) => item.year).filter(Boolean))].sort((a, b) => b - a);
  const techniques = [...new Set(sortedWorks.map((item) => item.technique).filter(Boolean))].sort((a, b) =>
    String(a).localeCompare(String(b), "ru"),
  );
  const collectionsWithWorks = collections
    .filter((collection) => (collection.workIds?.length || 0) > 0)
    .sort((a, b) => String(a.title || "").localeCompare(String(b.title || ""), "ru"));

  collectionSelect.innerHTML = [
    `<option value="">${escapeHtml(filtersCopy.collectionAll || "Все коллекции")}</option>`,
    ...collectionsWithWorks.map(
      (collection) => `<option value="${escapeHtml(collection.id)}">${escapeHtml(collection.title)}</option>`,
    ),
  ].join("");

  yearSelect.innerHTML = [
    `<option value="">${escapeHtml(filtersCopy.yearAll || "Все годы")}</option>`,
    ...years.map((year) => `<option value="${year}">${year}</option>`),
  ].join("");

  techniqueSelect.innerHTML = [
    `<option value="">${escapeHtml(filtersCopy.techniqueAll || "Все техники")}</option>`,
    ...techniques.map((technique) => `<option value="${escapeHtml(technique)}">${escapeHtml(technique)}</option>`),
  ].join("");

  const renderResults = () => {
    const searchValue = searchInput.value.trim().toLowerCase();
    const collectionValue = collectionSelect.value;
    const yearValue = yearSelect.value;
    const techniqueValue = techniqueSelect.value;

    const filtered = sortedWorks.filter((work) => {
      const haystack = [work.title, work.shortDescription, work.fullDescription]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();

      if (searchValue && !haystack.includes(searchValue)) {
        return false;
      }
      if (collectionValue && work.collectionId !== collectionValue) {
        return false;
      }
      if (yearValue && String(work.year || "") !== yearValue) {
        return false;
      }
      if (techniqueValue && String(work.technique || "") !== techniqueValue) {
        return false;
      }
      return true;
    });

    count.textContent = (browseSections?.catalog?.pageStatTemplate || "{count} работ в каталоге").replace(
      "{count}",
      String(filtered.length),
    );

    if (summary) {
      summary.innerHTML = `
        <span>${formatCount(collectionsWithWorks.length, "коллекция", "коллекции", "коллекций")}</span>
        <span>${formatCount(sortedWorks.length, "работа", "работы", "работ")}</span>
        <span>${formatCount(years.length, "год", "года", "лет")} в базе</span>
      `;
    }

    if (!filtered.length) {
      grid.innerHTML = `<p class="empty-note">${escapeHtml(
        browseSections?.catalog?.emptyText || "По выбранным фильтрам работы не найдены.",
      )}</p>`;
      return;
    }

    grid.innerHTML = filtered
      .map((work) => {
        const image = mediaMap.get(work.imageIds?.[0]);
        const collection = collectionMap.get(work.collectionId);
        const materials = (work.materials || []).join(", ");
        return `
          <article class="work-card work-card--catalog-page">
            <a class="card-link" href="${slugifyRoute("work", work.slug)}" aria-label="${escapeHtml(work.title)}">
              <div class="work-card__cover">
                ${
                  image
                    ? `<img src="${image.src}" alt="${escapeHtml(image.alt || work.title)}" />`
                    : `<div class="work-card__fallback">${escapeHtml(work.title)}</div>`
                }
              </div>
              <div class="work-card__body">
                <p class="section-kicker">${escapeHtml(collection?.title || "Работа")}</p>
                <h3>${escapeHtml(work.title)}</h3>
                <p>${escapeHtml(work.shortDescription || "")}</p>
                <div class="meta">
                  ${work.year ? `<span>${work.year}</span>` : ""}
                  ${work.technique ? `<span>${escapeHtml(work.technique)}</span>` : ""}
                  ${work.dimensionsText ? `<span>${escapeHtml(work.dimensionsText)}</span>` : ""}
                </div>
                <dl class="catalog-work__details">
                  ${
                    collection
                      ? `<div><dt>Коллекция</dt><dd>${escapeHtml(collection.title)}</dd></div>`
                      : ""
                  }
                  ${materials ? `<div><dt>Материалы</dt><dd>${escapeHtml(materials)}</dd></div>` : ""}
                  ${work.sourceLinks?.[0] ? `<div><dt>Источник</dt><dd>Каталог / архив</dd></div>` : ""}
                </dl>
                <span class="card-arrow" aria-hidden="true">О работе</span>
              </div>
            </a>
          </article>
        `;
      })
      .join("");
  };

  [searchInput, collectionSelect, yearSelect, techniqueSelect].forEach((element) => {
    const eventName = element.tagName === "INPUT" ? "input" : "change";
    element.addEventListener(eventName, renderResults);
  });

  resetButton.addEventListener("click", () => {
    searchInput.value = "";
    collectionSelect.value = "";
    yearSelect.value = "";
    techniqueSelect.value = "";
    renderResults();
  });

  renderResults();
}

function renderOrder(contacts) {
  const primaryContacts = contacts.slice(0, 4);
  document.querySelector("#orderContacts").innerHTML = primaryContacts
    .map(
      (contact) => `
        <a class="contact-item" href="${escapeHtml(contact.href || "#")}" ${
          contact.href ? "" : 'role="presentation"'
        }>
          <span class="contact-item__label">${escapeHtml(contact.label)}</span>
          <span class="contact-item__value">${escapeHtml(contact.value)}</span>
        </a>
      `,
    )
    .join("");
}

function renderWorkshop(workshop, mediaMap, siteSections) {
  const page = siteSections?.workshop || {};
  const blocks = page.blocks || [];
  const blocksMarkup = blocks
    .map(
      (block) => `
        <section class="text-block">
          <h3>${escapeHtml(block.title)}</h3>
          <p>${escapeHtml(block.text)}</p>
        </section>
      `,
    )
    .join("");

  document.querySelector("#workshopDetail").innerHTML = `
    <p>${escapeHtml(page.lead || workshop.descriptionFull)}</p>
    <p>${escapeHtml(workshop.processText)}</p>
    ${blocksMarkup}
    <div class="meta">
      ${(workshop.techniques || []).map((item) => `<span>${escapeHtml(item)}</span>`).join("")}
    </div>
  `;

  const media = mediaMap.get(workshop.imageIds?.[0] || workshop.imageIds?.[1]);
  document.querySelector("#workshopMedia").innerHTML = media
    ? `
      <figure class="workshop-figure">
        <img src="${media.src}" alt="${escapeHtml(media.alt || workshop.name)}" />
        <figcaption>Пространство мастерской, где пластина, краска и бумага становятся единым художественным листом.</figcaption>
      </figure>
    `
    : "";
}

function renderContacts(contacts) {
  document.querySelector("#contactGrid").innerHTML = contacts
    .map(
      (contact) => `
        <a class="contact-item" href="${escapeHtml(contact.href || "#")}" ${
          contact.href ? "" : 'role="presentation"'
        }>
          <span class="contact-item__label">${escapeHtml(contact.label)}</span>
          <span class="contact-item__value">${escapeHtml(contact.value)}</span>
        </a>
      `,
    )
    .join("");
}

function renderCollectionDetail(collection, works, mediaMap, collectionPage, contacts) {
  const cover = mediaMap.get(collection.coverImageId);
  const collectionWorks = works
    .filter((work) => collection.workIds?.includes(work.id))
    .sort(byOrder);
  const primaryContact = getPrimaryContact(contacts);
  const metaLabels = collectionPage.hero.metaLabels || {};
  const years = formatYearRange(collection.yearStart, collection.yearEnd);
  const subtitle = (collectionPage.hero.subtitleTemplate || "{seasonLabel}").replace(
    "{seasonLabel}",
    collection.season || "Коллекция",
  );
  const description = (collectionPage.hero.descriptionTemplate || "{description}").replace(
    "{description}",
    collection.fullDescription || collection.shortDescription || "",
  );
  const curatorText = (collectionPage.body.curatorTemplate || "").replaceAll(
    "{title}",
    collection.title,
  );
  const processText = (collectionPage.body.processTemplate || "").replaceAll(
    "{title}",
    collection.title,
  );

  return `
    <div class="detail-shell">
      <div class="detail-breadcrumbs">
        <a href="#collections">${escapeHtml(collectionPage.navigation.backToCollectionsLabel || "Все коллекции")}</a>
        <span>/</span>
        <a href="#catalog">${escapeHtml(collectionPage.navigation.backToCatalogLabel || "Смотреть каталог")}</a>
      </div>

      <section class="detail-hero">
        <div class="detail-hero__copy">
          <p class="section-kicker">${escapeHtml(collectionPage.hero.kicker || "Коллекция")}</p>
          <h1>${escapeHtml((collectionPage.hero.titleTemplate || "{title}").replace("{title}", collection.title))}</h1>
          <p class="detail-hero__subtitle">${escapeHtml(subtitle)}</p>
          <p class="detail-hero__text">${escapeHtml(description)}</p>
          <div class="meta">
            ${collection.season ? `<span>${escapeHtml(metaLabels.season || "Тип цикла")}: ${escapeHtml(collection.season)}</span>` : ""}
            ${years ? `<span>${escapeHtml(metaLabels.years || "Годы")}: ${escapeHtml(years)}</span>` : ""}
            <span>${escapeHtml(metaLabels.worksCount || "Работ в коллекции")}: ${collectionWorks.length}</span>
          </div>
        </div>
        <div class="detail-hero__media">
          ${cover ? `<img src="${cover.src}" alt="${escapeHtml(cover.alt || collection.title)}" />` : ""}
        </div>
      </section>

      <section class="detail-quote">
        <p>Коллекция читается как последовательность состояний: через повторы, вариации линии и внутренний ритм сезона.</p>
      </section>

      <section class="detail-section">
        <div class="detail-text-grid">
          <article class="detail-card">
            <p class="section-kicker">${escapeHtml(collectionPage.body.leadTitle || "О коллекции")}</p>
            <p>${escapeHtml(collectionPage.body.leadText || "")}</p>
            <p>${escapeHtml(collection.fullDescription || collection.shortDescription || "")}</p>
          </article>
          <article class="detail-card">
            <p class="section-kicker">${escapeHtml(collectionPage.body.curatorTitle || "Кураторский взгляд")}</p>
            <p>${escapeHtml(curatorText)}</p>
          </article>
          <article class="detail-card">
            <p class="section-kicker">${escapeHtml(collectionPage.body.processTitle || "Техника и характер цикла")}</p>
            <p>${escapeHtml(processText)}</p>
          </article>
        </div>
      </section>

      <section class="detail-section">
        <div class="section-head">
          <p class="section-kicker">${escapeHtml(collectionPage.body.worksTitle || "Работы внутри коллекции")}</p>
          <p>${escapeHtml(collectionPage.body.worksText || "")}</p>
        </div>
        <div class="work-grid">
          ${collectionWorks
            .map((work) => {
              const image = mediaMap.get(work.imageIds?.[0]);
              return `
                <article class="work-card">
                  <a class="card-link" href="${slugifyRoute("work", work.slug)}" aria-label="${escapeHtml(work.title)}">
                    <div class="work-card__cover">
                      ${
                        image
                          ? `<img src="${image.src}" alt="${escapeHtml(image.alt || work.title)}" />`
                          : `<div class="work-card__fallback">${escapeHtml(work.title)}</div>`
                      }
                    </div>
                    <div class="work-card__body">
                      <p class="section-kicker">${escapeHtml(collection.title)}</p>
                      <h3>${escapeHtml(work.title)}</h3>
                      <p>${escapeHtml(work.shortDescription || "")}</p>
                    </div>
                  </a>
                </article>
              `;
            })
            .join("")}
        </div>
      </section>

      <section class="detail-cta">
        <div>
          <p class="section-kicker">${escapeHtml(collectionPage.cta.title || "")}</p>
          <p>${escapeHtml(collectionPage.cta.text || "")}</p>
        </div>
        <div class="hero__actions">
          <a class="button button--primary" href="${escapeHtml(primaryContact?.href || "#contacts")}">${escapeHtml(collectionPage.cta.primaryLabel || "Связаться с мастерской")}</a>
          <a class="button button--ghost" href="#catalog">${escapeHtml(collectionPage.cta.secondaryLabel || "Перейти в каталог")}</a>
        </div>
      </section>
    </div>
  `;
}

function renderWorkDetail(work, collection, mediaMap, workPage, contacts) {
  const image = mediaMap.get(work.imageIds?.[0]);
  const materials = (work.materials || []).join(", ");
  const status = formatStatus(work.status, workPage.statusLabels);
  const primaryContact = getPrimaryContact(contacts);
  const subtitle = (workPage.hero.subtitleTemplate || "{collectionTitle}").replace(
    "{collectionTitle}",
    collection?.title || "",
  );

  return `
    <div class="detail-shell">
      <div class="detail-breadcrumbs">
        <a href="${collection ? slugifyRoute("collection", collection.slug) : "#collections"}">${escapeHtml(workPage.navigation.backToCollectionLabel || "Назад к коллекции")}</a>
        <span>/</span>
        <a href="#catalog">${escapeHtml(workPage.navigation.backToCatalogLabel || "Назад в каталог")}</a>
      </div>

      <section class="detail-hero">
        <div class="detail-hero__copy">
          <p class="section-kicker">${escapeHtml(workPage.hero.kicker || "Гравюра")}</p>
          <h1>${escapeHtml((workPage.hero.titleTemplate || "{title}").replace("{title}", work.title))}</h1>
          <p class="detail-hero__subtitle">${escapeHtml(subtitle)}</p>
          <p class="detail-hero__text">${escapeHtml((workPage.hero.descriptionTemplate || "{shortDescription}").replace("{shortDescription}", work.shortDescription || ""))}</p>
          <div class="meta">
            ${work.year ? `<span>${escapeHtml(workPage.hero.metaLabels.year || "Год")}: ${work.year}</span>` : ""}
            ${work.technique ? `<span>${escapeHtml(workPage.hero.metaLabels.technique || "Техника")}: ${escapeHtml(work.technique)}</span>` : ""}
            ${work.dimensionsText ? `<span>${escapeHtml(workPage.hero.metaLabels.dimensions || "Размер")}: ${escapeHtml(work.dimensionsText)}</span>` : ""}
            ${materials ? `<span>${escapeHtml(workPage.hero.metaLabels.materials || "Материалы")}: ${escapeHtml(materials)}</span>` : ""}
            ${status ? `<span>${escapeHtml(workPage.hero.metaLabels.status || "Статус")}: ${escapeHtml(status)}</span>` : ""}
          </div>
        </div>
        <div class="detail-hero__media">
          ${
            image
              ? `
                <button
                  class="detail-hero__zoom"
                  type="button"
                  data-lightbox-src="${escapeHtml(image.src)}"
                  data-lightbox-alt="${escapeHtml(image.alt || work.title)}"
                  aria-label="Увеличить изображение гравюры"
                >
                  <img src="${image.src}" alt="${escapeHtml(image.alt || work.title)}" />
                </button>
              `
              : `<div class="work-card__fallback">${escapeHtml(work.title)}</div>`
          }
        </div>
      </section>

      <section class="detail-quote">
        <p>Гравюра раскрывается вблизи: в плотности штриха, в мягкости отпечатка и в дыхании самой бумаги.</p>
      </section>

      <section class="detail-section">
        <div class="detail-text-grid">
          <article class="detail-card">
            <p class="section-kicker">${escapeHtml(workPage.body.descriptionTitle || "О работе")}</p>
            <p>${escapeHtml((workPage.body.descriptionTemplate || "{fullDescription}").replace("{fullDescription}", work.fullDescription || ""))}</p>
          </article>
          <article class="detail-card">
            <p class="section-kicker">${escapeHtml(workPage.body.contextTitle || "Контекст коллекции")}</p>
            <p>${escapeHtml((workPage.body.contextTemplate || "").replaceAll("{title}", work.title).replaceAll("{collectionTitle}", collection?.title || ""))}</p>
          </article>
          <article class="detail-card">
            <p class="section-kicker">${escapeHtml(workPage.body.materialTitle || "Материальность листа")}</p>
            <p>${escapeHtml((workPage.body.materialTemplate || "").replaceAll("{title}", work.title))}</p>
            <p>${escapeHtml(workPage.body.detailsText || "")}</p>
          </article>
        </div>
      </section>

      <section class="detail-cta">
        <div>
          <p class="section-kicker">${escapeHtml(workPage.cta.title || "")}</p>
          <p>${escapeHtml(workPage.cta.text || "")}</p>
        </div>
        <div class="hero__actions">
          <button class="button button--primary" type="button" data-open-request-modal="true" data-request-title="${escapeHtml(work.title)}">${escapeHtml(workPage.cta.primaryLabel || "Запросить работу")}</button>
          ${
            collection
              ? `<a class="button button--ghost" href="${slugifyRoute("collection", collection.slug)}">${escapeHtml(workPage.cta.secondaryLabel || "Смотреть коллекцию")}</a>`
              : ""
          }
        </div>
      </section>
    </div>
  `;
}

function applyRoute(data) {
  const { artist, collections, works, contacts, mediaMap, collectionPage, workPage, browseSections } = data;
  const detailView = document.querySelector("#detailView");
  const overviewView = document.querySelector("#overviewView");
  const hash = window.location.hash || "";
  const [, routeType, routeSlug] = hash.match(/^#([^/]+)\/(.+)$/) || [];

  if (hash === "#catalog") {
    detailView.innerHTML = renderCatalogPage(browseSections);
    detailView.hidden = false;
    overviewView.hidden = true;
    setupCatalogPage({ works, collections, mediaMap, browseSections });
    updateDocumentMeta(
      browseSections?.catalog?.page?.title || "Каталог гравюр",
      browseSections?.catalog?.page?.lead || "Каталог работ мастерской Сергея Свидера.",
    );
    window.scrollTo({ top: 0, behavior: "smooth" });
    return;
  }

  if (!routeType || !routeSlug) {
    detailView.hidden = true;
    overviewView.hidden = false;
    updateDocumentMeta(
      artist.fullName,
      "Авторская гравюрная мастерская Сергея Михайловича Свидера. Коллекции, каталог гравюр, заказ, информация о мастерской и контакты.",
    );
    if (hash && hash !== "#top") {
      const target = document.querySelector(hash);
      if (target) {
        requestAnimationFrame(() => {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
      }
    }
    return;
  }

  if (routeType === "collection") {
    const collection = collections.find((item) => item.slug === routeSlug);
    if (collection) {
      detailView.innerHTML = renderCollectionDetail(
        collection,
        works,
        mediaMap,
        collectionPage,
        contacts,
      );
      detailView.hidden = false;
      overviewView.hidden = true;
      updateDocumentMeta(
        `${collection.title} - Сергей Свидер`,
        collection.shortDescription || collection.fullDescription || collection.title,
      );
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
  }

  if (routeType === "work") {
    const work = works.find((item) => item.slug === routeSlug);
    if (work) {
      const collection = collections.find((item) => item.id === work.collectionId);
      detailView.innerHTML = renderWorkDetail(work, collection, mediaMap, workPage, contacts);
      detailView.hidden = false;
      overviewView.hidden = true;
      updateDocumentMeta(
        `${work.title} - Сергей Свидер`,
        work.shortDescription || work.fullDescription || work.title,
      );
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
  }

  detailView.hidden = true;
  overviewView.hidden = false;
}

function renderError(error) {
  document.body.innerHTML = `
    <main style="padding:40px;font-family:Manrope,sans-serif">
      <h1 style="font-family:'Cormorant Garamond',serif;font-size:48px">Не удалось загрузить данные</h1>
      <p>${escapeHtml(error.message)}</p>
      <p>Откройте проект через локальный сервер, чтобы браузер мог читать JSON-файлы.</p>
    </main>
  `;
}

async function main() {
  try {
    setupMobileMenu();
    setupLightbox();
    setupRequestForms();
    setupRequestModal();

    const [
      artist,
      workshop,
      collections,
      works,
      exhibitions,
      contacts,
      media,
      news,
      siteSections,
      browseSections,
      collectionPage,
      workPage,
    ] = await Promise.all(Object.values(contentFiles).map(loadJson));

    const mediaMap = makeMediaMap(media);

    renderHero(artist, mediaMap, siteSections, collections, works);
    renderIntro(artist, workshop, exhibitions);
    renderSectionCopy(siteSections, browseSections, works);
    renderNews(news, mediaMap);
    renderFeaturedCollections(collections, mediaMap);
    renderCollections(collections, mediaMap, browseSections);
    renderWorks(works, collections, mediaMap, browseSections);
    renderOrder(contacts);
    renderWorkshop(workshop, mediaMap, siteSections);
    renderContacts(contacts);

    const routeData = {
      artist,
      collections,
      works,
      contacts,
      mediaMap,
      collectionPage,
      workPage,
      browseSections,
    };

    applyRoute(routeData);
    window.addEventListener("hashchange", () => applyRoute(routeData));
  } catch (error) {
    renderError(error);
  }
}

main();
