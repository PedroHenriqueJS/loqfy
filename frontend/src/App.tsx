import { lazy, Suspense, useEffect, useState } from 'react'
import { flushSync } from 'react-dom'
import { Box, ButtonBase, Drawer, IconButton, Tooltip, Typography } from '@mui/material'
import MenuIcon from '@mui/icons-material/Menu'
import { useColorScheme } from '@mui/material/styles'
import type { SvgIconComponent } from '@mui/icons-material'
import SpaceDashboardOutlined from '@mui/icons-material/SpaceDashboardOutlined'
import RequestQuoteOutlined from '@mui/icons-material/RequestQuoteOutlined'
import LocalShippingOutlined from '@mui/icons-material/LocalShippingOutlined'
import PeopleAltOutlined from '@mui/icons-material/PeopleAltOutlined'
import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined'
import AccountBalanceWalletOutlined from '@mui/icons-material/AccountBalanceWalletOutlined'
import ForumOutlined from '@mui/icons-material/ForumOutlined'
import PhotoCameraOutlined from '@mui/icons-material/PhotoCameraOutlined'
import InsightsOutlined from '@mui/icons-material/InsightsOutlined'
import ReceiptLongOutlined from '@mui/icons-material/ReceiptLongOutlined'
import AutoAwesome from '@mui/icons-material/AutoAwesome'
import LightModeOutlined from '@mui/icons-material/LightModeOutlined'
import DarkModeOutlined from '@mui/icons-material/DarkModeOutlined'
import SettingsBrightnessOutlined from '@mui/icons-material/SettingsBrightnessOutlined'
import LogoutOutlined from '@mui/icons-material/LogoutOutlined'
import { api, SIGNED_OUT } from './api'
import type { User } from './api'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Quotes from './pages/Quotes'
import QuoteDetail from './pages/QuoteDetail'
import Rentals from './pages/Rentals'
import Customers from './pages/Customers'
import CatalogPage from './pages/Catalog'
import Finance from './pages/Finance'
import MessageQuotes from './pages/ai/MessageQuotes'
import PhotoInspection from './pages/ai/PhotoInspection'
import AiQuality from './pages/ai/AiQuality'
import ReceiptReader from './pages/ai/ReceiptReader'
import { go, usePath } from './router'

const Landing = lazy(() => import('./landing/Landing'))

interface NavItem {
  path: string
  label: string
  icon: SvgIconComponent
}

const NAV: Array<{ title?: string; ai?: boolean; items: NavItem[] }> = [
  { items: [{ path: 'inicio', label: 'Início', icon: SpaceDashboardOutlined }] },
  {
    title: 'Operação',
    items: [
      { path: 'orcamentos', label: 'Orçamentos', icon: RequestQuoteOutlined },
      { path: 'locacoes', label: 'Locações', icon: LocalShippingOutlined },
      { path: 'clientes', label: 'Clientes', icon: PeopleAltOutlined },
      { path: 'catalogo', label: 'Catálogo', icon: Inventory2Outlined },
      { path: 'financeiro', label: 'Financeiro', icon: AccountBalanceWalletOutlined },
    ],
  },
  {
    title: 'Ferramentas de IA',
    ai: true,
    items: [
      { path: 'ia/mensagens', label: 'Orçamento por mensagem', icon: ForumOutlined },
      { path: 'ia/vistoria', label: 'Vistoria por foto', icon: PhotoCameraOutlined },
      { path: 'ia/comprovante', label: 'Comprovante por foto', icon: ReceiptLongOutlined },
      { path: 'ia/qualidade', label: 'Qualidade da IA', icon: InsightsOutlined },
    ],
  },
]

function Page({ path }: { path: string }) {
  const [section, second] = path.split('/')

  if (section === 'orcamentos') return second ? <QuoteDetail key={second} id={second === 'novo' ? null : Number(second)} /> : <Quotes />
  if (section === 'locacoes') return <Rentals />
  if (section === 'clientes') return <Customers />
  if (section === 'catalogo') return <CatalogPage />
  if (section === 'financeiro') return <Finance />
  if (path === 'ia/mensagens') return <MessageQuotes />
  if (path === 'ia/vistoria') return <PhotoInspection />
  if (path === 'ia/comprovante') return <ReceiptReader />
  if (path === 'ia/qualidade') return <AiQuality />
  return <Dashboard />
}

