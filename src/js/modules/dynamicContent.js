async function fetchJSON(url) {
  try {
    const res = await fetch(url);
    if (!res.ok) return [];
    return await res.json();
  } catch (e) {
    return [];
  }
}

function injectProductCards(items) {
  document.querySelectorAll(".category__grid[data-category-id]").forEach((grid) => {
    const categoryId = Number(grid.dataset.categoryId);
    items
      .filter((item) => item.category_id === categoryId)
      .forEach((item) => {
        const a = document.createElement("a");
        a.href = item.link;
        a.className = "product-card";

        const media = document.createElement("div");
        media.className = "product-card__media";
        if (item.image) {
          const img = document.createElement("img");
          img.src = `uploads/products/${item.image}`;
          img.alt = item.name;
          media.appendChild(img);
        } else {
          const placeholder = document.createElement("span");
          placeholder.className = "product-card__placeholder";
          placeholder.textContent = "GEHWOL";
          media.appendChild(placeholder);
        }

        const title = document.createElement("h3");
        title.className = "product-card__title";
        title.textContent = item.name;

        const cta = document.createElement("span");
        cta.className = "product-card__cta btn-link";
        cta.textContent = "Uzzināt vairāk →";

        a.append(media, title, cta);
        grid.appendChild(a);
      });
  });
}

function injectCategoryLinks(categories) {
  categories.forEach((cat) => {
    if (!cat.parent_id) return;
    const parentList = document.querySelector(`[data-category-id="${cat.parent_id}"]`);
    if (!parentList) return;
    const li = document.createElement("li");
    const a = document.createElement("a");
    a.href = cat.link;
    a.className = "products__link";
    a.textContent = cat.name;
    li.appendChild(a);
    parentList.appendChild(li);
  });
}

function injectSlides(items, wrapperSelector, folder) {
  const wrapper = document.querySelector(wrapperSelector);
  if (!wrapper) return;
  items.forEach((item) => {
    const slide = document.createElement("div");
    slide.className = "news__slide swiper-slide";

    const a = document.createElement("a");
    a.href = item.link;
    a.className = "news__slide-link";

    const image = document.createElement("div");
    image.className = "news__image";
    image.setAttribute("aria-hidden", "true");
    if (item.image) {
      const img = document.createElement("img");
      img.src = `uploads/${folder}/${item.image}`;
      img.alt = "";
      image.appendChild(img);
    }

    const title = document.createElement("h3");
    title.className = "news__slide-title";
    title.textContent = item.title;

    a.append(image, title);
    slide.appendChild(a);
    wrapper.appendChild(slide);
  });
}

function pageNeedsDynamicContent() {
  return document.querySelector(".category__grid[data-category-id], #products, #news") !== null;
}

export default async function loadDynamicContent() {
  if (!pageNeedsDynamicContent()) return;

  const [products, categories, news, articles] = await Promise.all([
    fetchJSON("php/api.php?type=products"),
    fetchJSON("php/api.php?type=categories"),
    fetchJSON("php/api.php?type=news"),
    fetchJSON("php/api.php?type=articles"),
  ]);

  injectProductCards(products);
  injectCategoryLinks(categories);
  injectSlides(news, ".swiper-news .news__wrapper", "news");
  injectSlides(articles, ".swiper-articles .news__wrapper", "articles");
}
