const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const directory = path.resolve(process.argv[2] || 'docs');
const config = require('../seo.config.json');
const base = (process.env.SITE_URL || config.siteUrl).replace(/\/$/, '');
const files = fs.readdirSync(directory).filter(f => f.endsWith('.html'));
const titles = new Set();
let images = 0;
for (const file of files) {
  const html = fs.readFileSync(path.join(directory, file), 'utf8');
  assert.equal((html.match(/<h1\b/g) || []).length, 1, `${file}: H1`);
  assert(!html.includes('@@'), `${file}: unresolved include`);
  const title = html.match(/<title>(.*?)<\/title>/s)?.[1];
  assert(title && !titles.has(title), `${file}: missing or duplicate title`);
  titles.add(title);
  assert(/<meta[^>]*name="description"[^>]*content="[^"]+"/.test(html), `${file}: description`);
  const canonical = [...html.matchAll(/<link[^>]*rel="canonical"[^>]*href="([^"]+)"/g)];
  const url = `${base}/${file === 'index.html' ? '' : file}`;
  assert.equal(canonical.length, 1, `${file}: canonical count`);
  assert.equal(canonical[0][1], url, `${file}: canonical URL`);
  assert(html.includes(`property="og:url" content="${url}"`), `${file}: Open Graph URL`);
  const scripts = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)];
  assert.equal(scripts.length, 1, `${file}: JSON-LD count`);
  const graph = JSON.parse(scripts[0][1])['@graph'];
  const breadcrumbs = graph.find(g => g['@type'] === 'BreadcrumbList');
  if (file !== 'index.html') {
    assert(breadcrumbs, `${file}: breadcrumbs`);
    const crumbs = breadcrumbs.itemListElement;
    assert.equal(crumbs[0].item, `${base}/`);
    assert.equal(crumbs.at(-1).item, url);
    crumbs.forEach((crumb, index) => {
      assert.equal(crumb.position, index + 1);
      assert(!crumb.name.includes(' / '), `${file}: breadcrumb contains multiple levels`);
      if (index > 0) assert.notEqual(crumb.item, `${base}/index.html`, `${file}: duplicate homepage breadcrumb`);
    });
  }
  for (const match of html.matchAll(/<img\b[^>]*>/g)) {
    images++;
    assert(/\balt="[^"]*"/.test(match[0]), `${file}: alt`);
    assert(/\bwidth="[1-9]\d*"/.test(match[0]) && /\bheight="[1-9]\d*"/.test(match[0]), `${file}: image dimensions`);
    assert(/\bloading="(?:lazy|eager)"/.test(match[0]), `${file}: image loading`);
  }
  for (const match of html.matchAll(/\b(?:href|src)="([^"]+)"/g)) {
    const relative = match[1].split('#')[0].split('?')[0];
    if (!relative || /^(?:[a-z]+:|\/\/)/i.test(relative)) continue;
    assert(fs.existsSync(path.resolve(directory, relative)), `${file}: broken reference ${relative}`);
  }
}
const sitemap = fs.readFileSync(path.join(directory, 'sitemap.xml'), 'utf8');
const locations = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
assert.equal(new Set(locations).size, files.length, 'Sitemap unique URL count');
for (const file of files) assert(locations.includes(`${base}/${file === 'index.html' ? '' : file}`), `Missing sitemap URL: ${file}`);
assert(fs.readFileSync(path.join(directory, 'robots.txt'), 'utf8').includes(`Sitemap: ${base}/sitemap.xml`));
console.log(`SEO checks passed: ${files.length} pages, ${images} images, ${locations.length} sitemap URLs.`);
