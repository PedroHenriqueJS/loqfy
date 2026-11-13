import { useRef, useState } from 'react'
import { Alert, Box, Button, Checkbox, CircularProgress, FormControlLabel, MenuItem, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import Add from '@mui/icons-material/Add'
import AutoAwesome from '@mui/icons-material/AutoAwesome'
import AccountBalanceOutlined from '@mui/icons-material/AccountBalanceOutlined'
import SouthWestOutlined from '@mui/icons-material/SouthWestOutlined'
import NorthEastOutlined from '@mui/icons-material/NorthEastOutlined'
import TimelineOutlined from '@mui/icons-material/TimelineOutlined'
import { api } from '../api'
import type { EntryEdit } from '../api'
import { day, money, month, today } from '../format'
import { useData } from '../hooks'
import { BarList, ColumnChart, LineChart } from '../components/charts'
import FormDialog from '../components/FormDialog'
import Checks from '../components/Checks'
import { Pill } from '../components/Pill'
import { Card, Empty, Page, PageHeader, Segmented, Stat } from '../components/ui'
import type { FinanceSummary, FinancialEntry, ReceiptRead } from '../types'

type Filter = 'open' | 'overdue' | 'paid' | 'all'

export default function Finance() {
  const { data: summary, reload: reloadSummary } = useData(api.financeSummary)
  const { data: entries, reload: reloadEntries } = useData(api.entries)
  const [filter, setFilter] = useState<Filter>('open')
  const [editing, setEditing] = useState<FinancialEntry | 'new' | null>(null)
  const [receipt, setReceipt] = useState<ReceiptRead | null>(null)

  const reload = async () => {
    await Promise.all([reloadSummary(), reloadEntries()])
  }

  const counts = {
    open: entries?.filter((entry) => entry.status !== 'paid').length ?? 0,
    overdue: entries?.filter((entry) => entry.status === 'overdue').length ?? 0,
  }
  const visible = (entries ?? [])
    .filter((entry) => (filter === 'all' ? true : filter === 'open' ? entry.status !== 'paid' : entry.status === filter))
    .sort((a, b) => (filter === 'open' || filter === 'overdue' ? a.due_date.localeCompare(b.due_date) : b.due_date.localeCompare(a.due_date)))
    .slice(0, 80)

  return (
    <Page>
      <PageHeader
        title="Financeiro"
        subtitle="Fluxo de caixa da locadora. Cada orçamento aprovado vira uma conta a receber aqui."
        actions={
          <>
            <ReceiptButton onRead={setReceipt} />
            <Button variant="contained" startIcon={<Add />} onClick={() => setEditing('new')}>Novo lançamento</Button>
          </>
        }
      />

      {summary && (
        <>
          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr 1fr', lg: 'repeat(4, 1fr)' }, gap: 2, mb: 2 }}>
            <Stat icon={<AccountBalanceOutlined />} label="Saldo em caixa" value={money(summary.balance)} note="Entradas menos saídas até hoje" />
            <Stat icon={<SouthWestOutlined />} label="Entradas no mês" value={money(summary.income_this_month)} />
            <Stat icon={<NorthEastOutlined />} label="Saídas no mês" value={money(summary.expense_this_month)} />
            <Stat
              icon={<TimelineOutlined />}
              label="Saldo previsto em 30 dias"
              value={money(summary.projected_balance_in_30_days)}
              note={`+ ${money(summary.receivable_next_30_days)} a receber, − ${money(summary.payable_next_30_days)} a pagar`}
            />
          </Box>

          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: 'minmax(0, 1.4fr) minmax(0, 1fr)' }, gap: 2, mb: 2 }}>
            <Card title="Entradas e saídas" hint="Valores pagos em cada mês">
              <ColumnChart
                caption="Entradas e saídas pagas por mês"
                series={[{ name: 'Entradas', color: 'var(--lq-series-1)' }, { name: 'Saídas', color: 'var(--lq-series-2)' }]}
                data={summary.monthly.map((row) => ({ label: month(row.month), values: [row.income, row.expense] }))}
                format={money}
              />
            </Card>
            <Card title="Saldo de caixa" hint="No fechamento de cada mês">
              <LineChart
                caption="Saldo de caixa no fim de cada mês"
                series={[{ name: 'Saldo', color: 'var(--lq-series-1)' }]}
                data={summary.monthly.map((row) => ({ label: month(row.month), values: [row.balance] }))}
                format={money}
              />
            </Card>
          </Box>
        </>
      )}

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: 'minmax(0, 2.2fr) minmax(0, 1fr)' }, gap: 2, alignItems: 'start' }}>
        <Card
          title="Lançamentos"
          flush
          action={
            <Segmented
              value={filter}
              onChange={setFilter}
              options={[
                { value: 'open', label: 'Em aberto', count: counts.open },
                { value: 'overdue', label: 'Atrasados', count: counts.overdue },
                { value: 'paid', label: 'Pagos' },
                { value: 'all', label: 'Todos' },
              ]}
            />
          }
        >
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Vencimento</TableCell>
                <TableCell>Descrição</TableCell>
                <TableCell align="right">Valor</TableCell>
                <TableCell>Situação</TableCell>
                <TableCell />
              </TableRow>
            </TableHead>
            <TableBody>
              {visible.map((entry) => (
                <TableRow key={entry.id} hover onClick={() => setEditing(entry)} sx={{ cursor: 'pointer', '&:last-child td': { borderBottom: 0 } }}>
                  <TableCell sx={{ whiteSpace: 'nowrap', color: 'text.secondary' }}>{day(entry.due_date)}</TableCell>
                  <TableCell>
                    <Typography variant="body2" sx={{ fontWeight: 500 }}>{entry.description}</Typography>
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>{entry.category}</Typography>
                    {entry.origin === 'ai' && <Box component="span" sx={{ ml: 1 }}><Pill tone="violet">Lido de comprovante</Pill></Box>}
                  </TableCell>
                  <TableCell align="right" sx={{ whiteSpace: 'nowrap', fontWeight: 600 }}>{entry.type === 'income' ? '+ ' : '− '}{money(entry.amount)}</TableCell>
                  <TableCell><EntryStatus entry={entry} /></TableCell>
                  <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
                    {entry.status !== 'paid' && (
                      <Button
                        size="small"
                        onClick={async (event) => {
                          event.stopPropagation()
                          await api.togglePaid(entry.id)
                          await reload()
                        }}
                      >
                        {entry.type === 'income' ? 'Marcar recebido' : 'Marcar pago'}
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {entries && visible.length === 0 && <Empty>Nenhum lançamento neste filtro.</Empty>}
        </Card>

        {summary && (
          <Card title="Para onde vai o dinheiro" hint="Despesas pagas em 90 dias, por categoria">
            <BarList color="var(--lq-series-2)" rows={summary.expenses_by_category.map((row) => ({ label: row.category, value: row.amount, display: money(row.amount) }))} />
          </Card>
        )}
      </Box>

      {editing && summary && <EntryForm entry={editing === 'new' ? null : editing} categories={summary.categories} onClose={() => setEditing(null)} onSaved={reload} />}
      {receipt && summary && <EntryForm entry={null} receipt={receipt} categories={summary.categories} onClose={() => setReceipt(null)} onSaved={reload} />}
    </Page>
  )
}

function EntryStatus({ entry }: { entry: FinancialEntry }) {
  if (entry.status === 'paid') return <Pill tone="green">{entry.type === 'income' ? 'Recebido' : 'Pago'}</Pill>
  if (entry.status === 'overdue') return <Pill tone="red">Atrasado</Pill>
  return <Pill tone="neutral">{entry.type === 'income' ? 'A receber' : 'A pagar'}</Pill>
}

export function ReceiptButton({ onRead, large }: { onRead: (receipt: ReceiptRead) => void; large?: boolean }) {
  const input = useRef<HTMLInputElement>(null)
  const [reading, setReading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  return (
    <Box>
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
          setReading(true)
          setError(null)
          try {
            onRead(await api.readReceipt(file))
          } catch (e) {
            setError(e instanceof Error ? e.message : 'Não foi possível ler o comprovante')
          } finally {
            setReading(false)
          }
        }}
      />
      <Button
        variant={large ? 'contained' : 'outlined'}
        size={large ? 'large' : 'medium'}
        disabled={reading}
        startIcon={reading ? <CircularProgress size={15} thickness={6} color="inherit" sx={{ animationDuration: '600ms' }} /> : <AutoAwesome sx={large ? undefined : { color: 'var(--lq-violet)' }} />}
        onClick={() => input.current?.click()}
        sx={large ? { bgcolor: 'var(--lq-violet)', color: 'var(--lq-on-violet)', '&:hover': { bgcolor: 'var(--lq-violet-hover)' } } : undefined}
      >
        {reading ? 'Lendo o comprovante' : 'Ler comprovante'}
      </Button>
      {error && <Alert severity="error" sx={{ mt: 1 }}>{error}</Alert>}
    </Box>
  )
}

interface EntryFormProps {
  entry: FinancialEntry | null
  receipt?: ReceiptRead
  categories: FinanceSummary['categories']
  onClose: () => void
  onSaved: () => Promise<void>
}

export function EntryForm({ entry, receipt, categories, onClose, onSaved }: EntryFormProps) {
  const read = receipt?.suggestion
  const [edit, setEdit] = useState({
    type: entry?.type ?? ('expense' as FinancialEntry['type']),
    category: entry?.category ?? read?.category ?? categories.expense[0],
    description: entry?.description ?? [read?.description, read?.supplier].filter(Boolean).join(', '),
    amount: entry ? String(entry.amount).replace('.', ',') : read?.amount != null ? read.amount.toFixed(2).replace('.', ',') : '',
    due_date: entry?.due_date ?? read?.date ?? today(),
    paid: entry ? entry.paid_at !== null : Boolean(receipt),
  })
  const amount = Number(edit.amount.replace(/\./g, '').replace(',', '.'))
  const payload: EntryEdit = {
    type: edit.type,
    category: edit.category,
    description: edit.description.trim(),
    amount,
    due_date: edit.due_date,
    paid_at: edit.paid ? (entry?.paid_at ?? (receipt ? edit.due_date : today())) : null,
    ...(receipt ? { origin: 'ai' as const, receipt_photo: receipt.receipt_photo } : {}),
  }
  const photoUrl = receipt?.receipt_photo_url ?? entry?.receipt_photo_url

  return (
    <FormDialog
      title={entry ? 'Editar lançamento' : receipt ? 'Conferir comprovante' : 'Novo lançamento'}
      aside={
        photoUrl && (
          <Box>
            <Box component="img" src={photoUrl} alt="Foto do comprovante" sx={{ width: '100%', maxHeight: 460, objectFit: 'contain', bgcolor: 'var(--lq-subtle)', borderRadius: '10px', display: 'block' }} />
            {receipt && (
              <Typography variant="caption" sx={{ display: 'block', color: 'text.secondary', mt: 1 }}>
                A IA preencheu os campos a partir desta foto. Confira cada um antes de salvar.
              </Typography>
            )}
          </Box>
        )
      }
      canSave={payload.description !== '' && amount > 0 && edit.due_date !== ''}
      onClose={onClose}
      onSave={async () => {
        await (entry ? api.updateEntry(entry.id, payload) : api.createEntry(payload))
        await onSaved()
      }}
      onDelete={entry ? async () => { await api.deleteEntry(entry.id); await onSaved() } : undefined}
    >
      {receipt && <Checks checks={receipt.checks} />}
      {receipt && !read && <Alert severity="warning">A IA não conseguiu ler este comprovante. Preencha os campos olhando a foto.</Alert>}
      {read && !read.is_receipt && <Alert severity="warning">Esta foto não parece ser um comprovante de pagamento.</Alert>}
      {read && read.is_receipt && !read.legible && <Alert severity="warning">A foto está difícil de ler. Confira os valores com atenção.</Alert>}
      {read && read.is_receipt && read.amount === null && <Alert severity="warning">O valor total não foi encontrado na foto.</Alert>}
      <Segmented
        value={edit.type}
        onChange={(type) => setEdit({ ...edit, type, category: categories[type][0] })}
        options={[{ value: 'expense', label: 'Saída' }, { value: 'income', label: 'Entrada' }]}
      />
      <TextField size="small" label="Descrição" autoFocus={!receipt} required value={edit.description} onChange={(event) => setEdit({ ...edit, description: event.target.value })} />
      <TextField size="small" select label="Categoria" value={edit.category} onChange={(event) => setEdit({ ...edit, category: event.target.value })}>
        {categories[edit.type].map((category) => <MenuItem key={category} value={category}>{category}</MenuItem>)}
      </TextField>
      <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2 }}>
        <TextField size="small" label="Valor (R$)" required slotProps={{ htmlInput: { inputMode: 'decimal' } }} value={edit.amount} onChange={(event) => setEdit({ ...edit, amount: event.target.value })} />
        <TextField size="small" type="date" label="Vencimento" required value={edit.due_date} onChange={(event) => setEdit({ ...edit, due_date: event.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
      </Box>
      <FormControlLabel control={<Checkbox checked={edit.paid} onChange={(event) => setEdit({ ...edit, paid: event.target.checked })} />} label={edit.type === 'income' ? 'Já recebido' : 'Já pago'} />
    </FormDialog>
  )
}
