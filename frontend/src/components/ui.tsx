import { Box, ButtonBase, Typography } from '@mui/material'
import type { SxProps, Theme } from '@mui/material/styles'

export function Page({ children, width = 1180 }: { children: React.ReactNode; width?: number }) {
  return (
    <Box sx={{ height: '100%', overflow: 'auto' }}>
      <Box sx={{ maxWidth: width, mx: 'auto', px: { xs: 2, md: 4 }, py: 4 }}>{children}</Box>
    </Box>
  )
}

export function PageHeader({ title, subtitle, actions }: { title: React.ReactNode; subtitle?: React.ReactNode; actions?: React.ReactNode }) {
  return (
    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 2, mb: 3, flexWrap: 'wrap' }}>
      <Box sx={{ flex: 1, minWidth: 260 }}>
        <Typography variant="h1">{title}</Typography>
        {subtitle && <Typography sx={{ mt: 0.75, color: 'text.secondary', maxWidth: 680 }}>{subtitle}</Typography>}
      </Box>
      {actions && <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, alignItems: 'center' }}>{actions}</Box>}
    </Box>
  )
}

interface CardProps {
  title?: React.ReactNode
  hint?: React.ReactNode
  action?: React.ReactNode
  children: React.ReactNode
  flush?: boolean
  sx?: SxProps<Theme>
}

export function Card({ title, hint, action, children, flush, sx }: CardProps) {
  return (
    <Box sx={[{ bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '14px', boxShadow: 'var(--lq-shadow)', overflow: 'hidden' }, ...(Array.isArray(sx) ? sx : [sx])]}>
      {(title || action) && (
        <Box sx={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 1.5, px: 2.5, pt: 2.25, pb: flush ? 1.75 : 0.5 }}>
          <Box sx={{ flex: 1, minWidth: 200 }}>
            <Typography variant="h2">{title}</Typography>
            {hint && <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.25 }}>{hint}</Typography>}
          </Box>
          {action}
        </Box>
      )}
      <Box sx={flush ? { overflowX: 'auto' } : { p: { xs: 2, sm: 2.5 } }}>{children}</Box>
    </Box>
  )
}

interface StatProps {
  label: string
  value: React.ReactNode
  note?: React.ReactNode
  tone?: 'default' | 'good' | 'bad'
  icon?: React.ReactNode
}

export function Stat({ label, value, note, tone = 'default', icon }: StatProps) {
  const noteColor = tone === 'good' ? 'success.main' : tone === 'bad' ? 'error.main' : 'text.secondary'

  return (
    <Box sx={{ bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '14px', boxShadow: 'var(--lq-shadow)', p: { xs: 1.75, sm: 2.25 }, minWidth: 0 }}>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, color: 'text.secondary' }}>
        {icon && <Box sx={{ display: { xs: 'none', sm: 'grid' }, placeItems: 'center', width: 28, height: 28, borderRadius: '10px', bgcolor: 'var(--lq-blue-soft)', color: 'var(--lq-blue)', '& svg': { fontSize: 17 } }}>{icon}</Box>}
        <Typography variant="body2" sx={{ fontWeight: 500, lineHeight: 1.3 }}>{label}</Typography>
      </Box>
      <Typography sx={{ fontFamily: '"Outfit Variable", sans-serif', fontSize: { xs: 21, sm: 28 }, fontWeight: 600, letterSpacing: '-0.02em', lineHeight: 1.15, mt: 1.25 }} noWrap>
        {value}
      </Typography>
      {note && <Typography variant="body2" sx={{ color: noteColor, mt: 0.5, lineHeight: 1.35 }}>{note}</Typography>}
    </Box>
  )
}

export function Segmented<T extends string>({ value, onChange, options }: { value: T; onChange: (value: T) => void; options: Array<{ value: T; label: string; count?: number }> }) {
  return (
    <Box role="tablist" sx={{ display: 'inline-flex', maxWidth: '100%', overflowX: 'auto', p: 0.5, gap: 0.5, bgcolor: 'var(--lq-subtle)', border: '1px solid var(--lq-border)', borderRadius: '12px' }}>
      {options.map((option) => {
        const active = option.value === value
        return (
          <ButtonBase
            key={option.value}
            role="tab"
            aria-selected={active}
            onClick={() => onChange(option.value)}
            sx={{
              px: 1.5,
              py: 0.6,
              flexShrink: 0,
              whiteSpace: 'nowrap',
              borderRadius: '10px',
              fontSize: 13.5,
              fontWeight: 600,
              gap: 0.75,
              color: active ? 'text.primary' : 'text.secondary',
              bgcolor: active ? 'var(--lq-paper)' : 'transparent',
              boxShadow: active ? 'var(--lq-shadow)' : 'none',
            }}
          >
            {option.label}
            {option.count !== undefined && <Box component="span" sx={{ color: 'var(--lq-faint)', fontWeight: 500 }}>{option.count}</Box>}
          </ButtonBase>
        )
      })}
    </Box>
  )
}

export function Empty({ children }: { children: React.ReactNode }) {
  return <Typography variant="body2" sx={{ color: 'text.secondary', textAlign: 'center', py: 5, px: 3 }}>{children}</Typography>
}
