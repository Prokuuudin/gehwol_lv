#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const DEFAULT_SOURCE = path.join(ROOT, 'release');
const DEFAULT_DESTINATION = '/var/www/vhosts/gehwol.lv/httpdocs';
const PERSISTENT_ROOTS = ['php/data', 'uploads'];
const PLATFORM_ROOTS = ['.well-known'];
const FORBIDDEN_RELEASE_PATH = /(^|\/)(\.git|node_modules|tests|src|plans|specs|backups)(\/|$)|\.(docx?|md|log|lock|tmp|env|sql|zip|bak)$/i;

function slash(value) {
  return value.split(path.sep).join('/');
}

function isInside(rel, roots) {
  return roots.some((root) => rel === root || rel.startsWith(`${root}/`));
}

function parseArgs(argv) {
  const options = {
    dryRun: false,
    destination: process.env.GEHWOL_DEPLOY_DESTINATION || DEFAULT_DESTINATION,
    source: process.env.GEHWOL_DEPLOY_SOURCE || DEFAULT_SOURCE,
  };

  for (let i = 0; i < argv.length; i += 1) {
    if (argv[i] === '--dry-run') options.dryRun = true;
    else if (argv[i] === '--destination' && argv[i + 1]) options.destination = argv[++i];
    else if (argv[i] === '--source' && argv[i + 1]) options.source = argv[++i];
    else throw new Error(`Unknown or incomplete argument: ${argv[i]}`);
  }
  return options;
}

function walk(root, current = '', entries = new Map(), skip = () => false) {
  const directory = path.join(root, current);
  if (!fs.existsSync(directory)) return entries;

  for (const item of fs.readdirSync(directory, { withFileTypes: true })) {
    const rel = slash(path.join(current, item.name));
    if (skip(rel)) continue;
    const full = path.join(root, rel);
    const stat = fs.lstatSync(full);
    if (stat.isSymbolicLink()) entries.set(rel, { full, stat, type: 'symlink' });
    else if (stat.isDirectory()) walk(root, rel, entries, skip);
    else if (stat.isFile()) entries.set(rel, { full, stat, type: 'file' });
    else throw new Error(`Unsupported filesystem entry: ${full}`);
  }
  return entries;
}

function sameFile(left, right) {
  if (left.type !== 'file' || right.type !== 'file' || left.stat.size !== right.stat.size) return false;
  const a = fs.openSync(left.full, 'r');
  const b = fs.openSync(right.full, 'r');
  const leftBuffer = Buffer.allocUnsafe(64 * 1024);
  const rightBuffer = Buffer.allocUnsafe(64 * 1024);
  try {
    while (true) {
      const leftBytes = fs.readSync(a, leftBuffer, 0, leftBuffer.length, null);
      const rightBytes = fs.readSync(b, rightBuffer, 0, rightBuffer.length, null);
      if (leftBytes !== rightBytes) return false;
      if (leftBytes === 0) return true;
      if (!leftBuffer.subarray(0, leftBytes).equals(rightBuffer.subarray(0, rightBytes))) return false;
    }
  } finally {
    fs.closeSync(a);
    fs.closeSync(b);
  }
}

function validateRelease(source, entries) {
  if (!fs.existsSync(source) || !fs.statSync(source).isDirectory()) {
    throw new Error(`Release directory does not exist: ${source}`);
  }
  for (const [rel, entry] of entries) {
    if (entry.type !== 'file') throw new Error(`Release must not contain symlinks: ${rel}`);
    if (FORBIDDEN_RELEASE_PATH.test(rel)) throw new Error(`Development/private file in release: ${rel}`);
    if (/^php\/data\/.*\.json$/i.test(rel)) throw new Error(`Runtime JSON must not be deployed: ${rel}`);
    if (/(^|\/)(composer|package(?:-lock)?)\.json$/i.test(rel)) throw new Error(`Development manifest in release: ${rel}`);
  }
  for (const required of ['.htaccess', '.user.ini', 'php/site.php']) {
    if (!entries.has(required)) throw new Error(`Incomplete release, missing ${required}`);
  }
}

