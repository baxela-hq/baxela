// Not for the index (gap-B2 indexing audit): utility/account surface.
// A server layout carries the robots directive because these pages are
// client components and cannot export metadata themselves.
import type { Metadata } from "next";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default function NoindexPathLayout({ children }: { children: React.ReactNode }) {
  return children;
}
