/**
 * Languages whose script the user-selectable base fonts (Inter/Manrope,
 * latin-only) cannot render get a dedicated font family here. Every other
 * language (en, fr, ...) keeps the user's selected base font. Fonts are shared
 * per script, not per language — one Arabic family serves fa/ar/ur alike.
 *
 * 📝 How to Add a Language Font:
 * 1. Put the woff2 files in 'public/fonts/<family>/' (for CJK, use
 *    unicode-range-sliced files so only the used glyph slices download).
 * 2. Declare the @font-face blocks in 'src/styles/fonts.css'.
 * 3. Add the family to the '@theme inline' block in 'src/styles/theme.css'
 *    (e.g. --font-<family>).
 * 4. Map the language code here.
 * 5. Add the matching 'html[data-language-font=...]' rule in
 *    'src/styles/index.css'.
 */
const languageFonts = {
  fa: 'vazirmatn',
} as const

export type LanguageFont = (typeof languageFonts)[keyof typeof languageFonts]

/**
 * Font family for a UI language code — exact match first, then the base
 * subtag (`fa-IR` → `fa`). Undefined means: no override, the user's selected
 * base font applies.
 */
export function fontForLanguageCode(
  code: string | null | undefined,
): LanguageFont | undefined {
  if (!code) return undefined
  const exact = languageFonts[code as keyof typeof languageFonts]
  if (exact) return exact
  return languageFonts[code.split('-')[0] as keyof typeof languageFonts]
}
