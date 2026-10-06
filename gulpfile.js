const gulp = require("gulp");

// Tasks
require("./gulp/dev.js");
require("./gulp/docs.js");
require("./gulp/fontsDev.js");
require("./gulp/fontsDocs.js");
require("./gulp/templates.js");
const { writeAssets } = require('./gulp/seo');
gulp.task('seo:docs', function(done) { writeAssets('./docs'); done(); });
gulp.task('seo:dev', function(done) { writeAssets('./build'); done(); });
gulp.task("build:docs", gulp.series("html:docs", "seo:docs", "sass:docs", "templates:docs"));

gulp.task(
  "default",
  gulp.series(
    "clean:dev",
    "fontsDev",
    gulp.parallel(
      "html:dev",
      "sass:dev",
      "images:dev",
      "svgIcons:dev",
      gulp.series("svgStack:dev", "svgSymbol:dev"),
      "files:dev",
      "video:dev",
      "js:dev",
      "phpAdmin:dev",
      "uploads:dev",
    ),
    'seo:dev',
    gulp.parallel("server:dev", "watch:dev"),
  ),
);

// Full production build of docs/ and php/templates/; finishes by itself (no dev server).
const buildDocs = gulp.series(
  "clean:docs",
  "fontsDocs",
  gulp.parallel(
    "html:docs",
    "sass:docs",
    "images:docs",
    gulp.series("svgStack:docs", "svgSymbol:docs"),
    "files:docs",
    "video:docs",
    "js:docs",
  ),
  "seo:docs",
  "templates:docs",
);
gulp.task("build", buildDocs);

// Full build, then a local preview server for docs/ (static files only)
gulp.task("docs", gulp.series(buildDocs, "server:docs"));
