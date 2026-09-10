import type { ApiMenuLink } from "@/lib/api/types";

// Menu-shaped UI data derived from the public Menu API. Pure module (no
// server-only imports) so both Server Components and client components can
// consume it.

export interface NavItem {
  href: string;
  label: string | null;
}

export interface MegaMenuColumn {
  title: string | null;
  links: NavItem[];
}

export function navItems(links: ApiMenuLink[]): NavItem[] {
  return links.map((link) => ({ href: link.url, label: link.title }));
}

// A top-level nav link with children renders as a mega-menu trigger; each
// child becomes a column and its children the column's links.
export function megaMenuColumns(link: ApiMenuLink): MegaMenuColumn[] {
  return (link.children ?? []).map((child) => ({
    title: child.title,
    links: (child.children ?? []).map((leaf) => ({
      href: leaf.url,
      label: leaf.title,
    })),
  }));
}

// Category shortcuts (e.g. the children under "Products") used by the mobile
// drawer and the search panel's popular-categories list.
export function categoryItems(links: ApiMenuLink[]): NavItem[] {
  return links.flatMap((link) => link.children ?? []).map((child) => ({
    href: child.url,
    label: child.title,
  }));
}