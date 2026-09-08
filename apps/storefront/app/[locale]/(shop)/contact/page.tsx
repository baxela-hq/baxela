import { getTranslations } from "next-intl/server";
import { ContactForm } from "@/components/contact/contact-form";
import { MailIcon, PhoneIcon } from "@/components/ui/icons";

export default async function ContactPage() {
  const t = await getTranslations("contact.contact");

  return (
    <section className="border-t border-border-light">
      <div className="mx-auto max-w-7xl px-6 py-16">
        <div className="max-w-2xl">
          <h1 className="text-3xl font-semibold text-foreground md:text-4xl rtl:normal-case rtl:tracking-normal">
            {t("page.title")}
          </h1>
          <p className="mt-4 text-lg text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("page.subtitle")}
          </p>
        </div>

        <div className="mt-12 grid gap-10 lg:grid-cols-[1fr_1.5fr]">
          {/* Info block — static copy from the locale dictionaries; move to
              the Setting/Content modules if it ever needs admin editing. */}
          <aside className="flex flex-col gap-6 rounded-default border border-border-light bg-muted/40 p-6 md:p-8">
            <div>
              <h2 className="text-xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("info.title")}
              </h2>
              <p className="mt-3 text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("info.description")}
              </p>
              <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("info.response_time")}
              </p>
            </div>

            <dl className="flex flex-col gap-5 text-sm">
              <div className="flex items-start gap-3">
                <span className="mt-0.5 text-secondary-text [&>svg]:size-5">
                  <MailIcon />
                </span>
                <div>
                  <dt className="text-secondary-text">{t("info.email_label")}</dt>
                  <dd className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                    {t("info.email")}
                  </dd>
                </div>
              </div>
              <div className="flex items-start gap-3">
                <span className="mt-0.5 text-secondary-text [&>svg]:size-5">
                  <PhoneIcon />
                </span>
                <div>
                  <dt className="text-secondary-text">{t("info.phone_label")}</dt>
                  <dd className="font-medium text-foreground" dir="ltr">
                    {t("info.phone")}
                  </dd>
                </div>
              </div>
              <div>
                <dt className="text-secondary-text">{t("info.hours_label")}</dt>
                <dd className="mt-1 font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                  {t("info.hours")}
                </dd>
              </div>
            </dl>
          </aside>

          <ContactForm />
        </div>
      </div>
    </section>
  );
}
