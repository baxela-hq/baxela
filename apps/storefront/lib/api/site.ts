import { cache } from "react";
import { serverApiGet } from "./server";
import type { ApiMenu, ApiSetting } from "./types";

// Public site menus — header (nav) and footer (link columns) both come from
// the Menu module. Returns null when the backend is unreachable so the
// layout degrades gracefully instead of erroring the whole page.
export async function fetchMenu(location: string): Promise<ApiMenu | null> {
  try {
    return await serverApiGet<ApiMenu>(`/menu/public/menus/${location}`);
  } catch {
    return null;
  }
}

// Public site settings (Setting module) — title/description, the announcement
// bar, and the storefront theme. Returns null when the backend is unreachable;
// callers skip whatever depends on it instead of erroring the whole page.
// cache() dedupes the two server callers (root layout resolves the site
// theme, SiteHeader reads the announcement) into one API request per render.
export const fetchSettings = cache(
  async (): Promise<ApiSetting[] | null> => {
    try {
      return await serverApiGet<ApiSetting[]>("/setting/public/settings");
    } catch {
      return null;
    }
  },
);