export default function App() {
  const route = usePath()
  const path = route || 'inicio'
  const [user, setUser] = useState<User | null | undefined>(undefined)

  useEffect(() => {
    const signedOut = () => setUser(null)
    window.addEventListener(SIGNED_OUT, signedOut)
    api.me().then(setUser, signedOut)
    return () => window.removeEventListener(SIGNED_OUT, signedOut)
  }, [])

  if (user === undefined) return <Box sx={{ height: '100%', bgcolor: '#060a14' }} />

  if (route === 'site' || (user === null && route !== 'entrar')) {
    return <Suspense fallback={<Box sx={{ height: '100%', bgcolor: '#060a14' }} />}><Landing /></Suspense>
  }

  if (user === null) {
    return (
      <Login
        onSignedIn={(signedIn) => {
          setUser(signedIn)
          go('inicio')
        }}
      />
    )
  }

  return <Shell path={path} user={user} onSignOut={() => api.logout().then(() => setUser(null))} />
}

function Shell({ path, user, onSignOut }: { path: string; user: User; onSignOut: () => void }) {
  const [menuOpen, setMenuOpen] = useState(false)

  useEffect(() => setMenuOpen(false), [path])

  return (
    <Box sx={{ display: 'flex', flexDirection: { xs: 'column', md: 'row' }, height: '100%', bgcolor: 'var(--lq-bg)', color: 'text.primary' }}>
      <Box component="nav" sx={{ display: { xs: 'none', md: 'flex' }, width: 256, flexShrink: 0, borderRight: '1px solid var(--lq-side-border)' }}>
        <Sidebar path={path} user={user} onSignOut={onSignOut} />
      </Box>

      <Box
        component="header"
        sx={{
          display: { xs: 'flex', md: 'none' },
          alignItems: 'center',
          gap: 1,
          flexShrink: 0,
          bgcolor: 'var(--lq-side)',
          px: 1,
          pt: 'calc(env(safe-area-inset-top) + 6px)',
          pb: '6px',
        }}
      >
        <IconButton aria-label="Abrir menu" onClick={() => setMenuOpen(true)} sx={{ color: 'var(--lq-side-ink-strong)' }}>
          <MenuIcon />
        </IconButton>
        <Box component="a" href="#/inicio" sx={{ display: 'block' }}>
          <Box component="img" src="/logo-dark.png" alt="Loqfy" sx={{ width: 92, display: 'block' }} />
        </Box>
      </Box>

      <Drawer
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        sx={{ display: { md: 'none' } }}
        slotProps={{ paper: { sx: { width: 'min(300px, 86vw)', bgcolor: 'var(--lq-side)', backgroundImage: 'none', borderRight: 'none' } } }}
      >
        <Sidebar path={path} user={user} onSignOut={onSignOut} />
      </Drawer>

      <Box component="main" sx={{ flex: 1, minWidth: 0, minHeight: 0, height: { md: '100%' }, overflow: 'hidden' }}>
        <Page path={path} />
      </Box>
    </Box>
  )
}

