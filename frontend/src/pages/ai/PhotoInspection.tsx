import { useEffect, useRef, useState } from 'react'
import { Alert, Box, Button, CircularProgress, Tooltip, Typography } from '@mui/material'
import PhotoCameraOutlined from '@mui/icons-material/PhotoCameraOutlined'
import { api } from '../../api'
import { useData } from '../../hooks'
import InspectionDialog from '../../components/InspectionDialog'
import RentalCard from '../../components/RentalCard'
import { Pill } from '../../components/Pill'
import { Page, PageHeader } from '../../components/ui'
import type { Inspection, RentalItem } from '../../types'

export default function PhotoInspection() {
  const [polling, setPolling] = useState(false)
  const { data: rentals, reload } = useData(api.rentals, polling ? 2500 : 10000)
  const [openItemId, setOpenItemId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setPolling(rentals?.some((rental) => rental.items.some((item) => item.inspection?.status === 'pending')) ?? false)
  }, [rentals])

  async function upload(item: RentalItem, kind: 'checkout' | 'return', file: File) {
    setError(null)
    try {
      await (kind === 'checkout' ? api.checkoutPhoto(item.id, file) : api.returnPhoto(item.id, file))
      await reload()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível enviar a foto')
    }
  }

  const open = rentals?.flatMap((rental) => rental.items).find((item) => item.id === openItemId)

  const relevant = rentals?.filter((rental) => rental.status !== 'returned' || rental.items.some((item) => item.inspection?.origin === 'ai'))

  return (
    <Page width={980}>
      <PageHeader
        title="Vistoria por foto"
        subtitle="Fotografe o equipamento na saída e na devolução. A IA compara as duas fotos e aponta possíveis avarias novas. A decisão final é sua e fica registrada na locação."
      />

        {error && <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

        {relevant?.map((rental) => (
          <RentalCard
            key={rental.id}
            rental={rental}
            itemAction={(item) => <InspectionStep item={item} onUpload={(kind, file) => upload(item, kind, file)} onOpen={() => setOpenItemId(item.id)} />}
          />
        ))}

      {open?.inspection && (
        <InspectionDialog
          inspection={open.inspection}
          equipmentName={open.name}
          onClose={() => setOpenItemId(null)}
          onDecided={async () => {
            await reload()
            setOpenItemId(null)
          }}
        />
      )}
    </Page>
  )
}

function InspectionStep({ item, onUpload, onOpen }: { item: RentalItem; onUpload: (kind: 'checkout' | 'return', file: File) => Promise<void>; onOpen: () => void }) {
  const inspection = item.inspection

  if (!inspection) {
    return <PhotoButton label="Foto de saída" onPick={(file) => onUpload('checkout', file)} />
  }

  if (inspection.origin === 'manual' && inspection.decision) {
    return <Pill tone="neutral">Devolução registrada à mão</Pill>
  }

  if (inspection.status === 'awaiting_return') {
    return (
      <>
        {inspection.checks.length > 0 && (
          <Tooltip title={inspection.checks.map((check) => check.message).join(' ')}>
            <Box component="span"><Pill tone="amber">Conferir foto</Pill></Box>
          </Tooltip>
        )}
        {inspection.checkout_photo_url && (
          <Box component="img" src={inspection.checkout_photo_url} alt="Foto de saída" sx={{ width: 44, height: 44, objectFit: 'cover', borderRadius: '6px', border: 1, borderColor: 'divider' }} />
        )}
        <PhotoButton label="Foto de devolução" primary onPick={(file) => onUpload('return', file)} />
      </>
    )
  }

  if (inspection.status === 'pending') {
    return (
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, color: 'text.secondary' }}>
        <CircularProgress size={14} thickness={6} sx={{ color: 'inherit', animationDuration: '600ms' }} />
        <Typography variant="body2">Comparando as fotos</Typography>
      </Box>
    )
  }

  return (
    <>
      <InspectionSummary inspection={inspection} />
      <Button size="small" variant={inspection.decision ? 'text' : 'outlined'} onClick={onOpen}>
        {inspection.decision ? 'Ver vistoria' : 'Conferir vistoria'}
      </Button>
    </>
  )
}

function InspectionSummary({ inspection }: { inspection: Inspection }) {
  return (
    <>
      {inspection.checks.length > 0 && <Pill tone="amber">Conferir foto</Pill>}
      <DecisionSummary inspection={inspection} />
    </>
  )
}

function DecisionSummary({ inspection }: { inspection: Inspection }) {
  if (inspection.decision === 'no_damage') return <Pill tone="green">Devolvido sem avaria</Pill>
  if (inspection.decision === 'damage_confirmed') return <Pill tone="red">Avaria confirmada</Pill>
  if (inspection.status === 'failed') return <Pill tone="red">Análise falhou</Pill>

  const count = inspection.ai_result?.damages.length ?? 0
  return count > 0 ? (
    <Pill tone="amber">{count} {count === 1 ? 'possível avaria' : 'possíveis avarias'}</Pill>
  ) : (
    <Pill tone="neutral">IA não viu avaria</Pill>
  )
}

function PhotoButton({ label, primary, onPick }: { label: string; primary?: boolean; onPick: (file: File) => Promise<void> }) {
  const input = useRef<HTMLInputElement>(null)
  const [sending, setSending] = useState(false)

  return (
    <>
      <input
        ref={input}
        type="file"
        accept="image/*,.heic,.heif"
        capture="environment"
        hidden
        onChange={async (event) => {
          const file = event.target.files?.[0]
          event.target.value = ''
          if (!file) return
          setSending(true)
          await onPick(file)
          setSending(false)
        }}
      />
      <Button size="small" variant={primary ? 'contained' : 'outlined'} startIcon={<PhotoCameraOutlined />} disabled={sending} onClick={() => input.current?.click()}>
        {sending ? 'Enviando' : label}
      </Button>
    </>
  )
}
