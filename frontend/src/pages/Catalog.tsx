import { useState } from 'react'
import { Box, Button, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import Add from '@mui/icons-material/Add'
import { api } from '../api'
import { money } from '../format'
import { useData } from '../hooks'
import FormDialog from '../components/FormDialog'
import { Card, Page, PageHeader } from '../components/ui'
import type { DeliveryZone, Equipment } from '../types'

const list = (text: string) => text.split(',').map((part) => part.trim()).filter(Boolean)

export default function CatalogPage() {
  const { data, reload } = useData(api.catalog)
  const [equipment, setEquipment] = useState<Equipment | 'new' | null>(null)
  const [zone, setZone] = useState<DeliveryZone | 'new' | null>(null)
  const categories = [...new Set(data?.equipment.map((item) => item.category))]

  return (
    <Page width={1280}>
      <PageHeader
        title="Catálogo"
        subtitle="Equipamentos, preços, estoque e zonas de entrega. Todo orçamento usa os valores daqui."
        actions={<Button variant="contained" startIcon={<Add />} onClick={() => setEquipment('new')}>Novo equipamento</Button>}
      />

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: 'minmax(0, 2fr) minmax(0, 1fr)' }, gap: 3, alignItems: 'start' }}>
        <Card flush>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Equipamento</TableCell>
                <TableCell>Categoria</TableCell>
                <TableCell align="right">Diária</TableCell>
                <TableCell align="right">Unidades</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {data?.equipment.map((item) => (
                <TableRow key={item.id} hover onClick={() => setEquipment(item)} sx={{ cursor: 'pointer', '&:last-child td': { borderBottom: 0 } }}>
                  <TableCell>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>{item.name}</Typography>
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>{item.description}</Typography>
                  </TableCell>
                  <TableCell>{item.category}</TableCell>
                  <TableCell align="right">{money(item.daily_rate)}</TableCell>
                  <TableCell align="right">{item.quantity_total}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Card>

        <Card title="Zonas de entrega" action={<Button size="small" startIcon={<Add />} onClick={() => setZone('new')}>Nova zona</Button>}>
          {data?.delivery_zones.map((item) => (
            <Box
              key={item.id}
              onClick={() => setZone(item)}
              sx={{ py: 1.25, px: 1, mx: -1, borderTop: '1px solid var(--lq-border)', borderRadius: '6px', cursor: 'pointer', '@media (hover: hover) and (pointer: fine)': { '&:hover': { bgcolor: 'var(--lq-hover)' } } }}
            >
              <Box sx={{ display: 'flex', justifyContent: 'space-between', gap: 2 }}>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>{item.name}</Typography>
                <Typography variant="body2">{money(item.fee)}</Typography>
              </Box>
              <Typography variant="caption" color="text.secondary">{item.neighborhoods.join(', ')}</Typography>
            </Box>
          ))}
        </Card>
      </Box>

      {equipment && <EquipmentForm equipment={equipment === 'new' ? null : equipment} categories={categories} onClose={() => setEquipment(null)} onSaved={reload} />}
      {zone && <ZoneForm zone={zone === 'new' ? null : zone} onClose={() => setZone(null)} onSaved={reload} />}
    </Page>
  )
}

interface FormProps {
  onClose: () => void
  onSaved: () => Promise<void>
}

function EquipmentForm({ equipment, categories, onClose, onSaved }: FormProps & { equipment: Equipment | null; categories: string[] }) {
  const [edit, setEdit] = useState({
    name: equipment?.name ?? '',
    category: equipment?.category ?? categories[0] ?? '',
    description: equipment?.description ?? '',
    aliases: equipment?.aliases.join(', ') ?? '',
    daily_rate: String(equipment?.daily_rate ?? ''),
    quantity_total: String(equipment?.quantity_total ?? 1),
  })
  const field = (name: keyof typeof edit) => ({ value: edit[name], onChange: (event: React.ChangeEvent<HTMLInputElement>) => setEdit({ ...edit, [name]: event.target.value }) })
  const payload = {
    name: edit.name.trim(),
    category: edit.category.trim(),
    description: edit.description.trim() || null,
    aliases: list(edit.aliases),
    daily_rate: Number(edit.daily_rate.replace(',', '.')),
    quantity_total: Number(edit.quantity_total),
  }
  const valid = payload.name !== '' && payload.category !== '' && edit.daily_rate !== '' && payload.daily_rate >= 0 && Number.isInteger(payload.quantity_total) && payload.quantity_total >= 0

  return (
    <FormDialog
      title={equipment ? 'Editar equipamento' : 'Novo equipamento'}
      canSave={valid}
      onClose={onClose}
      onSave={async () => {
        await (equipment ? api.updateEquipment(equipment.id, payload) : api.createEquipment(payload))
        await onSaved()
      }}
      onDelete={equipment ? async () => { await api.deleteEquipment(equipment.id); await onSaved() } : undefined}
    >
      <TextField size="small" label="Nome" autoFocus required {...field('name')} />
      <TextField size="small" label="Categoria" required {...field('category')} />
      <TextField size="small" label="Descrição" {...field('description')} />
      <TextField
        size="small"
        label="Outros nomes"
        helperText="Como os clientes chamam, separados por vírgula. A busca e a IA usam esses nomes."
        {...field('aliases')}
      />
      <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2 }}>
        <TextField size="small" label="Diária (R$)" required slotProps={{ htmlInput: { inputMode: 'decimal' } }} {...field('daily_rate')} />
        <TextField size="small" label="Unidades" type="number" required slotProps={{ htmlInput: { min: 0 } }} {...field('quantity_total')} />
      </Box>
    </FormDialog>
  )
}

function ZoneForm({ zone, onClose, onSaved }: FormProps & { zone: DeliveryZone | null }) {
  const [edit, setEdit] = useState({ name: zone?.name ?? '', neighborhoods: zone?.neighborhoods.join(', ') ?? '', fee: String(zone?.fee ?? '') })
  const field = (name: keyof typeof edit) => ({ value: edit[name], onChange: (event: React.ChangeEvent<HTMLInputElement>) => setEdit({ ...edit, [name]: event.target.value }) })
  const payload = { name: edit.name.trim(), neighborhoods: list(edit.neighborhoods), fee: Number(edit.fee.replace(',', '.')) }

  return (
    <FormDialog
      title={zone ? 'Editar zona de entrega' : 'Nova zona de entrega'}
      canSave={payload.name !== '' && payload.neighborhoods.length > 0 && edit.fee !== '' && payload.fee >= 0}
      onClose={onClose}
      onSave={async () => {
        await (zone ? api.updateZone(zone.id, payload) : api.createZone(payload))
        await onSaved()
      }}
      onDelete={zone ? async () => { await api.deleteZone(zone.id); await onSaved() } : undefined}
    >
      <TextField size="small" label="Nome" autoFocus required {...field('name')} />
      <TextField size="small" label="Bairros e cidades" required multiline minRows={2} helperText="Separados por vírgula." {...field('neighborhoods')} />
      <TextField size="small" label="Valor da entrega (R$)" required slotProps={{ htmlInput: { inputMode: 'decimal' } }} {...field('fee')} />
    </FormDialog>
  )
}
