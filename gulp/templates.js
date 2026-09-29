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
// server config is copied next to the site.
gulp.task("templates:docs", function (done) {
  fs.mkdirSync(TEMPLATES, { recursive: true });
  fs.readdirSync(TEMPLATES)
    .filter((file) => file.endsWith(".html"))
    .forEach((file) => fs.unlinkSync(path.join(TEMPLATES, file)));

  fs.readdirSync(DOCS)
    .filter((file) => file.endsWith(".html"))
    .forEach((file) => {
      const built = path.join(DOCS, file);
      if (!fs.existsSync(path.join(SRC_HTML, file))) {
        fs.unlinkSync(built);
      } else if (fs.readFileSync(built, "utf8").includes("<x-slot ")) {
        fs.renameSync(built, path.join(TEMPLATES, file));
      }
    });

  const sitemap = path.join(DOCS, "sitemap.xml");
  if (fs.existsSync(sitemap)) fs.unlinkSync(sitemap);
  fs.copyFileSync(path.join(ROOT, "src", ".htaccess"), path.join(DOCS, ".htaccess"));
  done();
});
