import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { setCookie, removeCookie } from '@/lib/cookies'

const ACCESS_TOKEN = 'thisisjustarandomstring'

interface AuthUser {
  accountNo: string
  email: string
  role: string[]
  name: string
  exp: number
}

interface AuthState {
  user: AuthUser | null
  accessToken: string | null
  setUser: (user: AuthUser | null) => void
  setAccessToken: (token: string | null) => void
  reset: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      accessToken: null,

      setUser: (user) => set({ user }),

      setAccessToken: (token) => {
        if (token)
          setCookie(ACCESS_TOKEN, token)
        else
          removeCookie(ACCESS_TOKEN)

        set({ accessToken: token })
      },

      reset: () => {
        removeCookie(ACCESS_TOKEN)
        set({ user: null, accessToken: null })
      },
    }),

    {
      name: 'auth-storage', // localStorage key
      partialize: (state) => ({
        user: state.user,
        accessToken: state.accessToken,
      }),
    }
  )
)
