import { createNavigation } from "next-intl/navigation";
import { routing } from "./routing";

// Locale-aware navigation APIs — import Link/useRouter/usePathname/redirect
// from here instead of next/link and next/navigation so hrefs keep the
// active locale prefix automatically.
export const { Link, redirect, usePathname, useRouter, getPathname } =
  createNavigation(routing);
