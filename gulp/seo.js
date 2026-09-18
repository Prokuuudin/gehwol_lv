const fs = require('fs');
const path = require('path');
const { Transform } = require('stream');
const ROOT = path.resolve(__dirname, '..');
const config = require('../seo.config.json');
const content = require('../src/seo/content.json');
const escape = value => String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const plain = value => String(value).replace(/<[^>]*>/g, ' ').replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n))).replace(/&#x([\da-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16))).replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/\s+/g, ' ').trim();

function siteUrl() {
  const value = process.env.SITE_URL || config.siteUrl;
  if (!value) return null;
  const url = new URL(value);
  if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash) throw new Error('SITE_URL must be a public HTTPS URL without credentials, query or fragment');
  return url.href.replace(/\/$/, '');
}

function imageSize(src) {
  const relative = decodeURI(src).replace(/^\.\//, '').replace(/\.webp$/i, '.png');
  const candidates = [src.replace(/^\.\//, ''), relative, relative.replace(/\.png$/i, '.jpg'), relative.replace(/\.png$/i, '.jpeg')];
  for (const candidate of candidates) {
    const file = path.resolve(ROOT, 'src', candidate);
    if (!file.startsWith(path.join(ROOT, 'src', 'img') + path.sep) || !fs.existsSync(file)) continue;
    const b = fs.readFileSync(file);
    if (b.length > 24 && b.toString('ascii', 1, 4) === 'PNG') return [b.readUInt32BE(16), b.readUInt32BE(20)];
    if (b[0] === 0xff && b[1] === 0xd8) {
      let offset = 2;
      while (offset + 9 < b.length) {
        if (b[offset] !== 0xff) break;
        const marker = b[offset + 1];
        if ([0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf].includes(marker)) return [b.readUInt16BE(offset + 7), b.readUInt16BE(offset + 5)];
        offset += 2 + b.readUInt16BE(offset + 2);
      }
    }
  }
  throw new Error(`Cannot determine image dimensions: ${src}`);
}

function enhance(html, filename) {
  const base = siteUrl();
  const url = base && `${base}/${filename === 'index.html' ? '' : filename}`;
  const title = plain((html.match(/<title>([\s\S]*?)<\/title>/i) || [])[1] || 'Gehwol');
  const heading = plain((html.match(/<h1\b[^>]*>([\s\S]*?)<\/h1>/i) || [])[1] || title);
  const intro = content.categories[filename];
  if (intro) {
    html = html.replace(/(<h1\b[^>]*>[\s\S]*?<\/h1>)/i, `$1\n<p class="category__intro">${escape(intro)}</p>`);
    html = html.replace(/<meta\b[^>]*name="description"[^>]*>/i, `<meta name="description" content="${escape(intro.slice(0, 157) + (intro.length > 157 ? '…' : ''))}">`);
  }
  let description = plain((html.match(/<meta\b[^>]*name="description"[^>]*content="([^"]*)"/i) || [])[1] || '');
  if (!description) {
    const subtitle = plain((html.match(/<p\b[^>]*class="product-detail__subtitle"[^>]*>([\s\S]*?)<\/p>/i) || [])[1] || 'Oficiālā izplatītāja piedāvājums Latvijā.');
    description = `${heading} — ${subtitle}`.slice(0, 160);
    html = html.replace(/<\/head>/i, `<meta name="description" content="${escape(description)}"></head>`);
  }
  let firstProductImage = true;
  html = html.replace(/<img\b[^>]*>/gi, tag => {
    const src = (tag.match(/\bsrc="([^"]+)"/) || [])[1];
    if (!src || /^(?:https?:|data:)/.test(src)) return tag;
    const [width, height] = imageSize(src);
    let attrs = '';
    if (!/\bwidth=/.test(tag)) attrs += ` width="${width}"`;
    if (!/\bheight=/.test(tag)) attrs += ` height="${height}"`;
    const hero = /hero__img/.test(tag);
    const product = /^produkts-/.test(filename) && firstProductImage;
    if (product) firstProductImage = false;
    if (!/\bloading=/.test(tag)) attrs += ` loading="${hero || product ? 'eager' : 'lazy'}"`;
    if (!/\bdecoding=/.test(tag)) attrs += ' decoding="async"';
    if (hero) attrs += ' fetchpriority="high"';
    return tag.replace(/\s*\/?>(\s*)$/, `${attrs}>$1`);
  });
  if (!base) return html;
  const organization = { '@type': 'Organization', '@id': `${base}/#organization`, name: config.organizationName, url: `${base}/`, telephone: '+37127055700', email: 'versia@load.lv', address: { '@type': 'PostalAddress', streetAddress: 'Dzērbenes 14, 306.C', addressLocality: 'Rīga', postalCode: 'LV-1006', addressCountry: 'LV' } };
  const graph = [organization, { '@type': 'WebSite', '@id': `${base}/#website`, url: `${base}/`, name: config.siteName, inLanguage: 'lv', publisher: { '@id': organization['@id'] } }];
  const crumbs = [{ '@type': 'ListItem', position: 1, name: 'Sākums', item: `${base}/` }];
  if (filename !== 'index.html') {
    const nav = (html.match(/<nav\b[^>]*class="category__breadcrumbs"[^>]*>([\s\S]*?)<\/nav>/) || [])[1] || '';
    const categoryLinks = [...nav.matchAll(/<a\b[^>]*href="([^"]+)"[^>]*>([^<]*)<\/a>/g)].filter(m => m[1] !== 'index.html' && !m[1].includes('#'));
    const category = categoryLinks.at(-1);
    if (category) crumbs.push({ '@type': 'ListItem', position: 2, name: plain(category[2]), item: `${base}/${category[1]}` });
    crumbs.push({ '@type': 'ListItem', position: crumbs.length + 1, name: heading, item: url });
    graph.push({ '@type': 'BreadcrumbList', '@id': `${url}#breadcrumbs`, itemListElement: crumbs });
  }
  const image = (html.match(/<img\b[^>]*src="([^"]+)"/) || [])[1];
  const imageUrl = image && new URL(image, `${base}/`).href;
  graph.push({ '@type': intro ? 'CollectionPage' : 'WebPage', name: heading, description, url, inLanguage: 'lv', isPartOf: { '@id': `${base}/#website` } });
  const metadata = `<link rel="canonical" href="${escape(url)}">\n<meta property="og:type" content="${/^(raksts|jaunums)-/.test(filename) ? 'article' : 'website'}">\n<meta property="og:locale" content="lv_LV">\n<meta property="og:site_name" content="${escape(config.siteName)}">\n<meta property="og:title" content="${escape(title)}">\n<meta property="og:description" content="${escape(description)}">\n<meta property="og:url" content="${escape(url)}">\n${imageUrl ? `<meta property="og:image" content="${escape(imageUrl)}">\n<meta property="og:image:alt" content="${escape(heading)}">` : ''}\n<meta name="twitter:card" content="summary">\n<script type="application/ld+json">${JSON.stringify({ '@context': 'https://schema.org', '@graph': graph }).replace(/</g, '\\u003c')}</script>`;
  return html.replace(/<\/head>/i, `${metadata}\n</head>`);
}

function seoTransform() {
  return new Transform({ objectMode: true, transform(file, encoding, callback) {
    try { file.contents = Buffer.from(enhance(file.contents.toString(), path.basename(file.path))); callback(null, file); } catch (error) { callback(error); }
  } });
}

function writeAssets(directory) {
  const base = siteUrl();
  if (!base) throw new Error('Set siteUrl in seo.config.json or SITE_URL before generating sitemap and robots.txt');
  const files = fs.readdirSync(directory).filter(file => file.endsWith('.html')).sort();
  const urls = files.map(file => `<url><loc>${escape(`${base}/${file === 'index.html' ? '' : file}`)}</loc></url>`);
  fs.writeFileSync(path.join(directory, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join('\n')}\n</urlset>\n`);
  // Admin pages use noindex; allow crawling so search engines can see it.
  fs.writeFileSync(path.join(directory, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${base}/sitemap.xml\n`);
}

module.exports = { seoTransform, enhance, writeAssets };
