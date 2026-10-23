"use client";

import { useServerInsertedHTML } from "next/navigation";

import { THEME_MODE_INIT_SCRIPT } from "@/lib/theme";

/**
 * The insertion callback re-runs for every chunk of the HTML stream, so it
 * must emit the script exactly once per server render (the flush-once pattern
 * the useServerInsertedHTML docs use for CSS-in-JS registries).
 */
function createOnceEmitter() {
  let done = false;
  return function emitThemeScriptOnce() {
    if (done) return null;
    done = true;
    return (
      <script dangerouslySetInnerHTML={{ __html: THEME_MODE_INIT_SCRIPT }} />
    );
  };
}

/**
 * Emits THEME_MODE_INIT_SCRIPT into the SSR <head> (Next splices it in before
 * </head>, so it still executes before first paint) while keeping it out of
 * the client-rendered tree: React 19.2 warns that <script> elements rendered
 * as React elements are never executed when created on the client. The hook
 * registers a callback only during server rendering — on the client it is a
 * no-op, so no <script> React element ever exists there.
 */
export function ThemeModeInitScript() {
  useServerInsertedHTML(createOnceEmitter());

  return null;
}
