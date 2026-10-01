// Assembles release/ — exactly what goes to the web server's document root (FTP upload).
//   node scripts/build-release.js              update: code, design, templates; no content data
//   node scripts/build-release.js --with-data  first installation: also php/data/*.json and admin_users.json
// Content data (php/data/*.json) and uploads/ on the server are never part of a normal release,
// so uploading release/ cannot overwrite what editors changed in the admin.
// Build docs/ and php/templates/ first (npx gulp build:docs or npx gulp build).
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'release');
const DEPLOY_MANIFEST = path.join(ROOT, 'deploy-manifest.json');
const withData = process.argv.includes('--with-data');

const DATA_FILES = ['categories.json', 'products.json', 'news.json', 'articles.json', 'admin_users.json'];
const DEV_ONLY_PHP = new Set(['php/bin/dev-router.php', 'php/bin/render-all.php', 'php/bin/migrate.php']);

function copyTree(from, to, keep = () => true) {
  for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
    const src = path.join(from, entry.name);
    const rel = path.relative(ROOT, src).split(path.sep).join('/');
    if (!keep(rel, entry)) continue;
    const dest = path.join(to, entry.name);
    if (entry.isDirectory()) {
      fs.mkdirSync(dest, { recursive: true });
      copyTree(src, dest, keep);
    } else {
      fs.copyFileSync(src, dest);
    }
  }
}

function listFiles(dir, base = dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const p = path.join(dir, e.name);
    return e.isDirectory() ? listFiles(p, base) : [path.relative(base, p).split(path.sep).join('/')];
  });
}

for (const required of ['docs/.htaccess', 'docs/css/main.css', 'php/templates/_shell.html', 'php/templates/index.html']) {
  if (!fs.existsSync(path.join(ROOT, required))) {
    console.error(`Missing ${required} — run npx gulp build:docs first.`);
    process.exit(1);
  }
}

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(OUT);

// 1. built public site: css, js, img, fonts, legal pages, robots.txt, .htaccess, .user.ini
copyTree(path.join(ROOT, 'docs'), OUT);

// 2. PHP application (admin, public renderer, templates) without data and dev tools
fs.mkdirSync(path.join(OUT, 'php'));
copyTree(path.join(ROOT, 'php'), path.join(OUT, 'php'), (rel, entry) => {
  if (DEV_ONLY_PHP.has(rel)) return false;
  if (rel === 'php/data/backups') return false;
  if (rel.startsWith('php/data/') && entry.isFile()) {
    return entry.name === '.htaccess' || (withData && DATA_FILES.includes(entry.name));
  }
  return true;
});

// 3. empty upload folders with their protection (existing server uploads stay untouched)
for (const section of ['products', 'news', 'articles']) {
  fs.mkdirSync(path.join(OUT, 'uploads', section), { recursive: true });
  fs.writeFileSync(path.join(OUT, 'uploads', section, '.gitkeep'), '');
}
fs.copyFileSync(path.join(ROOT, 'uploads', '.htaccess'), path.join(OUT, 'uploads', '.htaccess'));

// 4. release stamp, shown on the admin server-check page (helps with rollbacks)
let commit = 'unknown';
try {
  const git = (...args) => execFileSync('git', args, { cwd: ROOT }).toString().trim();
  commit = git('rev-parse', '--short', 'HEAD') + (git('status', '--porcelain', '--', 'php', 'docs', 'src') ? '+local-changes' : '');
} catch (e) { /* not a git checkout */ }
const built = new Date().toISOString().slice(0, 16).replace('T', ' ');
fs.writeFileSync(path.join(OUT, 'php', 'includes', 'release.php'),
  `<?php
return ['commit' => '${commit.replace(/[^\w+-]/g, '')}', 'built' => '${built}'];
`);

// 5. safety check: nothing private or development-only may be in the release
// Directory enumeration order differs between filesystems (notably Windows and
// Linux). JavaScript's default string sort is locale-independent and keeps the
// tracked manifest byte-for-byte reproducible across build environments.
const files = listFiles(OUT).sort();
const forbidden = files.filter((f) =>
  /(^|\/)(node_modules|\.git|tests|backups|src|plans|specs)(\/|$)/.test(f)
  || /\.(docx?|md|log|lock|tmp|env|sql|zip|bak)$/i.test(f)
  || /(^|\/)(composer|package(-lock)?)\.json$/.test(f)
  || /(^|\/)login_attempts\.json$/.test(f)
  || (!withData && /^php\/data\/[^/]+\.json$/.test(f)));
const secretLike = files
  .filter((f) => /\.(html|js|css|txt|json|ini|xml)$/i.test(f) && !f.startsWith('img/'))
  .filter((f) => /(password|parole|passwd)\s*[:=]\s*["']?[^\s"'<]{6,}/i.test(fs.readFileSync(path.join(OUT, f), 'utf8')));
if (forbidden.length || secretLike.length) {
  console.error('Release check failed:');
  [...forbidden, ...secretLike.map((f) => `${f} (looks like a password)`)].forEach((f) => console.error('  ' + f));
  process.exit(1);
}

// The Plesk server has PHP but no Node.js. Commit this small manifest together
// with the already-built docs/ and PHP sources. The PHP deployer uses it as the
// exact allow-list and never has to build or infer release contents on-server.
const deployFiles = files
  .filter((file) => file !== 'php/includes/release.php')
  .filter((file) => !file.startsWith('php/data/') && !file.startsWith('uploads/'))
  .map((file) => {
    const source = file.startsWith('php/') ? file : `docs/${file}`;
    const sourcePath = path.join(ROOT, ...source.split('/'));
    if (!fs.existsSync(sourcePath)
        || !fs.readFileSync(sourcePath).equals(fs.readFileSync(path.join(OUT, file)))) {
      console.error(`Cannot map release file back to its committed source: ${file}`);
      process.exit(1);
    }
    return { path: file, source };
  });
fs.writeFileSync(DEPLOY_MANIFEST, `${JSON.stringify({ version: 1, files: deployFiles }, null, 2)}\n`);

const size = files.reduce((sum, f) => sum + fs.statSync(path.join(OUT, f)).size, 0);
console.log(`release/ ready: ${files.length} files, ${(size / 1024 / 1024).toFixed(1)} MB, commit ${commit}${withData ? ', WITH content data (first installation only)' : ''}; deploy manifest: ${deployFiles.length} files.`);