function makePlan(source, destination) {
  const sourceEntries = walk(source);
  validateRelease(source, sourceEntries);
  const desired = new Map([...sourceEntries].filter(([rel]) => !isInside(rel, PERSISTENT_ROOTS)));
  const current = walk(
    destination,
    '',
    new Map(),
    (rel) => isInside(rel, PERSISTENT_ROOTS) || isInside(rel, PLATFORM_ROOTS),
  );
  const added = [];
  const updated = [];
  const removed = [];
  const unchanged = [];

  for (const [rel, entry] of desired) {
    const existing = current.get(rel);
    if (!existing) added.push(rel);
    else if (sameFile(entry, existing)) unchanged.push(rel);
    else updated.push(rel);
  }
  for (const rel of current.keys()) {
    if (!desired.has(rel)) removed.push(rel);
  }
  for (const list of [added, updated, removed, unchanged]) list.sort();
  return { source, destination, desired, added, updated, removed, unchanged };
}

function ensureDirectory(root, relDirectory) {
  if (!relDirectory || relDirectory === '.') return;
  let current = root;
  for (const segment of slash(relDirectory).split('/')) {
    current = path.join(current, segment);
    if (fs.existsSync(current) && !fs.lstatSync(current).isDirectory()) fs.rmSync(current, { force: true });
    if (!fs.existsSync(current)) fs.mkdirSync(current);
  }
}

function atomicCopy(source, destination, mode) {
  if (fs.existsSync(destination) && fs.lstatSync(destination).isDirectory()) {
    fs.rmSync(destination, { recursive: true, force: true });
  }
  const temporary = path.join(
    path.dirname(destination),
    `.gehwol-deploy-${process.pid}-${Math.random().toString(16).slice(2)}.tmp`,
  );
  try {
    fs.copyFileSync(source, temporary);
    fs.chmodSync(temporary, mode & 0o777);
    try {
      fs.renameSync(temporary, destination);
    } catch (error) {
      // POSIX rename replaces atomically. Windows needs the existing file removed first.
      if (!['EEXIST', 'EPERM'].includes(error.code)) throw error;
      fs.rmSync(destination, { force: true });
      fs.renameSync(temporary, destination);
    }
  } finally {
    if (fs.existsSync(temporary)) fs.rmSync(temporary, { force: true });
  }
}

function pruneEmptyDirectories(root, current = '') {
  const full = path.join(root, current);
  if (!fs.existsSync(full) || !fs.lstatSync(full).isDirectory()) return;
  for (const item of fs.readdirSync(full, { withFileTypes: true })) {
    const rel = slash(path.join(current, item.name));
    if (item.isDirectory() && !isInside(rel, PERSISTENT_ROOTS) && !isInside(rel, PLATFORM_ROOTS)) {
      pruneEmptyDirectories(root, rel);
    }
  }
  if (current && !isInside(current, PERSISTENT_ROOTS) && !isInside(current, PLATFORM_ROOTS)
      && fs.readdirSync(full).length === 0) fs.rmdirSync(full);
}

function applyPlan(plan) {
  fs.mkdirSync(plan.destination, { recursive: true });
  const desiredPaths = [...plan.desired.keys()];
  const blockers = new Set(plan.removed.filter((rel) => desiredPaths.some((wanted) => wanted.startsWith(`${rel}/`))));
  for (const rel of blockers) {
    fs.rmSync(path.join(plan.destination, rel), { recursive: true, force: true });
  }
  for (const rel of [...plan.added, ...plan.updated]) {
    const entry = plan.desired.get(rel);
    const destination = path.join(plan.destination, rel);
    ensureDirectory(plan.destination, path.dirname(rel));
    atomicCopy(entry.full, destination, entry.stat.mode);
  }
  // Delete only after all new and changed files are safely in place.
  for (const rel of plan.removed.filter((item) => !blockers.has(item)).sort((a, b) => b.length - a.length)) {
    const target = path.join(plan.destination, rel);
    fs.rmSync(target, { force: true });
  }
  pruneEmptyDirectories(plan.destination);
}

