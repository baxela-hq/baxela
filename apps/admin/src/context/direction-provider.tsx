import { createContext, useContext, useEffect, useState } from 'react'
import { DirectionProvider as RdxDirProvider } from '@radix-ui/react-direction'
import { getCookie, setCookie, removeCookie } from '@/lib/cookies'
import {
  directionForLanguage,
  getDefaultLanguageSnapshot,
} from '@/shared/lib/ui-language'
import { type Language } from '@/shared/types/locale.types'

export type Direction = 'ltr' | 'rtl'

const DIRECTION_COOKIE_NAME = 'dir'
const DIRECTION_COOKIE_MAX_AGE = 60 * 60 * 24 * 365 // 1 year

type DirectionContextType = {
  /** Direction derived from the store default language (the reset target). */
  defaultDir: Direction
  dir: Direction
  setDir: (dir: Direction) => void
  /** Follow a language change without creating a manual override. */
  syncDirFromLanguage: (language: Language | null) => void
  resetDir: () => void
}

const DirectionContext = createContext<DirectionContextType | null>(null)

export function DirectionProvider({ children }: { children: React.ReactNode }) {
  // a manual override (the config drawer toggle, cookie-persisted) wins;
  // otherwise direction follows the store default language snapshot
  const [dir, _setDir] = useState<Direction>(
    () =>
      (getCookie(DIRECTION_COOKIE_NAME) as Direction) ||
      directionForLanguage(getDefaultLanguageSnapshot())
  )

  // read per render so the reset target tracks the latest snapshot (sync
  // points re-render this provider through _setDir right after writing it)
  const languageDir = directionForLanguage(getDefaultLanguageSnapshot())

  useEffect(() => {
    const htmlElement = document.documentElement
    htmlElement.setAttribute('dir', dir)
  }, [dir])

  const setDir = (dir: Direction) => {
    _setDir(dir)
    setCookie(DIRECTION_COOKIE_NAME, dir, DIRECTION_COOKIE_MAX_AGE)
  }

  const syncDirFromLanguage = (language: Language | null) => {
    // never overwrite a manual override; resetDir() clears it
    if (getCookie(DIRECTION_COOKIE_NAME)) return
    _setDir(directionForLanguage(language))
  }

  const resetDir = () => {
    // read the snapshot at call time — sign-out clears storage right before
    // this runs, so a value captured at render would be stale
    removeCookie(DIRECTION_COOKIE_NAME)
    _setDir(directionForLanguage(getDefaultLanguageSnapshot()))
  }

  return (
    <DirectionContext
      value={{
        defaultDir: languageDir,
        dir,
        setDir,
        syncDirFromLanguage,
        resetDir,
      }}
    >
      <RdxDirProvider dir={dir}>{children}</RdxDirProvider>
    </DirectionContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useDirection() {
  const context = useContext(DirectionContext)
  if (!context) {
    throw new Error('useDirection must be used within a DirectionProvider')
  }
  return context
}
