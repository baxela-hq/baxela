import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface AuthUser {
  /** Numeric user id — drives the private `user.{id}` Reverb channel. */
  id?: number
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

      setAccessToken: (token) => set({ accessToken: token }),

      reset: () => set({ user: null, accessToken: null }),
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
