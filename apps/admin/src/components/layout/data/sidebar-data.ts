import {
  Construction,
  LayoutDashboard,
  Monitor,
  Bug,
  ListTodo,
  FileX,
  HelpCircle,
  Lock,
  Bell,
  Package,
  Palette,
  ServerOff,
  Settings,
  Wrench,
  UserCog,
  UserX,
  Users,
  MessagesSquare,
  ShieldCheck,
  Command,
  GalleryVerticalEnd,
  Images,
  Truck,
} from 'lucide-react'
import { ClerkLogo } from '@/assets/clerk-logo'
import { useAuthStore } from '@/stores/auth-store'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { type SidebarData } from '../types'

export const useSidebarData = (): SidebarData => {
  const { t } = useAppTranslation('shared/layout')
  const { user } = useAuthStore()

  return {
    user: {
      name: user?.name ?? '',
      email: user?.email ?? '',
      avatar: '/avatars/shadcn.jpg',
    },
    teams: [
      {
        name: 'XShop Admin',
        logo: Command,
        plan: 'Vite + ShadcnUI',
      },
      {
        name: 'Acme Inc',
        logo: GalleryVerticalEnd,
        plan: 'Enterprise',
      },
    ],
    navGroups: [
      {
        title: t('sidebar.general'),
        items: [
          {
            title: t('sidebar.dashboard'),
            url: '/',
            icon: LayoutDashboard,
          },
          {
            title: t('sidebar.settings'),
            url: '/setting/settings',
            icon: Settings,
          },
          {
            title: t('sidebar.users'),
            url: '/user/users',
            icon: Users,
          },
          {
            title: t('sidebar.tasks'),
            url: '/tasks',
            icon: ListTodo,
          },
          {
            title: t('sidebar.apps'),
            url: '/apps',
            icon: Package,
          },
          {
            title: t('sidebar.chats'),
            url: '/chats',
            badge: '3',
            icon: MessagesSquare,
          },
          {
            title: t('sidebar.users'),
            url: '/users',
            icon: Users,
          },
          {
            title: 'Secured by Clerk',
            icon: ClerkLogo,
            items: [
              {
                title: 'Sign In',
                url: '/clerk/sign-in',
              },
              {
                title: 'Sign Up',
                url: '/clerk/sign-up',
              },
              {
                title: 'User Management',
                url: '/clerk/user-management',
              },
            ],
          },
        ],
      },
      {
        title: t('sidebar.modules'),
        items: [
          {
            title: t('sidebar.catalog'),
            icon: Package,
            items: [
              {
                title: t('sidebar.products'),
                url: '/catalog/products',
              },
              {
                title: t('sidebar.categories'),
                url: '/catalog/categories',
              },
              {
                title: t('sidebar.options'),
                url: '/catalog/options',
              },
              {
                title: t('sidebar.attributes'),
                url: '/catalog/attributes',
              },
            ],
          },
          {
            title: t('sidebar.content'),
            icon: Package,
            items: [
              {
                title: t('sidebar.pages'),
                url: '/content/pages',
              },
            ],
          },
          {
            title: t('sidebar.media'),
            url: '/media',
            icon: Images,
          },
          {
            title: t('sidebar.order'),
            icon: Package,
            items: [
              {
                title: t('sidebar.orders'),
                url: '/order/orders',
              },
            ],
          },
          {
            title: t('sidebar.shipping'),
            icon: Truck,
            items: [
              {
                title: t('sidebar.methods'),
                url: '/shipping/methods',
              },
              {
                title: t('sidebar.zones'),
                url: '/shipping/zones',
              },
              {
                title: t('sidebar.rates'),
                url: '/shipping/rates',
              },
              {
                title: t('sidebar.shipments'),
                url: '/shipping/shipments',
              },
            ],
          },
          {
            title: 'Auth',
            icon: ShieldCheck,
            items: [
              {
                title: 'Sign In',
                url: '/sign-in',
              },
              {
                title: 'Sign In (2 Col)',
                url: '/sign-in-2',
              },
              {
                title: 'Sign Up',
                url: '/sign-up',
              },
              {
                title: 'Forgot Password',
                url: '/forgot-password',
              },
              {
                title: 'OTP',
                url: '/otp',
              },
            ],
          },
          {
            title: t('sidebar.errors'),
            icon: Bug,
            items: [
              {
                title: t('sidebar.unauthorized'),
                url: '/errors/unauthorized',
                icon: Lock,
              },
              {
                title: t('sidebar.forbidden'),
                url: '/errors/forbidden',
                icon: UserX,
              },
              {
                title: t('sidebar.not-found'),
                url: '/errors/not-found',
                icon: FileX,
              },
              {
                title: t('sidebar.internal-server-error'),
                url: '/errors/internal-server-error',
                icon: ServerOff,
              },
              {
                title: t('sidebar.maintenance-error'),
                url: '/errors/maintenance-error',
                icon: Construction,
              },
            ],
          },
        ],
      },
      {
        title: t('sidebar.other'),
        items: [
          {
            title: t('sidebar.settings'),
            icon: Settings,
            items: [
              {
                title: t('sidebar.profile'),
                url: '/settings',
                icon: UserCog,
              },
              {
                title: t('sidebar.account'),
                url: '/settings/account',
                icon: Wrench,
              },
              {
                title: t('sidebar.appearance'),
                url: '/settings/appearance',
                icon: Palette,
              },
              {
                title: t('sidebar.notifications'),
                url: '/settings/notifications',
                icon: Bell,
              },
              {
                title: t('sidebar.display'),
                url: '/settings/display',
                icon: Monitor,
              },
            ],
          },
          {
            title: t('sidebar.help-center'),
            url: '/help-center',
            icon: HelpCircle,
          },
        ],
      },
    ],
  }
}
