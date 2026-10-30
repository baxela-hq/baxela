import { createFileRoute, Outlet } from '@tanstack/react-router'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'

/**
 * Settings section shell: standard app chrome (header + main) around the
 * subpages. The template's sidebar-nav is gone — the section is a single
 * page now — but the page must not lose the app header, which is exactly
 * what a bare Outlet did. Bare /settings redirects to /settings/store via
 * the index route (never redirect on a layout: a parent beforeLoad runs
 * for every child match and would loop the child onto itself).
 */
export const Route = createFileRoute('/_authenticated/settings')({
  component: () => (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>
      <Main>
        <Outlet />
      </Main>
    </>
  ),
})
