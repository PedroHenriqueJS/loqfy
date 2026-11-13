import { useEffect, useState } from 'react'
import { Alert, Box, Button, Typography } from '@mui/material'
import ArrowBack from '@mui/icons-material/ArrowBack'
import { api } from '../api'
import type { QuoteEdit } from '../api'
import { useData } from '../hooks'
import { go } from '../router'
import QuoteEditor from '../components/QuoteEditor'
import { OriginPill, QuoteStatusPill } from '../components/Pill'
import type { Quote } from '../types'

export default function QuoteDetail({ id }: { id: number | null }) {
  const { data: catalog } = useData(api.catalog)
  const { data: customers, reload: reloadCustomers } = useData(api.customers)
  const [quote, setQuote] = useState<Quote | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (id !== null) api.quote(id).then(setQuote, () => setError('Orçamento não encontrado.'))
  }, [id])

  async function run(action: () => Promise<Quote>, after?: (saved: Quote) => void) {
    setError(null)
    try {
      const saved = await action()
      setQuote(saved)
      after?.(saved)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Algo deu errado')
    }
  }

  const save = (edit: QuoteEdit) =>
    id === null ? run(() => api.createQuote(edit), (saved) => go(`orcamentos/${saved.id}`)) : run(() => api.updateQuote(id, edit))

  const ready = catalog && customers && (id === null || quote)

  return (
    <Box sx={{ height: '100%', overflow: 'auto' }}>
      <Box sx={{ maxWidth: 880, mx: 'auto', p: { xs: 2, md: 4 } }}>
        <Button size="small" color="inherit" startIcon={<ArrowBack />} href="#/orcamentos" sx={{ color: 'text.secondary', mb: 1.5, ml: -0.5 }}>
          Orçamentos
        </Button>

        <Box sx={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 1.5, mb: 2.5 }}>
          <Typography variant="h1" sx={{ flex: 1 }}>{id === null ? 'Novo orçamento' : `Orçamento nº ${id}`}</Typography>
          {quote && (
            <>
              <OriginPill origin={quote.origin} />
              <QuoteStatusPill quote={quote} />
              {quote.status === 'draft' && <Button size="small" color="inherit" onClick={() => run(() => api.discardQuote(quote.id))}>Descartar</Button>}
            </>
          )}
        </Box>

        {error && <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

        {quote?.rental_id && (
          <Alert
            severity="success"
            sx={{ mb: 2 }}
            action={
              <>
                <Button color="inherit" size="small" href={api.contractUrl(quote.rental_id)} target="_blank" rel="noopener">Contrato</Button>
                <Button color="inherit" size="small" href="#/locacoes">Ver locação</Button>
              </>
            }
          >
            Aprovado. A locação nº {quote.rental_id} está reservada e a conta a receber já está no Financeiro.
          </Alert>
        )}

        {quote?.origin === 'ai' && (
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Montado pela IA a partir de uma mensagem do cliente. Você pode alterar qualquer campo.
          </Typography>
        )}

        {ready && (
          <QuoteEditor
            key={quote?.id ?? 'new'}
            quote={id === null ? null : quote}
            catalog={catalog}
            customers={customers}
            onCustomerSaved={reloadCustomers}
            readOnly={quote !== null && quote.status !== 'draft'}
            onSave={save}
            onApprove={() => run(() => api.approveQuote(quote!.id))}
          />
        )}
      </Box>
    </Box>
  )
}