function acquireLock(lockPath) {
  try {
    const fd = fs.openSync(lockPath, 'wx', 0o600);
    fs.writeFileSync(fd, `${JSON.stringify({ pid: process.pid, started: new Date().toISOString() })}\n`);
    fs.closeSync(fd);
  } catch (error) {
    if (error.code === 'EEXIST') throw new Error(`Another deployment is running (lock: ${lockPath})`);
    throw error;
  }
  let released = false;
  return () => {
    if (!released) {
      released = true;
      fs.rmSync(lockPath, { force: true });
    }
  };
}

function gitCommit(root) {
  const result = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : 'unknown';
}

function buildRelease() {
  const result = spawnSync(process.execPath, [path.join(ROOT, 'scripts', 'build-release.js')], {
    cwd: ROOT,
    stdio: 'inherit',
  });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`Production release build failed with exit code ${result.status}`);
}

function logChanges(label, files, logger) {
  logger(`${label}: ${files.length}`);
  const limit = 30;
  for (const file of files.slice(0, limit)) logger(`  ${file}`);
  if (files.length > limit) logger(`  ... and ${files.length - limit} more`);
}

function deploy(options = {}) {
  const logger = options.logger || console.log;
  const source = path.resolve(options.source || DEFAULT_SOURCE);
  const destination = path.resolve(options.destination || DEFAULT_DESTINATION);
  if (path.basename(destination) !== 'httpdocs' || destination === path.parse(destination).root) {
    throw new Error(`Refusing unsafe destination (expected a directory named httpdocs): ${destination}`);
  }
  if (fs.existsSync(destination) && fs.lstatSync(destination).isSymbolicLink()) {
    throw new Error(`Refusing symlink destination: ${destination}`);
  }
  if (source === destination || source.startsWith(`${destination}${path.sep}`)
      || destination.startsWith(`${source}${path.sep}`)) {
    throw new Error('Release source and production destination must not contain each other');
  }
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  const lockPath = options.lockPath || path.join(path.dirname(destination), '.gehwol-deploy.lock');
  const releaseLock = acquireLock(lockPath);
  const commit = options.commit || gitCommit(ROOT);
  logger(`Deploy started: ${new Date().toISOString()}`);
  logger(`Commit: ${commit}`);
  logger(`Destination: ${destination}`);
  logger(`Persistent runtime paths: php/data/**, uploads/** (preserved)`);

  try {
    try {
      (options.build || buildRelease)();
      logger('Build result: success');
    } catch (error) {
      logger(`Build result: failed (${error.message})`);
      throw error;
    }
    const plan = makePlan(source, destination);
    logChanges('Add', plan.added, logger);
    logChanges('Update', plan.updated, logger);
    logChanges('Remove', plan.removed, logger);
    if (options.dryRun) {
      logger('Runtime preservation: php/data/** and uploads/** were not read or modified');
      logger('Deployment result: dry run; production destination was not changed');
      return plan;
    }
    applyPlan(plan);
    logger('Runtime preservation: php/data/** and uploads/** were not read or modified');
    logger(`Deployment result: success (${plan.added.length} added, ${plan.updated.length} updated, ${plan.removed.length} removed)`);
    logger('Deploy completed successfully');
    return plan;
  } catch (error) {
    logger(`Deployment failed: ${error.message}`);
    throw error;
  } finally {
    releaseLock();
  }
}

if (require.main === module) {
  try {
    deploy(parseArgs(process.argv.slice(2)));
  } catch (error) {
    console.error(`ERROR: ${error.message}`);
    process.exitCode = 1;
  }
}

module.exports = { acquireLock, applyPlan, deploy, makePlan, parseArgs };
