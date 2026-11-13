import { useState } from 'react'
import { Alert, Box, Button, Dialog, DialogActions, DialogContent, DialogTitle, useMediaQuery } from '@mui/material'
import type { Theme } from '@mui/material/styles'

interface Props {
  title: string
  children: React.ReactNode
  canSave: boolean
  onClose: () => void
  onSave: () => Promise<void>
  onDelete?: () => Promise<void>
  aside?: React.ReactNode
}

export default function FormDialog({ title, children, canSave, onClose, onSave, onDelete, aside }: Props) {
  const [busy, setBusy] = useState(false)
  const phone = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'))
  const [error, setError] = useState<string | null>(null)

  async function run(action: () => Promise<void>) {
    setBusy(true)
    setError(null)
    try {
      await action()
      onClose()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível salvar')
      setBusy(false)
    }
  }

  return (
    <Dialog open onClose={onClose} maxWidth={aside ? 'md' : 'sm'} fullWidth fullScreen={phone} transitionDuration={{ enter: 200, exit: 140 }}>
      <Box
        component="form"
        onSubmit={(event) => {
          event.preventDefault()
          if (canSave && !busy) run(onSave)
        }}
      >
        <DialogTitle sx={{ fontSize: 18, fontWeight: 650 }}>{title}</DialogTitle>
        <DialogContent sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: aside ? 'minmax(0, 1fr) minmax(0, 1fr)' : '1fr' }, gap: 3, pt: '8px !important' }}>
          {aside}
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            {children}
            {error && <Alert severity="error">{error}</Alert>}
          </Box>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2.5 }}>
          {onDelete && <Button color="error" disabled={busy} onClick={() => run(onDelete)} sx={{ mr: 'auto' }}>Excluir</Button>}
          <Button color="inherit" onClick={onClose}>Cancelar</Button>
          <Button type="submit" variant="contained" disabled={!canSave || busy}>Salvar</Button>
        </DialogActions>
      </Box>
    </Dialog>
  )
}
