import { Box, Typography } from '@mui/material'
import { money, period } from '../format'
import { OriginPill, RentalStatusPill } from './Pill'
import type { Rental, RentalItem } from '../types'

interface Props {
  rental: Rental
  action?: React.ReactNode
  itemAction: (item: RentalItem) => React.ReactNode
}

export default function RentalCard({ rental, action, itemAction }: Props) {
  return (
    <Box className="enter" sx={{ bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '14px', boxShadow: 'var(--lq-shadow)', mb: 2 }}>
      <Box sx={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 1.5, px: { xs: 2, sm: 2.5 }, py: 1.75, borderBottom: 1, borderColor: 'divider' }}>
        <Box sx={{ flex: 1, minWidth: 180 }}>
          <Typography sx={{ fontWeight: 600 }} noWrap>{rental.customer_name ?? `Locação nº ${rental.id}`}</Typography>
          <Typography variant="body2" color="text.secondary">{period(rental.start_date, rental.end_date)} · {money(rental.total)}</Typography>
        </Box>
        <OriginPill origin={rental.origin} />
        <RentalStatusPill status={rental.status} />
        {action}
      </Box>

      {rental.items.map((item) => (
        <Box key={item.id} sx={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 1.5, px: { xs: 2, sm: 2.5 }, py: 1.5, minHeight: 56, '& + &': { borderTop: 1, borderColor: 'divider' } }}>
          <Typography variant="body2" sx={{ flex: 1, minWidth: 160 }}>{item.quantity} × {item.name}</Typography>
          {itemAction(item)}
        </Box>
      ))}
    </Box>
  )
}
