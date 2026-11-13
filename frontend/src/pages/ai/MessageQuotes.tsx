import { useEffect, useState } from 'react'
import { Alert, Box, Button, ButtonBase, Chip, CircularProgress, TextField, Typography, useMediaQuery } from '@mui/material'
import type { Theme } from '@mui/material/styles'
import ArrowBack from '@mui/icons-material/ArrowBack'
import { api } from '../../api'
import { clock } from '../../format'
import { useData } from '../../hooks'
import { RequestStatusPill } from '../../components/Pill'
import RequestDetail from '../../components/RequestDetail'
import type { RentalRequest } from '../../types'

const EXAMPLES = [
  'Boa tarde! Preciso de uma betoneira e 20 andaimes pro fim de semana, entrega no Campeche. Aqui é o Marcos',
  'tem rolo compactador pra amanhã? obra em palhoça',
  'bom dia qero 10 andaimes e 1 furadera pra segunda e terça, entrega em sao jose',
  'preciso de um gerador amanhã',
]

const isWorking = (request: RentalRequest) => request.status === 'pending' || request.status === 'processing'

export default function MessageQuotes() {
  const [catalog, setCatalog] = useState<Awaited<ReturnType<typeof api.catalog>> | null>(null)
  const [polling, setPolling] = useState(false)
  const { data: requests, reload, setData } = useData(api.requests, polling ? 2000 : 10000)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [text, setText] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const { data: customers, reload: reloadCustomers } = useData(api.customers)

  useEffect(() => {
    api.catalog().then(setCatalog)
  }, [])

  useEffect(() => {
    setPolling(requests?.some(isWorking) ?? false)
  }, [requests])

  const phone = useMediaQuery((theme: Theme) => theme.breakpoints.down('md'))

  useEffect(() => {
    if (!phone && selectedId === null && requests?.length) setSelectedId(requests[0].id)
  }, [requests, selectedId, phone])

  async function send() {
    if (!text.trim() || sending) return
    setSending(true)
    setError(null)
    try {
      const created = await api.createRequest(text.trim())
      setText('')
      setSelectedId(created.id)
      await reload()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível enviar')
    } finally {
      setSending(false)
    }
  }

  const replace = (updated: RentalRequest) => setData((current) => current?.map((request) => (request.id === updated.id ? updated : request)) ?? null)
  const selected = requests?.find((request) => request.id === selectedId) ?? null

  return (
    <Box sx={{ display: 'flex', height: '100%' }}>
      <Box sx={{ width: { xs: '100%', md: 380 }, flexShrink: 0, borderRight: { md: 1 }, borderColor: 'divider', display: { xs: phone && selected ? 'none' : 'flex', md: 'flex' }, flexDirection: 'column', bgcolor: 'background.paper', overflowY: { xs: 'auto', md: 'visible' } }}>
        <Box sx={{ p: 2, borderBottom: 1, borderColor: 'divider' }}>
          <Typography variant="h1">Orçamento por mensagem</Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5, mb: 1.5 }}>
            A IA lê a mensagem do cliente e deixa o orçamento montado. Ele entra na lista de Orçamentos como qualquer outro.
          </Typography>
          <TextField
            multiline
            minRows={3}
            maxRows={8}
            fullWidth
            size="small"
            placeholder="Cole aqui a mensagem do cliente, do jeito que chegou"
            value={text}
            onChange={(event) => setText(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) send()
            }}
          />
          <Box sx={{ display: 'flex', gap: 0.75, flexWrap: 'wrap', mt: 1 }}>
            {EXAMPLES.map((example, index) => (
              <Chip key={example} size="small" variant="outlined" label={`Exemplo ${index + 1}`} onClick={() => setText(example)} />
            ))}
          </Box>
          <Button fullWidth variant="contained" sx={{ mt: 1.5 }} disabled={!text.trim() || sending} onClick={send}>
            Gerar orçamento
          </Button>
          {error && <Alert severity="error" sx={{ mt: 1 }}>{error}</Alert>}
        </Box>

        <Box sx={{ flex: 1, overflow: 'auto' }}>
          {requests?.length === 0 && (
            <Typography color="text.secondary" sx={{ p: 3, textAlign: 'center' }} variant="body2">
              Nenhum pedido ainda. Cole uma mensagem acima ou use um dos exemplos.
            </Typography>
          )}
          {requests?.map((request) => (
            <ButtonBase
              key={request.id}
              onClick={() => setSelectedId(request.id)}
              className="enter"
              sx={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                px: 2,
                py: 1.5,
                borderBottom: 1,
                borderColor: 'divider',
                bgcolor: request.id === selectedId ? 'var(--lq-subtle)' : 'transparent',
                boxShadow: request.id === selectedId ? 'inset 3px 0 0 var(--lq-blue)' : 'none',
                '@media (hover: hover) and (pointer: fine)': { '&:hover': { bgcolor: 'var(--lq-hover)' } },
              }}
            >
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                <Typography variant="body2" sx={{ fontWeight: 600, flex: 1 }} noWrap>
                  {request.customer_name ?? `Pedido #${request.id}`}
                </Typography>
                {isWorking(request) && <CircularProgress size={12} thickness={6} sx={{ color: 'text.secondary', animationDuration: '600ms' }} />}
                <RequestStatusPill status={request.status} />
              </Box>
              <Typography variant="body2" color="text.secondary" sx={{ display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                {request.raw_text}
              </Typography>
              <Typography variant="caption" color="text.secondary">{clock(request.created_at)}</Typography>
            </ButtonBase>
          ))}
        </Box>
      </Box>

      <Box sx={{ flex: 1, minWidth: 0, overflow: 'auto', display: { xs: phone && selected ? 'block' : 'none', md: 'block' } }}>
        {phone && selected && (
          <Button size="small" color="inherit" startIcon={<ArrowBack />} onClick={() => setSelectedId(null)} sx={{ color: 'text.secondary', m: 1.5, mb: 0 }}>
            Pedidos
          </Button>
        )}
        {selected && catalog ? (
          <RequestDetail key={selected.id} summary={selected} catalog={catalog} customers={customers ?? []} onChange={replace} onCustomerSaved={reloadCustomers} />
        ) : (
          <Box sx={{ height: '100%', display: 'grid', placeItems: 'center', color: 'text.secondary' }}>
            <Typography variant="body2">Selecione um pedido para ver o orçamento.</Typography>
          </Box>
        )}
      </Box>
    </Box>
  )
}
