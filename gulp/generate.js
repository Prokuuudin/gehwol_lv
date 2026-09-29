const gulp = require("gulp");

// Retired: products, news and articles now live in php/data/*.json in the new shape
// (see php/bin/migrate.php) and are rendered by PHP. The old generator would overwrite
// src/html pages from data it no longer understands, so it refuses to run.
gulp.task("generate:static", function (done) {
  done(new Error("generate:static is retired — content is edited in the PHP admin (php/admin)."));
});
