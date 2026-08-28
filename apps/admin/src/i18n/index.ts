import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import Backend from 'i18next-http-backend';

i18n
  // load translation using http -> see /locales/en/shared/common.json and /locales/fa/shared/common.json
  // learn more: https://github.com/i18next/i18next-http-backend
  .use(Backend)
  // detect user language
  // learn more: https://github.com/i18next/i18next-browser-language-detector
  // .use(LanguageDetector) // Optional: If you want to detect language automatically
  // pass the i18n instance to react-i18next.
  .use(initReactI18next)
  // init i18next
  // for all options read: https://www.i18next.com/overview/configuration-options
  .init({
    lng: "fa", // default
    fallbackLng: "en",
    // debug: true,
    ns: ["shared/common"],
    defaultNS: "shared/common",
    backend: {
      loadPath: '/locales/{{lng}}/{{ns}}.json',
    },
    interpolation: {
      escapeValue: false,
    },
    react: {
      useSuspense: false,
    },
  });

export default i18n;
