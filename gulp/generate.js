const gulp = require("gulp");
const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const DATA_DIR = path.join(ROOT, "php", "data");
const HTML_DIR = path.join(ROOT, "src", "html");

function loadJSON(name) {
  const file = path.join(DATA_DIR, `${name}.json`);
  if (!fs.existsSync(file)) {
    throw new Error(`generate:static: missing ${file}`);
  }
  const rows = JSON.parse(fs.readFileSync(file, "utf8"));
  if (!Array.isArray(rows)) {
    throw new Error(`generate:static: ${file} must contain a JSON array`);
  }
  return rows;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function bySortOrder(a, b) {
  const orderDiff = (a.sort_order || 0) - (b.sort_order || 0);
  return orderDiff !== 0 ? orderDiff : (a.id || 0) - (b.id || 0);
}

function productCardHtml(product) {
  const name = escapeHtml(product.name);
  const media = product.image
    ? `<img src="./img/${escapeHtml(product.image)}" alt="${name}">`
    : `<span class="product-card__placeholder">GEHWOL</span>`;
  return `<a href="produkts-${product.id}.html" class="product-card"><div class="product-card__media">${media}</div><h3 class="product-card__title">${name}</h3><span class="product-card__cta btn-link">Uzzināt vairāk →</span></a>`;
}

function categoryGridHtml(products) {
  if (products.length === 0) {
    return `<p>Šajā kategorijā vēl nav produktu.</p>`;
  }
  // one card per line — gulp-webp-retina-html parses <img> tags line-by-line
  // and mismatches sources when multiple <img> land on the same line
  return products.map(productCardHtml).join("\n");
}

const CATEGORY_INCLUDE_RE = /@@include\('blocks\/category-page\.html',\s*(\{[\s\S]*?\})\s*\)/;

function patchCategoryPage(category, products) {
  const filePath = path.join(HTML_DIR, category.link_url);
  if (!fs.existsSync(filePath)) {
    console.warn(`generate:static: skip missing category page ${category.link_url}`);
    return;
  }
  const source = fs.readFileSync(filePath, "utf8");
  const match = source.match(CATEGORY_INCLUDE_RE);
  if (!match) {
    console.warn(`generate:static: no category-page include found in ${category.link_url}`);
    return;
  }
  const params = JSON.parse(match[1]);
  params.productCards = categoryGridHtml(products);
  const replacement = `@@include('blocks/category-page.html', ${JSON.stringify(params)})`;
  fs.writeFileSync(filePath, source.replace(CATEGORY_INCLUDE_RE, () => replacement), "utf8");
}

function productMetaDescription(product) {
  const firstLine = String(product.description || "").split("\n")[0];
  return escapeHtml(`${product.name} — ${firstLine}`.slice(0, 160));
}

function productDescriptionHtml(description) {
  return String(description || "")
    .split("\n")
    .map(escapeHtml)
    .join("<br>");
}

function productMediaHtml(product) {
  const name = escapeHtml(product.name);
  return product.image
    ? `<img src="./img/${escapeHtml(product.image)}" alt="${name}">`
    : `<span class="product-detail__placeholder">GEHWOL</span>`;
}

function productDetailPageSource(product, category) {
  const name = escapeHtml(product.name);
  const params = {
    name,
    description: productDescriptionHtml(product.description),
    media: productMediaHtml(product),
    categoryName: category ? escapeHtml(category.name) : "",
    categoryHref: category ? category.link_url : "index.html#products",
  };
  return `<!DOCTYPE html>
<html lang="lv">
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="${productMetaDescription(product)}" />
	<title>${name} — Gehwol</title>
	<link rel="stylesheet" href="./css/main.css" />
	<link rel="icon" type="image/x-icon" href="./img/favicons/favicon.ico">
	<link rel="apple-touch-icon" sizes="180x180" href="./img/favicons/apple-touch-icon.png">
</head>

<body>
	@@include('blocks/header.html')
	<main>
		@@include('blocks/product-detail-page.html', ${JSON.stringify(params)})
	</main>
	@@include('blocks/footer.html')
	<script src="./js/index.bundle.js"></script>
</body>

</html>
`;
}

function writeProductPage(product, categoriesById) {
  const category = categoriesById.get(product.category_id);
  if (!category) {
    console.warn(`generate:static: product ${product.id} (${product.name}) has unknown category_id ${product.category_id}`);
  }
  const filePath = path.join(HTML_DIR, `produkts-${product.id}.html`);
  fs.writeFileSync(filePath, productDetailPageSource(product, category), "utf8");
}

function cleanStaleProductPages(validIds) {
  const files = fs.readdirSync(HTML_DIR).filter((f) => /^produkts-\d+\.html$/.test(f));
  files.forEach((file) => {
    const id = Number(file.match(/^produkts-(\d+)\.html$/)[1]);
    if (!validIds.has(id)) {
      fs.unlinkSync(path.join(HTML_DIR, file));
      console.log(`generate:static: removed stale ${file}`);
    }
  });
}

function generateStatic(done) {
  const categories = loadJSON("categories");
  const products = loadJSON("products");
  const categoriesById = new Map(categories.map((c) => [c.id, c]));

  const productsByCategory = new Map();
  products.forEach((p) => {
    const list = productsByCategory.get(p.category_id) || [];
    list.push(p);
    productsByCategory.set(p.category_id, list);
  });

  categories
    .filter((c) => c.link_url)
    .forEach((category) => {
      const items = (productsByCategory.get(category.id) || []).slice().sort(bySortOrder);
      patchCategoryPage(category, items);
    });

  products.forEach((product) => writeProductPage(product, categoriesById));
  cleanStaleProductPages(new Set(products.map((p) => p.id)));

  done();
}

gulp.task("generate:static", generateStatic);
