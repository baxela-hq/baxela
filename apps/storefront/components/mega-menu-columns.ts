/**
 * Mock mega-menu category data — shared by the mega menu, search menu,
 * mobile menu and footer. Lives outside "use client" modules so server
 * components can import it too. Will be replaced by catalog data from
 * the backend API.
 */
export interface MegaMenuCategory {
  title: string;
  links: { label: string; href: string }[];
}

export const MEGA_MENU_COLUMNS: MegaMenuCategory[] = [
  {
    title: "Sneakers",
    links: [
      { label: "Low top", href: "/products?category=sneakers" },
      { label: "High top", href: "/products?category=sneakers" },
      { label: "Runners", href: "/products?category=sneakers" },
    ],
  },
  {
    title: "Apparel",
    links: [
      { label: "Tees", href: "/products?category=apparel" },
      { label: "Hoodies", href: "/products?category=apparel" },
      { label: "Joggers", href: "/products?category=apparel" },
    ],
  },
  {
    title: "Accessories",
    links: [
      { label: "Caps", href: "/products?category=accessories" },
      { label: "Bags", href: "/products?category=accessories" },
      { label: "Socks", href: "/products?category=accessories" },
    ],
  },
  {
    title: "Footwear",
    links: [
      { label: "Slip-ons", href: "/products?category=footwear" },
      { label: "Sandals", href: "/products?category=footwear" },
      { label: "Boots", href: "/products?category=footwear" },
    ],
  },
];
