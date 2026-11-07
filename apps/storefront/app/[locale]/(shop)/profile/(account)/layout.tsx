// Not for the index (gap-B2 indexing audit): the whole account surface is
// a server layout because the surrounding profile shell is a client
// component and cannot export metadata itself. Route groups don't affect
// URLs — /profile/* is unchanged.
import type { Metadata } from "next";

export const metadata: Metadata = {
  robots: { index: false, follow: false },
};

export default function AccountLayout({ children }: { children: React.ReactNode }) {
  return children;
}
