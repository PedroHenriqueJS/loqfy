import { Chip } from '@mui/material'
import type { Origin, Quote, Rental, RequestStatus } from '../types'

const TONES = ['neutral', 'muted', 'green', 'amber', 'blue', 'red', 'violet'] as const

export type Tone = (typeof TONES)[number]

export function Pill({ tone, children }: { tone: Tone; children: React.ReactNode }) {
  return <Chip size="small" label={children} sx={{ bgcolor: `var(--pill-${tone}-bg)`, color: `var(--pill-${tone}-fg)`, height: 22, fontSize: 12 }} />
}

export function OriginPill({ origin }: { origin: Origin }) {
  return origin === 'ai' ? <Pill tone="violet">IA</Pill> : <Pill tone="neutral">Manual</Pill>
}

export function QuoteStatusPill({ quote }: { quote: Pick<Quote, 'status' | 'needs_review'> }) {
  if (quote.status === 'approved') return <Pill tone="blue">Aprovado</Pill>
  if (quote.status === 'discarded') return <Pill tone="muted">Descartado</Pill>
  return quote.needs_review ? <Pill tone="amber">Revisar</Pill> : <Pill tone="green">Pronto</Pill>
}

const RENTAL: Record<Rental['status'], { label: string; tone: Tone }> = {
  reserved: { label: 'Reservada', tone: 'blue' },
  out: { label: 'Com o cliente', tone: 'amber' },
  returned: { label: 'Devolvida', tone: 'green' },
}

export function RentalStatusPill({ status }: { status: Rental['status'] }) {
  return <Pill tone={RENTAL[status].tone}>{RENTAL[status].label}</Pill>
}

const REQUEST: Record<RequestStatus, { label: string; tone: Tone }> = {
  pending: { label: 'Na fila', tone: 'neutral' },
  processing: { label: 'Lendo', tone: 'neutral' },
  ready: { label: 'Pronto', tone: 'green' },
  needs_review: { label: 'Revisar', tone: 'amber' },
  approved: { label: 'Aprovado', tone: 'blue' },
  discarded: { label: 'Descartado', tone: 'muted' },
  failed: { label: 'Falhou', tone: 'red' },
}

export function RequestStatusPill({ status }: { status: RequestStatus }) {
  return <Pill tone={REQUEST[status].tone}>{REQUEST[status].label}</Pill>
}
