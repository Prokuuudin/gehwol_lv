const gulp = require("gulp");

// Tasks
require("./gulp/dev.js");
require("./gulp/docs.js");
require("./gulp/fontsDev.js");
require("./gulp/fontsDocs.js");
require("./gulp/generate.js");
const { writeAssets } = require('./gulp/seo');
gulp.task('seo:docs', function(done) { writeAssets('./docs'); done(); });
gulp.task('seo:dev', function(done) { writeAssets('./build'); done(); });
gulp.task('build:docs', gulp.series('html:docs', 'seo:docs', 'sass:docs'));

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
      "js:dev",
      "phpAdmin:dev",
      "uploads:dev",
    ),
    'seo:dev',
    gulp.parallel("server:dev", "watch:dev"),
  ),
);

gulp.task(
  "docs",
  gulp.series(
    "clean:docs",
    "fontsDocs",
    gulp.parallel(
      "html:docs",
      "sass:docs",
      "images:docs",
      gulp.series("svgStack:docs", "svgSymbol:docs"),
      "files:docs",
      "js:docs",
    ),
    'seo:docs',
    gulp.parallel("server:docs"),
  ),
);
