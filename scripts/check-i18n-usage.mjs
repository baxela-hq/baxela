#!/usr/bin/env node
/**
 * i18n usage check for the admin app — catches translation calls whose
 * key does not exist in the namespace their hook instance is bound to.
 *
 * The bug class this prevents (seen on the settings Store identity tab):
 * a component binds several useAppTranslation instances
 * (`Locales.SHARED_COMMON` + `Locales.SETTING`) and a helper call uses a
 * key that lives in the OTHER namespace — i18next silently renders the
 * raw key (`form.placeholders.store_legal_name`).
 *
 * How it works:
 *   1. Collect Locales constant definitions (`class Locales` in
 *      `data/routes.ts` files): constant name → namespace string.
 *   2. Walk every .ts/.tsx under apps/admin/src:
 *      - track `const { helpers } = useAppTranslation(Locales.X)`
 *        bindings in file order (later bindings override earlier ones
 *        for the same helper name — mirroring JS scoping);
 *      - for every helper call with a static string literal key,
 *        resolve `prefix + key` inside the English namespace JSON and
 *        flag missing paths.
 *
 * Dynamic keys (variables, template fragments) are skipped — they cannot
 * be verified statically. `t()` and `tx()` calls are skipped too.
 *
 * Usage: node scripts/check-i18n-usage.mjs   (exit 1 on violations)
 */

import fs from 'node:fs';
import path from 'node:path';

const ROOT = process.cwd();
const ADMIN_SRC = path.join(ROOT, 'apps/admin/src');
const LOCALES_DIR = path.join(ROOT, 'apps/admin/public/locales/en');

// helper name → key prefix inside the namespace JSON
const HELPERS = {
  tLabel: 'form.labels.',
  tAction: 'form.actions.',
  tPlaceHolder: 'form.placeholders.',
  tStatus: 'form.statuses.',
  tTooltip: 'form.tooltips.',
  tHelpText: 'form.help_texts.',
  tMessage: 'messages.',
  tPageTitle: 'page_titles.',
};
const HELPER_NAMES = Object.keys(HELPERS);

// ---------- 1. Locales constant map ----------
// `class Locales` blocks in data/routes.ts files: NAME = 'namespace'
const localesMap = new Map(); // constant name → Set<namespace>

function collectLocalesDefinitions(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === 'locales') continue;
      collectLocalesDefinitions(full);
    } else if (entry.name.endsWith('routes.ts')) {
      const c = fs.readFileSync(full, 'utf8');
      const re = /public\s+static\s+readonly\s+(\w+)\s*=\s*'([^']+)'/g;
      let m;
      while ((m = re.exec(c)) !== null) {
        const [, name, ns] = m;
        if (!localesMap.has(name)) localesMap.set(name, new Set());
        localesMap.get(name).add(ns);
      }
    }
  }
}
collectLocalesDefinitions(path.join(ADMIN_SRC, 'features'));
collectLocalesDefinitions(path.join(ADMIN_SRC, 'shared'));
collectLocalesDefinitions(path.join(ADMIN_SRC, 'components'));

// ---------- 2. English namespace JSONs ----------
function loadNamespace(ns) {
  const file = path.join(LOCALES_DIR, `${ns}.json`);
  if (!fs.existsSync(file)) return null;
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return null;
  }
}

function keyExists(json, dottedKey) {
  if (!json) return false;
  let node = json;
  for (const segment of dottedKey.split('.')) {
    if (node && typeof node === 'object' && segment in node) {
      node = node[segment];
    } else {
      return false;
    }
  }
  return node !== undefined;
}

// ---------- 3. Walk source files ----------
const violations = [];
const filesChecked = [];
const skippedDynamic = [];

function walkSources(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walkSources(full);
    } else if (/\.(tsx|ts)$/.test(entry.name)) {
      filesChecked.push(full);
      checkFile(full);
    }
  }
}

function checkFile(file) {
  const rel = path.relative(ROOT, file);
  const c = fs.readFileSync(file, 'utf8');

  // Pass 1: every useAppTranslation binding, in file order (later
  // bindings override earlier ones for the same helper name).
  const bindingRe =
    /const\s+\{([^}]+)\}\s*=\s*useAppTranslation\(\s*Locales\.(\w+)\s*\)/g;
  const bindings = new Map(); // varName → { helper, ns }

  let m;
  while ((m = bindingRe.exec(c)) !== null) {
    for (const h of m[1].split(',').map((s) => s.trim())) {
      const [varName, rename] = h.split(':').map((s) => s.trim());
      if (!varName) continue;
      bindings.set(rename || varName, { helper: varName, ns: m[2] });
    }
  }

  // Pass 2: helper calls with static literal keys, resolved against the
  // binding active for that variable name. Known prefixed helpers AND
  // raw `t` bindings (renamed or not) are all verified.
  const varNames = [...bindings.keys()];
  if (varNames.length === 0) return;

  const callRe = new RegExp(
    `\\b(${varNames.map(escapeRe).join('|')})\\(\\s*['"]([^'"]+)['"]`,
    'g',
  );

  while ((m = callRe.exec(c)) !== null) {
    const binding = bindings.get(m[1]);
    if (binding === undefined) continue;

    const line = c.slice(0, m.index).split('\n').length;
    const prefix = HELPERS[binding.helper] ?? '';
    const candidates = localesMap.get(binding.ns);

    if (!candidates || candidates.size === 0) {
      violations.push({ file: rel, line, helper: m[1], key: m[2], reason: `unknown Locales constant [${binding.ns}]` });
      continue;
    }

    const fullKey = `${prefix}${m[2]}`;
    const missing = [];

    for (const ns of candidates) {
      const json = loadNamespace(ns);
      if (!json) {
        missing.push(`${ns} (namespace file not found)`);
        continue;
      }
      if (!keyExists(json, fullKey)) {
        missing.push(ns);
      }
    }

    if (missing.length === candidates.size) {
      violations.push({ file: rel, line, helper: m[1], key: m[2], reason: `key [${fullKey}] not found in namespace(s): ${[...candidates].join(', ')}` });
    }
  }
}

function escapeRe(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

walkSources(ADMIN_SRC);

// ---------- report ----------
if (violations.length > 0) {
  console.error(`i18n usage check FAILED — ${violations.length} violation(s):\n`);
  for (const v of violations) {
    console.error(`  ${v.file}:${v.line}\n    ${v.helper}('${v.key}') — ${v.reason}\n`);
  }
  process.exit(1);
}

console.log(
  `i18n usage check passed (${filesChecked.length} files, ` +
    `${localesMap.size} Locales constants verified).`,
);
