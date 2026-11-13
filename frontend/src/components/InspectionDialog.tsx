import { useState } from 'react'
import { Alert, Box, Button, Dialog, DialogActions, DialogContent, DialogTitle, TextField, Typography, useMediaQuery } from '@mui/material'
import type { Theme } from '@mui/material/styles'
import { api } from '../api'
import { Pill } from './Pill'
import Checks from './Checks'
import type { Tone } from './Pill'
import type { Damage, Inspection } from '../types'

const SEVERITY: Record<Damage['severity'], { label: string; tone: Tone }> = {
  minor: { label: 'Leve', tone: 'neutral' },
  moderate: { label: 'Média', tone: 'amber' },
  severe: { label: 'Grave', tone: 'red' },
}

interface Props {
  inspection: Inspection
  equipmentName: string
  onClose: () => void
  onDecided: () => Promise<void>
}

export default function InspectionDialog({ inspection, equipmentName, onClose, onDecided }: Props) {
  const [note, setNote] = useState(inspection.decision_note ?? '')
  const phone = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'))
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const result = inspection.ai_result
  const decided = inspection.decision !== null

  async function decide(decision: 'no_damage' | 'damage_confirmed') {
    setBusy(true)
    setError(null)
    try {
      await api.decide(inspection.id, decision, note.trim() || null)
      await onDecided()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível salvar')
      setBusy(false)
    }
  }

  return (
    <Dialog open onClose={onClose} maxWidth="md" fullWidth fullScreen={phone} transitionDuration={{ enter: 200, exit: 140 }}>
      <DialogTitle sx={{ fontSize: 18, fontWeight: 650 }}>Vistoria: {equipmentName}</DialogTitle>
      <DialogContent>
        <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2, mb: 2.5 }}>
          <Photo label="Saída" url={inspection.checkout_photo_url} />
          <Photo label="Devolução" url={inspection.return_photo_url} />
        </Box>

        <Box sx={{ mb: 2, '&:empty': { display: 'none' } }}><Checks checks={inspection.checks} /></Box>
        {inspection.status === 'failed' && <Alert severity="error" sx={{ mb: 2 }}>A análise automática falhou. Compare as fotos e decida manualmente.</Alert>}
        {result && !result.same_equipment && <Alert severity="warning" sx={{ mb: 2 }}>As fotos não parecem ser do mesmo equipamento. Confira antes de decidir.</Alert>}
        {result?.photo_quality === 'poor' && <Alert severity="warning" sx={{ mb: 2 }}>A qualidade das fotos dificulta a análise. Vale conferir o equipamento pessoalmente.</Alert>}

        {result && (
          <>
            <Typography variant="subtitle2" color="text.secondary" sx={{ mb: 1 }}>O que a IA apontou</Typography>
            <Typography variant="body2" sx={{ mb: 1.5 }}>{result.summary}</Typography>

            {result.damages.map((damage, index) => (
              <Box key={index} sx={{ display: 'flex', gap: 1.5, alignItems: 'flex-start', py: 1.25, borderTop: 1, borderColor: 'divider' }}>
                <Pill tone={SEVERITY[damage.severity].tone}>{SEVERITY[damage.severity].label}</Pill>
                <Box>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>{damage.location}</Typography>
                  <Typography variant="body2" color="text.secondary">{damage.description}</Typography>
                </Box>
              </Box>
            ))}

            <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1.5 }}>
              Isto é uma triagem. A IA enxerga só dano visível na foto e pode errar, então a decisão é sua.
            </Typography>
          </>
        )}

        <TextField
          fullWidth
          size="small"
          label="Observação (opcional)"
          sx={{ mt: 2.5 }}
          disabled={decided}
          value={note}
          onChange={(event) => setNote(event.target.value)}
        />
        {error && <Alert severity="error" sx={{ mt: 1.5 }}>{error}</Alert>}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button color="inherit" onClick={onClose}>Fechar</Button>
        {!decided && (
          <>
            <Button variant="outlined" disabled={busy} onClick={() => decide('no_damage')}>Sem avaria</Button>
            <Button variant="contained" color="error" disabled={busy} onClick={() => decide('damage_confirmed')}>Confirmar avaria</Button>
          </>
        )}
      </DialogActions>
    </Dialog>
  )
}

function Photo({ label, url }: { label: string; url: string | null }) {
  return (
    <Box>
      <Typography variant="subtitle2" color="text.secondary" sx={{ mb: 0.75 }}>{label}</Typography>
      {url && <Box component="img" src={url} alt={`Foto de ${label.toLowerCase()}`} sx={{ width: '100%', aspectRatio: '4 / 3', objectFit: 'contain', bgcolor: 'var(--lq-subtle)', borderRadius: '8px', display: 'block' }} />}
    </Box>
  )
}
