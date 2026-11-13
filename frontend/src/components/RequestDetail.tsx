import { useEffect, useState } from 'react'
import { Alert, Box, Button, CircularProgress, Collapse, Typography } from '@mui/material'
import ExpandMore from '@mui/icons-material/ExpandMore'
import { api } from '../api'
import { clock } from '../format'
import { RequestStatusPill } from './Pill'
import QuoteEditor from './QuoteEditor'
import type { Catalog, Customer, RentalRequest, ToolCall } from '../types'

interface Props {
  summary: RentalRequest
  catalog: Catalog
  customers: Customer[]
  onCustomerSaved: () => Promise<void>
  onChange: (request: RentalRequest) => void
}

export default function RequestDetail({ summary, catalog, customers, onChange, onCustomerSaved }: Props) {
  const [detail, setDetail] = useState<RentalRequest | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [traceOpen, setTraceOpen] = useState(false)

  useEffect(() => {
    api.request(summary.id).then(setDetail)
  }, [summary.id, summary.status])

  const request = detail ?? summary
  const working = request.status === 'pending' || request.status === 'processing'
  const closed = request.status === 'approved' || request.status === 'discarded'

  async function run(action: () => Promise<unknown>) {
    setError(null)
    try {
      await action()
      const updated = await api.request(summary.id)
      setDetail(updated)
      onChange(updated)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Algo deu errado')
    }
  }

  return (
    <Box sx={{ maxWidth: 880, mx: 'auto', p: { xs: 2, md: 4 } }}>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2.5 }}>
        <Typography variant="h1" sx={{ flex: 1 }}>
          {request.customer_name ?? `Pedido #${request.id}`}
        </Typography>
        <RequestStatusPill status={request.status} />
        {!closed && !working && (
          <>
            <Button size="small" color="inherit" onClick={() => run(() => api.reprocess(request.id))}>Ler de novo</Button>
            <Button size="small" color="inherit" onClick={() => run(() => api.discard(request.id))}>Descartar</Button>
          </>
        )}
      </Box>

      <Box sx={{ bgcolor: 'var(--lq-subtle)', border: '1px solid var(--lq-border)', borderRadius: '4px 16px 16px 16px', px: 2, py: 1.5, maxWidth: 560, mb: 3 }}>
        <Typography sx={{ whiteSpace: 'pre-wrap' }}>{request.raw_text}</Typography>
        <Typography variant="caption" color="text.secondary">Recebido às {clock(request.created_at)}</Typography>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

      {working && (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, color: 'text.secondary', py: 4 }}>
          <CircularProgress size={18} thickness={5} sx={{ color: 'inherit', animationDuration: '600ms' }} />
          <Typography variant="body2">Lendo o pedido e consultando o catálogo.</Typography>
        </Box>
      )}

      {request.status === 'failed' && (
        <Alert severity="error" action={<Button color="inherit" size="small" onClick={() => run(() => api.reprocess(request.id))}>Tentar de novo</Button>}>
          A leitura automática falhou. {request.error}
        </Alert>
      )}

      {!working && request.quote && (
        <div className="enter">
          <QuoteEditor
            key={request.quote.id}
            quote={request.quote}
            catalog={catalog}
            customers={customers}
            onCustomerSaved={onCustomerSaved}
            readOnly={closed}
            onSave={(edit) => run(() => api.updateQuote(request.quote!.id, edit))}
            onApprove={() => run(() => api.approveQuote(request.quote!.id))}
          />
        </div>
      )}

      {!working && request.ai_run && (
        <Box sx={{ mt: 4 }}>
          <Button
            size="small"
            color="inherit"
            onClick={() => setTraceOpen((open) => !open)}
            endIcon={<ExpandMore sx={{ transform: traceOpen ? 'rotate(180deg)' : 'none', transition: 'transform 200ms var(--ease-out)' }} />}
            sx={{ color: 'text.secondary' }}
          >
            Como a IA leu este pedido
          </Button>
          <Collapse in={traceOpen} timeout={200}>
            <Trace calls={request.ai_run.tool_calls ?? []} />
            <Typography variant="caption" color="text.secondary">
              {request.ai_run.model} · {request.ai_run.tokens.toLocaleString('pt-BR')} tokens · {(request.ai_run.latency_ms / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} s
            </Typography>
          </Collapse>
        </Box>
      )}
    </Box>
  )
}

const TOOL_LABEL: Record<string, string> = {
  search_equipment: 'Buscou no catálogo',
  find_delivery_zone: 'Conferiu a entrega',
}

function Trace({ calls }: { calls: ToolCall[] }) {
  if (calls.length === 0) {
    return <Typography variant="body2" color="text.secondary" sx={{ py: 1 }}>A IA não fez consultas para este pedido.</Typography>
  }

  return (
    <Box sx={{ py: 1 }}>
      {calls.map((call, index) => (
        <Box key={index} sx={{ borderLeft: 2, borderColor: 'divider', pl: 1.5, mb: 1.5 }}>
          <Typography variant="body2" sx={{ fontWeight: 600 }}>{TOOL_LABEL[call.name] ?? call.name}</Typography>
          <Typography variant="caption" component="pre" sx={{ m: 0, color: 'text.secondary', whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace' }}>
            {JSON.stringify(call.arguments)}
            {'\n→ '}
            {summarize(call)}
          </Typography>
        </Box>
      ))}
    </Box>
  )
}

function summarize(call: ToolCall): string {
  const result = call.result as Record<string, any> | null

  if (call.name === 'find_delivery_zone') {
    return result?.delivers ? result.zone : 'sem zona de entrega cadastrada'
  }

  const matches: any[] = result?.matches ?? []
  if (matches.length === 0) return 'nada no catálogo'

  return matches
    .map((match) => {
      const stock = match.available === undefined ? '' : match.available ? ' (disponível)' : ` (${match.available_quantity} livres)`
      const alternatives = match.alternatives?.length ? `; alternativas: ${match.alternatives.map((alternative: any) => alternative.name).join(', ')}` : ''
      return `${match.name}${stock}${alternatives}`
    })
    .join(' | ')
}
