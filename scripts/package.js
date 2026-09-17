const fs = require('fs');
const os = require('os');
const path = require('path');
const { execSync } = require('child_process');

const ROOT_DIR = path.join(__dirname, '..');
const FRONTEND_DIR = path.join(ROOT_DIR, 'pt_frontend');
const TEMP_RELEASE_DIR = path.join(ROOT_DIR, 'release-temp');
const ZIP_PATH = path.join(ROOT_DIR, 'release.zip');

// ---------------------------------------------------------------------------
// Security filter — what must NEVER end up in the published archive.
// ---------------------------------------------------------------------------
const SESSION_DIR = 'sessions';
// The deny-all guard is part of the product and MUST ship; session payloads are
// the opposite (they contain a PLAINTEXT captcha answer keyed by session id).
const SESSION_ALLOWED_FILES = new Set(['.htaccess']);

// Returns true when the entry (relative to pt_api/) should be EXCLUDED.
// `relative` uses the platform separator, as produced by path.relative().
function shouldSkipApiEntry(relative) {
  if (relative === '') return false; // the pt_api directory itself

  const parts = relative.split(path.sep);
  const base = parts[parts.length - 1];

  // 1. Local credentials and the install lock file.
  //    `.env.example` is a template, not a secret — it must ship.
  if (base === '.env.example') return false;
  if (base === '.env' || base.startsWith('.env.')) return true;
  if (base === '.installed') return true;

  // 2. Runtime session data. Compare by path SEGMENT (not string prefix) so a
  //    sibling directory such as `sessions-old/` is not silently dropped.
  if (parts[0] === SESSION_DIR) {
    if (parts.length === 1) return false; // keep the (now empty) directory
    return !(parts.length === 2 && SESSION_ALLOWED_FILES.has(parts[1]));
  }

  return false;
}

// ---------------------------------------------------------------------------
// Post-build verification — read the archive back and prove it is clean.
// ---------------------------------------------------------------------------
const REQUIRED_ENTRIES = [
  '.htaccess',
  'index.html',
  'upload/.gitkeep',
  'pt_api/install.php',
  'pt_api/config.php',
  'pt_api/.env.example',
  `pt_api/${SESSION_DIR}/.htaccess`,
];

const FORBIDDEN_ENTRIES = [
  { pattern: /(^|\/)\.env$/, why: 'local database credentials' },
  { pattern: /(^|\/)\.env\.(?!example$)[^/]+$/, why: 'local environment override' },
  { pattern: /(^|\/)\.installed$/, why: 'install lock file' },
  {
    pattern: new RegExp(`(^|/)${SESSION_DIR}/(?!\\.htaccess$)[^/]+$`),
    why: 'PHP session payload (plaintext captcha)',
  },
];

// Lists entry names inside a ZIP without extracting it.
// Entry names are normalised to forward slashes.
function listZipEntries(zipPath) {
  const listFile = path.join(os.tmpdir(), 'pt-release-entries.txt');
  try {
    if (process.platform === 'win32') {
      // Passed through env vars to avoid any quoting problems in the shell.
      const script = [
        'Add-Type -AssemblyName System.IO.Compression.FileSystem',
        '$z=[IO.Compression.ZipFile]::OpenRead($env:PT_ZIP)',
        '[IO.File]::WriteAllLines($env:PT_LIST, [string[]]($z.Entries | ForEach-Object { $_.FullName }))',
        '$z.Dispose()',
      ].join('; ');
      execSync(`powershell -NoProfile -Command "${script}"`, {
        stdio: 'inherit',
        env: { ...process.env, PT_ZIP: zipPath, PT_LIST: listFile },
      });
    } else {
      execSync(`unzip -Z1 "${zipPath}" > "${listFile}"`, { stdio: 'inherit' });
    }
    return fs
      .readFileSync(listFile, 'utf8')
      .replace(/^\uFEFF/, '')
      .split(/\r?\n/)
      .map((line) => line.trim().replace(/\\/g, '/'))
      .filter(Boolean);
  } finally {
    if (fs.existsSync(listFile)) fs.unlinkSync(listFile);
  }
}

// Best-effort removal of a directory. A failed cleanup must NOT turn an already
// successful release into a reported failure, so the error is downgraded to a
// warning and the directory is simply left behind.
function safeRemoveDir(dir) {
  try {
    if (fs.existsSync(dir)) {
      fs.rmSync(dir, { recursive: true, force: true });
    }
    return true;
  } catch (err) {
    console.warn(`   ⚠️  Could not remove ${dir}: ${err.message}`);
    console.warn('      The release archive is unaffected — remove it manually.');
    return false;
  }
}

