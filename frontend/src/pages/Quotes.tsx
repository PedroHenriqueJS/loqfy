import { useState } from 'react'
import { Box, Button, ButtonBase, InputAdornment, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import Add from '@mui/icons-material/Add'
import AutoAwesome from '@mui/icons-material/AutoAwesome'
import Search from '@mui/icons-material/Search'
import { api } from '../api'
import { money, period } from '../format'
import { useData } from '../hooks'
import { go } from '../router'
import { OriginPill, QuoteStatusPill } from '../components/Pill'
import { Card, Empty, Page, PageHeader, Segmented } from '../components/ui'
import type { Quote } from '../types'

type Filter = 'open' | 'review' | 'approved' | 'all'

const matches = (quote: Quote, filter: Filter) =>
  filter === 'all' || (filter === 'open' && quote.status === 'draft') || (filter === 'review' && quote.status === 'draft' && quote.needs_review) || (filter === 'approved' && quote.status === 'approved')

export default function Quotes() {
  const { data: quotes } = useData(api.quotes, 10000)
  const [filter, setFilter] = useState<Filter>('open')
  const [search, setSearch] = useState('')

  const count = (which: Filter) => quotes?.filter((quote) => matches(quote, which)).length
  const term = search.trim().toLowerCase()
  const visible = quotes?.filter((quote) => matches(quote, filter) && (term === '' || (quote.customer_name ?? '').toLowerCase().includes(term)))

  return (
    <Page>
      <PageHeader
        title="Orçamentos"
        subtitle="Todos os orçamentos, feitos à mão ou montados pela IA a partir de uma mensagem."
        actions={
          <>
            <Button variant="outlined" startIcon={<AutoAwesome sx={{ color: 'var(--lq-violet)' }} />} onClick={() => go('ia/mensagens')}>A partir de mensagem</Button>
            <Button variant="contained" startIcon={<Add />} onClick={() => go('orcamentos/novo')}>Novo orçamento</Button>
          </>
        }
      />

      <Box sx={{ display: 'flex', gap: 2, mb: 2, flexWrap: 'wrap', alignItems: 'center' }}>
        <Segmented
          value={filter}
          onChange={setFilter}
          options={[
            { value: 'open', label: 'Em aberto', count: count('open') },
            { value: 'review', label: 'Para revisar', count: count('review') },
            { value: 'approved', label: 'Aprovados', count: count('approved') },
            { value: 'all', label: 'Todos', count: count('all') },
          ]}
        />
        <TextField
          size="small"
          placeholder="Buscar cliente"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          sx={{ ml: { sm: 'auto' }, width: { xs: '100%', sm: 260 }, '& .MuiOutlinedInput-root': { bgcolor: 'var(--lq-paper)' } }}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><Search fontSize="small" /></InputAdornment> } }}
        />
      </Box>

      <Box sx={{ display: { xs: 'flex', sm: 'none' }, flexDirection: 'column', gap: 1.25 }}>
        {visible?.map((quote) => (
          <ButtonBase
            key={quote.id}
            onClick={() => go(`orcamentos/${quote.id}`)}
            sx={{ display: 'block', textAlign: 'left', p: 1.75, borderRadius: '14px', bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)' }}
          >
            <Box sx={{ display: 'flex', alignItems: 'baseline', gap: 1 }}>
              <Typography sx={{ fontWeight: 600, flex: 1 }} noWrap>{quote.customer_name ?? 'Sem nome'}</Typography>
              <Typography sx={{ fontWeight: 600 }}>{money(quote.total)}</Typography>
            </Box>
            <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.25 }} noWrap>
              {quote.items.map((item) => `${item.quantity} × ${item.name}`).join(', ') || 'Nenhum equipamento'}
            </Typography>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75, mt: 1, flexWrap: 'wrap' }}>
              <Typography variant="caption" sx={{ color: 'text.secondary', flex: 1 }}>{period(quote.start_date, quote.end_date)}</Typography>
              <QuoteStatusPill quote={quote} />
              <OriginPill origin={quote.origin} />
            </Box>
          </ButtonBase>
        ))}
        {visible?.length === 0 && <Empty>{quotes?.length === 0 ? 'Nenhum orçamento ainda.' : 'Nenhum orçamento neste filtro.'}</Empty>}
      </Box>

      <Card flush sx={{ display: { xs: 'none', sm: 'block' } }}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Nº</TableCell>
              <TableCell>Cliente</TableCell>
              <TableCell>Equipamentos</TableCell>
              <TableCell>Período</TableCell>
              <TableCell align="right">Total</TableCell>
              <TableCell>Situação</TableCell>
              <TableCell>Origem</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {visible?.map((quote) => (
              <TableRow key={quote.id} hover onClick={() => go(`orcamentos/${quote.id}`)} sx={{ cursor: 'pointer', '&:last-child td': { borderBottom: 0 } }}>
                <TableCell sx={{ color: 'text.secondary' }}>{quote.id}</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>{quote.customer_name ?? 'Sem nome'}</TableCell>
                <TableCell sx={{ maxWidth: 320 }}>
                  <Typography variant="body2" noWrap>{quote.items.map((item) => `${item.quantity} × ${item.name}`).join(', ') || 'Nenhum'}</Typography>
                </TableCell>
                <TableCell sx={{ whiteSpace: 'nowrap' }}>{period(quote.start_date, quote.end_date)}</TableCell>
                <TableCell align="right" sx={{ whiteSpace: 'nowrap', fontWeight: 600 }}>{money(quote.total)}</TableCell>
                <TableCell><QuoteStatusPill quote={quote} /></TableCell>
                <TableCell><OriginPill origin={quote.origin} /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        {visible?.length === 0 && (
          <Empty>
            {quotes?.length === 0 ? 'Nenhum orçamento ainda. Crie um à mão ou cole a mensagem de um cliente para a IA montar.' : 'Nenhum orçamento neste filtro.'}
          </Empty>
        )}
      </Card>
    </Page>
  )
}
