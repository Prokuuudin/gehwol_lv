const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const productsFile = path.join(root, 'php', 'data', 'products.json');
const productsSource = fs.readFileSync(productsFile, 'utf8');
const products = JSON.parse(productsSource);

const claimPattern = /ārst|sēn|diab|neiroderm|brūc|plais.{0,20}(?:dzied|sadz)|iekais|antibakter|antimik|pretmikro|pretsēn|dezinfic|sāp|asinsrit|asins cirkul|infek|terap|profilak|novērš|pasargā no|dermatoloģ|medicīn|mikrocirkul|reģener|aizsargspēj|veselīgu nagu|pārragošanās|kārp|varžac|tulzn/iu;

function lineOf(haystack, needle, from = 0) {
  const index = haystack.indexOf(needle, from);
  if (index === -1) return null;
  return haystack.slice(0, index).split('\n').length;
}

function sentences(value) {
  return String(value)
    .replace(/<[^>]+>/g, ' ')
    .replace(/\\n/g, ' ')
    .split(/(?<=[.!?])\s+|\n+/u)
    .map((item) => item.trim())
    .filter((item) => item && claimPattern.test(item));
}

function evidenceFor(claims) {
  const joined = claims.join(' ').toLowerCase();
  const evidence = [];
  if (/medicīn|varžac|kārp|sāp|koriģ|deform/.test(joined)) evidence.push('produkta MDR statuss, paredzētais nolūks, marķējums un lietošanas instrukcija');
  if (/ārst|sēn|infek|iekais|brūc|dzied|terap|profilak|antibakter|antimik|pretmikro|pretsēn|dezinfic/.test(joined)) evidence.push('ražotāja apstiprināts apgalvojums un pamatojošie klīniskie/efektivitātes dati');
  if (/diab|neiroderm|dermatoloģ/.test(joined)) evidence.push('ražotāja dokumentēts piemērotības vai testēšanas pamatojums konkrētajai lietotāju grupai');
  if (/asinsrit|cirkul|mikrocirkul|reģener|pārragošanās|aizsargspēj|veselīgu nagu/.test(joined)) evidence.push('ražotāja produkta dokumentācija un apgalvojuma pierādījumi');
  return [...new Set(evidence)].join('; ') || 'ražotāja aktuālā produkta dokumentācija un apgalvojuma pierādījumi';
}

const entries = [];
for (const product of products) {
  const claims = sentences(product.description);
  if (!claims.length) continue;
  const marker = `\"id\": ${product.id}`;
  const line = lineOf(productsSource, marker);
  entries.push({
    page: `produkts-${product.id}.html`,
    file: 'php/data/products.json',
    line,
    product: product.name,
    claims,
    reason: 'Formulējums var tikt uztverts kā veselības, ārstnieciskas iedarbības, slimības profilakses vai medicīniskas piemērotības apgalvojums. Pirms publicēšanas jāpārbauda produkta kategorija un tas, vai apgalvojums precīzi ietilpst ražotāja apstiprinātajā paredzētajā lietojumā un pierādījumu apjomā.',
    evidence: evidenceFor(claims),
  });
}

const extraFiles = [
  ['index.html (sadaļa “Kas ir Gehwol”)', 'src/html/blocks/about.html', 'Vispārīga informācija'],
  ['raksts-6.html', 'src/html/raksts-6.html', 'Raksts “Sausas pēdu ādas kopšana”'],
];

for (const [page, relative, product] of extraFiles) {
  const source = fs.readFileSync(path.join(root, relative), 'utf8');
  const lines = source.split(/\r?\n/);
  lines.forEach((lineText, index) => {
    const claims = sentences(lineText.replace(/\\n/g, ' '));
    if (!claims.length) return;
    entries.push({
      page,
      file: relative,
      line: index + 1,
      product,
      claims,
      reason: 'Redakcionāls teksts satur medicīnisku, ārstniecisku vai veselības apgalvojumu. Jāpārbauda gan faktu avots, gan tas, vai produkta reklāmas kontekstā formulējums nepārsniedz apstiprinātos apgalvojumus.',
      evidence: 'ražotāja oficiālais materiāls, attiecīgā produkta dokumentācija un, ja apgalvojums balstīts pētījumā, pilns pētījuma avots',
    });
  });
}

