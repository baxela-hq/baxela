#!/usr/bin/env node
/**
 * Cross-locale translation parity check for the storefront and admin apps.
 *
 * For every translation file of the reference locale (en), every other
 * locale must have the same file with the same (nested) key set — extra or
 * missing keys fail. For the storefront it also verifies that every file on
 * disk is registered in i18n/request.ts's loadMessages (an unregistered
 * namespace silently never resolves).
 *
 * Run from the repo root: node scripts/check-i18n-parity.mjs
 * Exit code 1 on any mismatch (used by CI).
 */

import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative, sep } from "node:path";

const REFERENCE_LOCALE = "en";

const apps = [
  {
    name: "storefront",
    messagesDir: "apps/storefront/messages",
    loaderFile: "apps/storefront/i18n/request.ts",
  },
  {
    name: "admin",
    messagesDir: "apps/admin/public/locales",
  },
];

function listJsonFiles(dir) {
  const files = [];
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      files.push(...listJsonFiles(full));
    } else if (entry.endsWith(".json")) {
      files.push(full);
    }
  }
  return files;
}

// Flatten nested objects into dot-separated key paths; arrays and leaf
// values count as keys.
function flatten(value, prefix = "") {
  const keys = [];
  if (value && typeof value === "object" && !Array.isArray(value)) {
    for (const [key, child] of Object.entries(value)) {
      keys.push(...flatten(child, prefix ? `${prefix}.${key}` : key));
    }
  } else if (prefix) {
    keys.push(prefix);
  }
  return keys;
}

function keySet(file) {
  return new Set(flatten(JSON.parse(readFileSync(file, "utf8"))));
}

function diffSets(reference, candidate) {
  const missing = [...reference].filter((key) => !candidate.has(key));
  const extra = [...candidate].filter((key) => !reference.has(key));
  return { missing, extra };
}

let failures = 0;

for (const app of apps) {
  const localeDirs = readdirSync(app.messagesDir).filter((entry) =>
    statSync(join(app.messagesDir, entry)).isDirectory(),
  );
  const otherLocales = localeDirs.filter(
    (locale) => locale !== REFERENCE_LOCALE,
  );

  // Relative namespace paths (e.g. catalog/product.json) per locale.
  const filesByLocale = Object.fromEntries(
    localeDirs.map((locale) => [
      locale,
      new Set(
        listJsonFiles(join(app.messagesDir, locale)).map((file) =>
          relative(join(app.messagesDir, locale), file).split(sep).join("/"),
        ),
      ),
    ]),
  );

  for (const locale of otherLocales) {
    const fileDiff = diffSets(filesByLocale[REFERENCE_LOCALE], filesByLocale[locale]);
    for (const file of fileDiff.missing) {
      console.error(`✗ [${app.name}] ${locale} is missing the file ${file}`);
      failures++;
    }
    for (const file of fileDiff.extra) {
      console.error(`✗ [${app.name}] ${locale} has an extra file ${file}`);
      failures++;
    }

    for (const file of filesByLocale[REFERENCE_LOCALE]) {
      if (!filesByLocale[locale].has(file)) continue;
      const { missing, extra } = diffSets(
        keySet(join(app.messagesDir, REFERENCE_LOCALE, file)),
        keySet(join(app.messagesDir, locale, file)),
      );
      for (const key of missing) {
        console.error(`✗ [${app.name}] ${locale}/${file} is missing key "${key}"`);
        failures++;
      }
      for (const key of extra) {
        console.error(`✗ [${app.name}] ${locale}/${file} has an extra key "${key}"`);
        failures++;
      }
    }
  }

  // Storefront: every namespace on disk must be imported by loadMessages.
  if (app.loaderFile) {
    const loader = readFileSync(app.loaderFile, "utf8");
    for (const file of filesByLocale[REFERENCE_LOCALE]) {
      if (!loader.includes(`/${file.replace(/\.json$/, "")}.json`)) {
        console.error(
          `✗ [${app.name}] ${file} exists on disk but is not loaded by ${app.loaderFile}`,
        );
        failures++;
      }
    }
  }
}

if (failures > 0) {
  console.error(`\ni18n parity check failed with ${failures} problem(s).`);
  process.exit(1);
}

console.log("i18n parity check passed.");
