'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');
const { acquireLock, deploy } = require('../scripts/deploy-plesk');

function write(root, rel, contents) {
  const target = path.join(root, rel);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, contents);
}

function fixture() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'gehwol-deploy-test-'));
  const release = path.join(root, 'release');
  const production = path.join(root, 'httpdocs');
  for (const [rel, contents] of [
    ['.htaccess', 'new htaccess'],
    ['.user.ini', 'new user ini'],
    ['php/site.php', '<?php // new'],
    ['css/changed.css', 'new css'],
    ['js/new.js', 'new js'],
  ]) write(release, rel, contents);
  for (const [rel, contents] of [
    ['.htaccess', 'old htaccess'],
    ['.user.ini', 'old user ini'],
    ['php/site.php', '<?php // old'],
    ['css/changed.css', 'old css'],
    ['js/removed.js', 'stale'],
    ['php/data/products.json', '{"production":true}'],
    ['php/data/backups/products-1.json', '{"backup":true}'],
    ['uploads/products/live.jpg', Buffer.from([0, 1, 2, 255])],
  ]) write(production, rel, contents);
  return { root, release, production };
}

function snapshot(root) {
  const result = {};
  function visit(current = '') {
    const dir = path.join(root, current);
    if (!fs.existsSync(dir)) return;
    for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
      const rel = path.join(current, item.name);
      if (item.isDirectory()) visit(rel);
      else result[rel.split(path.sep).join('/')] = fs.readFileSync(path.join(root, rel)).toString('base64');
    }
  }
  visit();
  return result;
}

function run(input, overrides = {}) {
  return deploy({
    source: input.release,
    destination: input.production,
    build: () => {},
    commit: 'test-commit',
    logger: () => {},
    ...overrides,
  });
}

test('adds, updates and removes deployable files while preserving runtime data byte-for-byte', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  const dataBefore = fs.readFileSync(path.join(input.production, 'php/data/products.json'));
  const backupBefore = fs.readFileSync(path.join(input.production, 'php/data/backups/products-1.json'));
  const uploadBefore = fs.readFileSync(path.join(input.production, 'uploads/products/live.jpg'));

  const plan = run(input);

  assert.equal(fs.readFileSync(path.join(input.production, 'js/new.js'), 'utf8'), 'new js');
  assert.equal(fs.readFileSync(path.join(input.production, 'css/changed.css'), 'utf8'), 'new css');
  assert.equal(fs.existsSync(path.join(input.production, 'js/removed.js')), false);
  assert.deepEqual(fs.readFileSync(path.join(input.production, 'php/data/products.json')), dataBefore);
  assert.deepEqual(fs.readFileSync(path.join(input.production, 'php/data/backups/products-1.json')), backupBefore);
  assert.deepEqual(fs.readFileSync(path.join(input.production, 'uploads/products/live.jpg')), uploadBefore);
  assert.equal(fs.readFileSync(path.join(input.production, '.htaccess'), 'utf8'), 'new htaccess');
  assert.equal(fs.readFileSync(path.join(input.production, '.user.ini'), 'utf8'), 'new user ini');
  assert.deepEqual(plan.added, ['js/new.js']);
  assert(plan.updated.includes('css/changed.css'));
  assert(plan.removed.includes('js/removed.js'));
});

test('runtime versions from the release never replace production data or uploads', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  write(input.release, 'php/data/.htaccess', 'release protection');
  write(input.release, 'uploads/.htaccess', 'release upload protection');
  write(input.production, 'php/data/.htaccess', 'production protection');
  write(input.production, 'uploads/.htaccess', 'production upload protection');

  run(input);

  assert.equal(fs.readFileSync(path.join(input.production, 'php/data/.htaccess'), 'utf8'), 'production protection');
  assert.equal(fs.readFileSync(path.join(input.production, 'uploads/.htaccess'), 'utf8'), 'production upload protection');
});

test('a release containing source JSON is rejected and cannot replace production JSON', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  write(input.release, 'php/data/products.json', '{"source":true}');
  const before = snapshot(input.production);
  assert.throws(() => run(input), /Runtime JSON must not be deployed/);
  assert.deepEqual(snapshot(input.production), before);
});

test('a build failure leaves the destination unchanged', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  const before = snapshot(input.production);
  assert.throws(() => run(input, { build: () => { throw new Error('synthetic build failure'); } }), /build failure/);
  assert.deepEqual(snapshot(input.production), before);
});

test('a concurrent deployment is blocked by the exclusive lock', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  const lockPath = path.join(input.root, '.gehwol-deploy.lock');
  const release = acquireLock(lockPath);
  t.after(release);
  assert.throws(() => run(input, { lockPath }), /Another deployment is running/);
});

test('dry run reports the plan without changing the destination', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  const before = snapshot(input.production);
  const plan = run(input, { dryRun: true });
  assert(plan.added.includes('js/new.js'));
  assert.deepEqual(snapshot(input.production), before);
});

test('development-only content in a release is rejected before deployment', (t) => {
  const input = fixture();
  t.after(() => fs.rmSync(input.root, { recursive: true, force: true }));
  write(input.release, 'node_modules/private.js', 'secret');
  const before = snapshot(input.production);
  assert.throws(() => run(input), /Development\/private file/);
  assert.deepEqual(snapshot(input.production), before);
});