const now = '2026-09-28';
let output = `# GEHWOL Latvia juridiski nozīmīgo apgalvojumu audits\n\n`;
output += `Pārbaudes datums: ${now}\n\n`;
output += `Šis ir skrīninga ziņojums, nevis secinājums, ka uzskaitītie apgalvojumi ir nepatiesi vai aizliegti. Apgalvojumi nav automātiski mainīti vai dzēsti. Tie jāsalīdzina ar ražotāja aktuālo marķējumu, lietošanas instrukciju, paredzēto nolūku un pierādījumu dokumentāciju. GEHWOL Vācijas tīmekļvietne var palīdzēt atrast oficiālu produkta aprakstu, taču tā pati par sevi neaizstāj konkrētā Latvijā izplatītā produkta regulatīvo dokumentāciju.\n\n`;
output += `## Juridiskais konteksts\n\n`;
output += `Kosmētikas līdzekļu apgalvojumiem piemērojams Regulas (EK) Nr. 1223/2009 20. pants; medicīnisko ierīču apgalvojumiem — Regulas (ES) 2017/745 7. pants. Visam komerciālajam saturam piemērojams Negodīgas komercprakses aizlieguma likums. Produkta kategorija nav pieņemta tikai pēc nosaukuma: tā jāpārbauda dokumentācijā.\n\n`;
output += `## Kopsavilkums\n\nAtrasti ${entries.length} produktu vai satura ieraksti, kuros ir vismaz viens pārbaudāms apgalvojums.\n\n`;
output += `## Pārbaudāmie apgalvojumi\n\n`;

entries.forEach((entry, index) => {
  output += `### ${index + 1}. ${entry.product}\n\n`;
  output += `- Lapa: \`${entry.page}\`\n`;
  output += `- Avota fails un vieta: \`${entry.file}:${entry.line ?? 'rinda nav noteikta'}\`\n`;
  output += `- Sākotnējais formulējums:\n`;
  entry.claims.forEach((claim) => { output += `  - “${claim.replace(/\s+/g, ' ')}”\n`; });
  output += `- Kāpēc jāpārbauda: ${entry.reason}\n`;
  output += `- Nepieciešamais apstiprinājums: ${entry.evidence}.\n\n`;
});

output += `## Īpašniekam veicamā pārbaude\n\n`;
output += `1. Katram produktam apstiprināt kategoriju: kosmētikas līdzeklis, medicīniska ierīce vai cita prece.\n`;
output += `2. Salīdzināt tekstu ar tieši tā paša produkta un tirgus aktuālo marķējumu un lietošanas instrukciju.\n`;
output += `3. Saņemt no ražotāja vai atbildīgās personas rakstisku apstiprinājumu apgalvojumiem par slimībām, diabētu, neirodermītu, brūču/plaisu dzīšanu, sēnīšu infekciju, pretmikrobu iedarbību, asinsriti un sāpēm.\n`;
output += `4. Pārbaudīt pētījumu atsauces un saglabāt pilnus pierādījumus, ne tikai reklāmas materiālu.\n`;
output += `5. Pēc apstiprināšanas izlabot arī primāro datu avotu \`php/data/products.json\`, lai nākamā statisko lapu ģenerēšana neatjaunotu vecos tekstus.\n`;

fs.writeFileSync(path.join(root, 'LEGAL_CLAIMS_AUDIT.md'), output, 'utf8');
console.log(`Wrote LEGAL_CLAIMS_AUDIT.md with ${entries.length} entries.`);
