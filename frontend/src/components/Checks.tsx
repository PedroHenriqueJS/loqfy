import { Box, Typography } from '@mui/material'
import ReportProblemOutlined from '@mui/icons-material/ReportProblemOutlined'
import type { Check } from '../types'

export default function Checks({ checks }: { checks: Check[] }) {
  if (checks.length === 0) return null

  return (
    <Box sx={{ border: '1px solid var(--lq-warn-border)', bgcolor: 'var(--lq-warn-bg)', borderRadius: '12px', px: 2, py: 1.5 }}>
      <Typography sx={{ display: 'flex', alignItems: 'center', gap: 0.75, fontWeight: 600, color: 'var(--pill-amber-fg)' }}>
        <ReportProblemOutlined sx={{ fontSize: 18 }} /> Pontos para conferir
      </Typography>
      <Box component="ul" sx={{ m: 0, mt: 0.75, pl: 2.5 }}>
        {checks.map((check, index) => (
          <Typography component="li" variant="body2" key={index} sx={{ mt: 0.25 }}>{check.message}</Typography>
        ))}
      </Box>
      <Typography variant="caption" sx={{ display: 'block', color: 'text.secondary', mt: 0.75 }}>
        Conferido pelo sistema, sem IA. Não é prova de fraude: só indica onde olhar com atenção.
      </Typography>
    </Box>
  )
}
