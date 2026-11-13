import { useState } from 'react'
import { Alert, Box, Button, Typography } from '@mui/material'
import ArrowForward from '@mui/icons-material/ArrowForward'
import { api } from '../api'
import type { User } from '../api'

export default function Login({ onSignedIn }: { onSignedIn: (user: User) => void }) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function enter() {
    setBusy(true)
    setError(null)
    try {
      onSignedIn(await api.demoLogin())
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível entrar')
      setBusy(false)
    }
  }

  return (
    <Box sx={{ minHeight: '100%', display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'minmax(0, 1.1fr) minmax(0, 1fr)' }, bgcolor: 'var(--lq-bg)' }}>
      <Box sx={{ display: { xs: 'none', md: 'flex' }, flexDirection: 'column', justifyContent: 'space-between', bgcolor: 'var(--lq-side)', p: 6 }}>
        <Box component="img" src="/logo-dark.png" alt="Loqfy" sx={{ width: 150 }} />
        <Box>
          <Typography sx={{ fontFamily: '"Outfit Variable", sans-serif', fontSize: 38, fontWeight: 600, lineHeight: 1.15, letterSpacing: '-0.02em', color: '#fff', maxWidth: 460 }}>
            A locadora inteira em um lugar só.
          </Typography>
          <Typography sx={{ mt: 2, color: 'var(--lq-side-ink)', maxWidth: 440, fontSize: 17, lineHeight: 1.5 }}>
            Orçamentos, locações, estoque e caixa. Com IA para ler o pedido do cliente e conferir a devolução por foto.
          </Typography>
        </Box>
        <Typography variant="caption" sx={{ color: 'var(--lq-side-ink)', opacity: 0.7 }}>Locadora Demo · Grande Florianópolis</Typography>
      </Box>

      <Box sx={{ display: 'grid', placeItems: 'center', p: 3 }}>
        <Box className="enter" sx={{ width: '100%', maxWidth: 380 }}>
          <Box sx={{ display: { md: 'none' }, mb: 4 }}>
            <Box component="img" src="/logo-light.png" alt="Loqfy" sx={{ width: 130, '.dark &': { display: 'none' } }} />
            <Box component="img" src="/logo-dark.png" alt="" sx={{ width: 130, display: 'none', '.dark &': { display: 'block' } }} />
          </Box>

          <Typography variant="h1" sx={{ fontSize: 28 }}>Entrar</Typography>
          <Typography sx={{ mt: 1, color: 'text.secondary' }}>
            Este é um ambiente de demonstração. Não há senha: você entra como o usuário de demonstração e todos os dados são fictícios.
          </Typography>

          {error && <Alert severity="error" sx={{ mt: 2 }}>{error}</Alert>}

          <Button fullWidth size="large" variant="contained" endIcon={<ArrowForward />} disabled={busy} onClick={enter} sx={{ mt: 3, py: 1.4 }}>
            {busy ? 'Entrando' : 'Entrar na demonstração'}
          </Button>
          <Button fullWidth color="inherit" href="#/" sx={{ mt: 1, color: 'text.secondary' }}>Conhecer o Loqfy</Button>
        </Box>
      </Box>
    </Box>
  )
}