function Sidebar({ path, user, onSignOut }: { path: string; user: User; onSignOut: () => void }) {
  return (
    <Box sx={{ width: '100%', height: '100%', bgcolor: 'var(--lq-side)', p: 1.5, pb: 'calc(env(safe-area-inset-bottom) + 12px)', display: 'flex', flexDirection: 'column', overflowY: 'auto' }}>
        <Box component="a" href="#/inicio" sx={{ display: 'block', px: 1, pt: 1.25, pb: 1 }}>
          <Box component="img" src="/logo-dark.png" alt="Loqfy" sx={{ width: 124, display: 'block' }} />
        </Box>

        {NAV.map((group, index) => (
          <Box key={index} sx={{ display: 'flex', flexDirection: 'column', gap: 0.25, mt: 2 }}>
            {group.title && (
              <Typography variant="subtitle2" sx={{ display: 'flex', alignItems: 'center', gap: 0.75, px: 1.25, pb: 0.5, color: 'color-mix(in srgb, var(--lq-side-ink) 70%, transparent)' }}>
                {group.ai && <AutoAwesome sx={{ fontSize: 13, color: 'var(--lq-side-violet)' }} />}
                {group.title}
              </Typography>
            )}
            {group.items.map(({ path: target, label, icon: Icon }) => {
              const active = path === target || path.startsWith(`${target}/`)
              return (
                <ButtonBase
                  key={target}
                  href={`#/${target}`}
                  aria-current={active ? 'page' : undefined}
                  sx={{
                    justifyContent: 'flex-start',
                    gap: 1.25,
                    px: 1.25,
                    py: 0.95,
                    borderRadius: '10px',
                    fontSize: 14,
                    fontWeight: active ? 600 : 500,
                    color: active ? 'var(--lq-side-ink-strong)' : 'var(--lq-side-ink)',
                    bgcolor: active ? 'var(--lq-side-active)' : 'transparent',
                    '@media (hover: hover) and (pointer: fine)': { '&:hover': { bgcolor: active ? 'var(--lq-side-active)' : 'var(--lq-side-hover)', color: 'var(--lq-side-ink-strong)' } },
                  }}
                >
                  <Icon sx={{ fontSize: 19, color: group.ai ? 'var(--lq-side-violet)' : active ? 'var(--lq-orange)' : 'inherit' }} />
                  {label}
                </ButtonBase>
              )
            })}
          </Box>
        ))}

        <Box sx={{ mt: 'auto', pt: 3 }}>
          <ModeSwitch />
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25, px: 0.75, pt: 1.5 }}>
            <Box sx={{ display: 'grid', placeItems: 'center', width: 32, height: 32, borderRadius: '50%', bgcolor: 'var(--lq-side-active)', color: 'var(--lq-side-ink-strong)', fontSize: 13, fontWeight: 600 }}>
              {user.name.charAt(0)}
            </Box>
            <Box sx={{ flex: 1, minWidth: 0 }}>
              <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--lq-side-ink-strong)', lineHeight: 1.3 }} noWrap>{user.name}</Typography>
              <Typography sx={{ fontSize: 12, color: 'var(--lq-side-ink)', lineHeight: 1.3 }} noWrap>Locadora Demo</Typography>
            </Box>
            <Tooltip title="Sair">
              <IconButton size="small" aria-label="Sair" onClick={onSignOut} sx={{ color: 'var(--lq-side-ink)' }}>
                <LogoutOutlined sx={{ fontSize: 18 }} />
              </IconButton>
            </Tooltip>
          </Box>
        </Box>
    </Box>
  )
}

const MODES = [
  { value: 'light', label: 'Claro', icon: LightModeOutlined },
  { value: 'system', label: 'Seguir o sistema', icon: SettingsBrightnessOutlined },
  { value: 'dark', label: 'Escuro', icon: DarkModeOutlined },
] as const

type Mode = (typeof MODES)[number]['value']

function ModeSwitch() {
  const { mode, setMode } = useColorScheme()

  function change(next: Mode, event: React.MouseEvent) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches

    if (!document.startViewTransition || reducedMotion) {
      setMode(next)
      return
    }

    const { clientX: x, clientY: y } = event
    const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y))

    const transition = document.startViewTransition(async () => {
      flushSync(() => setMode(next))
      await new Promise((resolve) => setTimeout(resolve, 0))
    })

    transition.ready.then(() => {
      document.documentElement.animate(
        { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
        { duration: 480, easing: 'cubic-bezier(0.65, 0, 0.35, 1)', pseudoElement: '::view-transition-new(root)' },
      )
    })
  }

  return (
    <Box role="radiogroup" aria-label="Aparência" sx={{ display: 'flex', p: 0.5, gap: 0.5, borderRadius: '12px', bgcolor: 'var(--lq-side-hover)' }}>
      {MODES.map(({ value, label, icon: Icon }) => {
        const active = (mode ?? 'system') === value
        return (
          <Tooltip key={value} title={label}>
            <ButtonBase
              role="radio"
              aria-checked={active}
              aria-label={label}
              onClick={(event) => change(value, event)}
              sx={{ flex: 1, py: 0.7, borderRadius: '10px', color: active ? 'var(--lq-side-ink-strong)' : 'var(--lq-side-ink)', bgcolor: active ? 'var(--lq-side-active)' : 'transparent' }}
            >
              <Icon sx={{ fontSize: 17 }} />
            </ButtonBase>
          </Tooltip>
        )
      })}
    </Box>
  )
}
