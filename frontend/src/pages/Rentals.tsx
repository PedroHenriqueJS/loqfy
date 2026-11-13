import { useState } from 'react'
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, TextField, Typography } from '@mui/material'
import DescriptionOutlined from '@mui/icons-material/DescriptionOutlined'
import { api } from '../api'
import type { Decision } from '../api'
import { useData } from '../hooks'
import RentalCard from '../components/RentalCard'
import { Pill } from '../components/Pill'
import { Empty, Page, PageHeader, Segmented } from '../components/ui'
import type { RentalItem } from '../types'

export default function Rentals() {
  const { data: rentals, reload } = useData(api.rentals, 10000)
  const [returning, setReturning] = useState<RentalItem | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [filter, setFilter] = useState<'open' | 'returned'>('open')
  const visible = rentals?.filter((rental) => (rental.status === 'returned') === (filter === 'returned'))

  async function run(action: () => Promise<unknown>) {
    setError(null)
    try {
      await action()
      await reload()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Algo deu errado')
    }
  }

  return (
    <Page width={980}>
      <PageHeader
        title="Locações"
        subtitle="Uma locação nasce de um orçamento aprovado. Registre aqui a saída e a devolução de cada equipamento."
        actions={
          <Segmented
            value={filter}
            onChange={setFilter}
            options={[
              { value: 'open', label: 'Em andamento', count: rentals?.filter((rental) => rental.status !== 'returned').length },
              { value: 'returned', label: 'Devolvidas' },
            ]}
          />
        }
      />

        {error && <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

        {visible?.map((rental) => (
          <RentalCard
            key={rental.id}
            rental={rental}
            action={
              <>
                <Button size="small" color="inherit" startIcon={<DescriptionOutlined />} href={api.contractUrl(rental.id)} target="_blank" rel="noopener">Contrato</Button>
                {rental.status === 'reserved' && <Button size="small" variant="outlined" onClick={() => run(() => api.checkoutRental(rental.id))}>Registrar saída</Button>}
              </>
            }
            itemAction={(item) => {
              const inspection = item.inspection

              if (inspection?.decision) {
                return (
                  <>
                    {inspection.origin === 'ai' && <Pill tone="violet">Vistoria por IA</Pill>}
                    {inspection.decision === 'no_damage' ? <Pill tone="green">Devolvido sem avaria</Pill> : <Pill tone="red">Devolvido com avaria</Pill>}
                  </>
                )
              }

              if (inspection?.return_photo_url) return <Pill tone="violet">Vistoria por foto em andamento</Pill>
              if (rental.status === 'reserved') return null

              return <Button size="small" onClick={() => setReturning(item)}>Registrar devolução</Button>
            }}
          />
        ))}

        {visible?.length === 0 && <Empty>{filter === 'open' ? 'Nenhuma locação em andamento. Aprove um orçamento para criar uma.' : 'Nenhuma locação devolvida ainda.'}</Empty>}

      {returning && (
        <ReturnDialog
          item={returning}
          onClose={() => setReturning(null)}
          onSave={async (decision, note) => {
            await run(() => api.manualReturn(returning.id, decision, note))
            setReturning(null)
          }}
        />
      )}
    </Page>
  )
}

function ReturnDialog({ item, onClose, onSave }: { item: RentalItem; onClose: () => void; onSave: (decision: Decision, note: string | null) => Promise<void> }) {
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  async function save(decision: Decision) {
    setBusy(true)
    await onSave(decision, note.trim() || null)
    setBusy(false)
  }

  return (
    <Dialog open onClose={onClose} maxWidth="xs" fullWidth transitionDuration={{ enter: 200, exit: 140 }}>
      <DialogTitle sx={{ fontSize: 18, fontWeight: 650 }}>Devolução: {item.name}</DialogTitle>
      <DialogContent>
        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>Confira o equipamento e registre como ele voltou.</Typography>
        <TextField fullWidth multiline minRows={2} size="small" label="Observação (opcional)" value={note} onChange={(event) => setNote(event.target.value)} />
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button color="inherit" onClick={onClose}>Cancelar</Button>
        <Button variant="outlined" color="error" disabled={busy} onClick={() => save('damage_confirmed')}>Com avaria</Button>
        <Button variant="contained" disabled={busy} onClick={() => save('no_damage')}>Sem avaria</Button>
      </DialogActions>
    </Dialog>
  )
}