function verifyPackage(zipPath) {
  console.log('\n🔍 7. Verifying package contents...');

  let entries;
  try {
    entries = listZipEntries(zipPath);
  } catch (err) {
    console.error(`   ⚠️  Could not read the archive back: ${err.message}`);
    return false;
  }

  const set = new Set(entries);
  let ok = true;

  for (const required of REQUIRED_ENTRIES) {
    const present = set.has(required);
    if (!present) ok = false;
    console.log(`   ${present ? '✅' : '❌'} must be present: ${required}`);
  }

  for (const { pattern, why } of FORBIDDEN_ENTRIES) {
    const leaked = entries.filter((entry) => pattern.test(entry));
    if (leaked.length) ok = false;
    const detail = leaked.length ? ` — leaked: ${leaked.join(', ')}` : '';
    console.log(`   ${leaked.length ? '❌' : '✅'} must be absent (${why})${detail}`);
  }

  console.log(`   ${entries.length} entries total.`);
  return ok;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  console.log('🚀 Starting PeachtreesCMS release package generation...');

  // 1. Build the frontend (skippable for packaging-only / CI reuse)
  if (process.env.PT_SKIP_BUILD === '1') {
    console.log('\n📦 1. Building React frontend... SKIPPED (PT_SKIP_BUILD=1)');
  } else {
    console.log('\n📦 1. Building React frontend...');
    try {
      execSync('pnpm install', { cwd: FRONTEND_DIR, stdio: 'inherit' });
      execSync('pnpm build', { cwd: FRONTEND_DIR, stdio: 'inherit' });
    } catch (err) {
      console.error('❌ Frontend build failed:', err.message);
      process.exit(1);
    }
  }

  let exitCode = 0;

  try {
    // 2. Clean and create temporary staging directory
    console.log('\n📁 2. Preparing staging area...');
    // A stale staging directory would silently pollute the archive, so a failed
    // removal here is FATAL (unlike the best-effort cleanup at the end).
    if (fs.existsSync(TEMP_RELEASE_DIR) && !safeRemoveDir(TEMP_RELEASE_DIR)) {
      throw new Error(`Stale staging directory could not be removed: ${TEMP_RELEASE_DIR}`);
    }
    fs.mkdirSync(TEMP_RELEASE_DIR, { recursive: true });

    // 3. Copy frontend build assets
    console.log('🚚 3. Copying frontend built assets...');
    const distDir = path.join(FRONTEND_DIR, 'dist');
    if (!fs.existsSync(distDir)) {
      throw new Error(
        `Frontend build output not found: ${distDir}\n` +
          '   Run without PT_SKIP_BUILD=1 so the frontend is built first.'
      );
    }
    fs.cpSync(distDir, TEMP_RELEASE_DIR, { recursive: true });

    // The build output mirrors pt_frontend/public/, which may carry local
    // attachments. Ship an EMPTY upload directory instead (only .gitkeep, so
    // the directory survives archiving).
    const tempUploadDir = path.join(TEMP_RELEASE_DIR, 'upload');
    // Same reasoning as step 2: if this fails, local attachments from
    // pt_frontend/public/upload would ride along into the release.
    if (fs.existsSync(tempUploadDir) && !safeRemoveDir(tempUploadDir)) {
      throw new Error(`Could not clear ${tempUploadDir} — it may still hold local attachments.`);
    }
    fs.mkdirSync(tempUploadDir, { recursive: true });
    fs.writeFileSync(path.join(tempUploadDir, '.gitkeep'), '');

    // 4. Copy backend API (pt_api) with exclusion filter
    console.log('🚚 4. Copying backend API and applying security filters...');
    const apiSrcDir = path.join(ROOT_DIR, 'pt_api');
    const apiDestDir = path.join(TEMP_RELEASE_DIR, 'pt_api');

    fs.mkdirSync(apiDestDir, { recursive: true });

    fs.cpSync(apiSrcDir, apiDestDir, {
      recursive: true,
      filter: (src) => !shouldSkipApiEntry(path.relative(apiSrcDir, src)),
    });

    // 5. Copy root files (.htaccess, LICENSE, README, SQL files)
    console.log('🚚 5. Copying root configuration and documentation...');
    const filesToCopy = ['.htaccess', 'LICENSE', 'README.md'];

    for (const file of filesToCopy) {
      const srcFile = path.join(ROOT_DIR, file);
      if (fs.existsSync(srcFile)) {
        fs.cpSync(srcFile, path.join(TEMP_RELEASE_DIR, file));
      }
    }

    // 6. Compress staging area into release.zip
    console.log('\n🗜️ 6. Compressing package into release.zip...');
    if (fs.existsSync(ZIP_PATH)) {
      fs.unlinkSync(ZIP_PATH);
    }

    if (process.platform === 'win32') {
      // Use PowerShell to create the ZIP
      const psCommand = `Compress-Archive -Path "${TEMP_RELEASE_DIR}\\*" -DestinationPath "${ZIP_PATH}" -Force`;
      execSync(`powershell -NoProfile -Command "${psCommand}"`, { stdio: 'inherit' });
    } else {
      // Use standard Unix zip command
      execSync(`zip -r "${ZIP_PATH}" ./*`, { cwd: TEMP_RELEASE_DIR, stdio: 'inherit' });
    }

    // 7. Refuse to publish an archive that failed the leak check.
    if (!verifyPackage(ZIP_PATH)) {
      fs.unlinkSync(ZIP_PATH);
      throw new Error(
        'Package verification FAILED — release.zip was deleted rather than shipped. ' +
          'Fix the filter in scripts/package.js before releasing.'
      );
    }

    console.log(`\n🎉 Release package successfully created at:\n👉 ${ZIP_PATH}`);
  } catch (err) {
    console.error(`❌ Packaging failed: ${err.message}`);
    exitCode = 1;
  } finally {
    // 8. Cleanup staging area (always, even on failure)
    console.log('\n🧹 Cleaning up staging area...');
    safeRemoveDir(TEMP_RELEASE_DIR);
    console.log('Done.');
  }

  process.exit(exitCode);
}

// Executed as a CLI (`node scripts/package.js` / `pnpm package`).
// The guard keeps the security filter importable for unit tests.
if (require.main === module) {
  main();
}

module.exports = { shouldSkipApiEntry, verifyPackage };
