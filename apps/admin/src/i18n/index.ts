import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import Backend from 'i18next-http-backend';
import {
  DEFAULT_UI_LANGUAGE_CODE,
  getDefaultLanguageSnapshot,
} from '@/shared/lib/ui-language';

// The store default language snapshot (written at sign-in / settings save)
// drives the admin UI language; without one, the built-in default applies.
const uiLanguage = getDefaultLanguageSnapshot();
const uiLanguageCode = uiLanguage?.code ?? DEFAULT_UI_LANGUAGE_CODE;

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
    lng: uiLanguageCode,
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

// keep <html lang> in sync with the boot language (applyUiLanguage maintains
// it after runtime switches)
document.documentElement.lang = uiLanguageCode;

export default i18n;
