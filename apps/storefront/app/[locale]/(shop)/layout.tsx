import { JsonLd } from "@/components/json-ld";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
import { fetchSettings } from "@/lib/api/site";
import { organizationLd, websiteLd } from "@/lib/seo/schema-org";
import { siteUrl } from "@/lib/seo/urls";

export default async function ShopLayout({ children }: { children: React.ReactNode }) {
  // The merchant's identity — from store settings, never the platform
  // brand (gap-B2 D7). Unavailable settings degrade to the host name.
  const settings = await fetchSettings();
  const storeName =
    settings?.find((setting) => setting.name === "website_title")?.value?.trim() ||
    new URL(siteUrl()).hostname;

  return (
    <div className='flex min-h-screen flex-col bg-background'>
      <JsonLd
        data={[
          organizationLd({ name: storeName, url: siteUrl() }),
          websiteLd({ name: storeName, url: siteUrl() }),
        ]}
      />
      <SiteHeader />
      <main className='flex-1'>{children}</main>
      <SiteFooter />
    </div>
  );
}
