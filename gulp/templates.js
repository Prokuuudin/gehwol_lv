const gulp = require("gulp");
const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const DOCS = path.join(ROOT, "docs");
const SRC_HTML = path.join(ROOT, "src", "html");
const TEMPLATES = path.join(ROOT, "php", "templates");

// Pages that PHP fills at request time (index, category pages, the page shell) contain
// <x-slot> placeholders. They are moved from docs/ to php/templates/ so the web server
// never serves them as plain files. Built pages whose source is gone are removed, and the
// server config (.htaccess, .user.ini) is copied next to the site.
gulp.task("templates:docs", function (done) {
  fs.mkdirSync(TEMPLATES, { recursive: true });
  const built = fs.readdirSync(DOCS).filter((file) => file.endsWith(".html"));
  built
    .filter((file) => !fs.existsSync(path.join(SRC_HTML, file)))
    .forEach((file) => fs.unlinkSync(path.join(DOCS, file)));
  const fresh = built.filter((file) =>
    fs.existsSync(path.join(DOCS, file)) && fs.readFileSync(path.join(DOCS, file), "utf8").includes("<x-slot "));

  // Replace the templates only when html:docs has just produced new ones; run on its own,
  // this task must not wipe php/templates/.
  if (fresh.length) {
    fs.readdirSync(TEMPLATES)
      .filter((file) => file.endsWith(".html"))
      .forEach((file) => fs.unlinkSync(path.join(TEMPLATES, file)));
    fresh.forEach((file) => fs.renameSync(path.join(DOCS, file), path.join(TEMPLATES, file)));
  }

  const sitemap = path.join(DOCS, "sitemap.xml");
  if (fs.existsSync(sitemap)) fs.unlinkSync(sitemap);
  for (const file of [".htaccess", ".user.ini"]) {
    fs.copyFileSync(path.join(ROOT, "src", file), path.join(DOCS, file));
  }
  done();
});
