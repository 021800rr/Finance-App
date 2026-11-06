import i18n from 'i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import { initReactI18next } from 'react-i18next'
import enMessages from './locales/en/messages.json'
import enValidators from './locales/en/validators.json'
import plMessages from './locales/pl/messages.json'
import plValidators from './locales/pl/validators.json'

// The languages of the app, Polish first: the one used when the browser asks for any other.
export const LANGUAGES = ['pl', 'en'] as const

// The texts come from the backend's translations, under the same keys and in the same two domains: "messages"
// (translations/messages.*.yaml) and "validators" (validators.*.yaml, whose keys are the API's error codes). Only
// "app" in messages is the front's own.
void i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: {
      pl: { messages: plMessages, validators: plValidators },
      en: { messages: enMessages, validators: enValidators },
    },
    supportedLngs: LANGUAGES,
    fallbackLng: 'pl',
    // en-US and en-GB are both English
    load: 'languageOnly',
    nonExplicitSupportedLngs: true,
    ns: ['messages', 'validators'],
    defaultNS: 'messages',
    // The language chosen in the header is remembered in this browser; at first the browser's own is used.
    detection: { order: ['localStorage', 'navigator'], caches: ['localStorage'] },
    // React escapes what it shows anyway.
    interpolation: { escapeValue: false },
    initAsync: false,
  })

document.documentElement.lang = i18n.resolvedLanguage ?? 'pl'
i18n.on('languageChanged', () => {
  document.documentElement.lang = i18n.resolvedLanguage ?? 'pl'
})

export default i18n
