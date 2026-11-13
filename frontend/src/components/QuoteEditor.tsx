import { useMemo, useState } from 'react'
import { Autocomplete, Box, Button, IconButton, Switch, TextField, Tooltip, Typography } from '@mui/material'
import Add from '@mui/icons-material/Add'
import Close from '@mui/icons-material/Close'
import type { QuoteEdit } from '../api'
import { money, period } from '../format'
import { Pill } from './Pill'
import CustomerForm from './CustomerForm'
import type { Catalog, Customer, Issue, Quote } from '../types'

interface Props {
  quote: Quote | null
  catalog: Catalog
  customers: Customer[]
  readOnly: boolean
  onSave: (edit: QuoteEdit) => Promise<void>
  onApprove: () => Promise<void>
  onCustomerSaved: () => Promise<void>
}

interface Line {
  key: number
  equipment_id: number | null
  quantity: number
  requested_term: string | null
}

const BLANK: Quote = {
  id: 0,
  status: 'draft',
  origin: 'manual',
  needs_review: false,
  rental_request_id: null,
  customer_id: null,
  customer_name: null,
  customer_verified: false,
  rental_id: null,
  created_at: null,
  start_date: null,
  end_date: null,
  days: 0,
  neighborhood: null,
  delivery_zone: null,
  delivery_fee: 0,
  subtotal: 0,
  discount: 0,
  total: 0,
  confidence: null,
  issues: [],
  items: [],
}

const toEdit = (quote: Quote) => ({
  customerId: quote.customer_id,
  customerName: quote.customer_name ?? '',
  lines: quote.items.map((item): Line => ({ key: item.id, equipment_id: item.equipment_id, quantity: item.quantity, requested_term: item.requested_term })),
  start: quote.start_date ?? '',
  end: quote.end_date ?? '',
  delivery: quote.delivery_zone !== null || quote.neighborhood !== null,
  neighborhood: quote.neighborhood ?? '',
})

export default function QuoteEditor({ quote: saved, catalog, customers, readOnly, onSave, onApprove, onCustomerSaved }: Props) {
  const isNew = saved === null
  const quote = saved ?? BLANK
  const initial = useMemo(() => toEdit(quote), [quote])
  const [edit, setEdit] = useState(initial)
  const [busy, setBusy] = useState<'save' | 'approve' | null>(null)
  const [customerForm, setCustomerForm] = useState<Customer | 'new' | null>(null)

  const dirty = JSON.stringify(edit) !== JSON.stringify(initial)
  const complete = edit.lines.every((line) => line.equipment_id !== null && line.quantity >= 1)
  const savedById = new Map(quote.items.map((item) => [item.id, item]))
  const hasItems = edit.lines.length > 0
  const linked = customers.find((customer) => customer.id === edit.customerId) ?? null
  const approvable = linked !== null && linked.document_verified_at !== null

  const setLine = (key: number, patch: Partial<Line>) =>
    setEdit((current) => ({ ...current, lines: current.lines.map((line) => (line.key === key ? { ...line, ...patch } : line)) }))

  async function save() {
    setBusy('save')
    await onSave({
      customer_id: edit.customerId,
      customer_name: edit.customerName.trim() || null,
      items: edit.lines.map((line) => ({ equipment_id: line.equipment_id!, quantity: line.quantity, requested_term: line.requested_term })),
      start_date: edit.start || null,
      end_date: edit.end || null,
      delivery: edit.delivery,
      neighborhood: edit.delivery ? edit.neighborhood.trim() || null : null,
    })
    setBusy(null)
  }

  async function approve() {
    setBusy('approve')
    await onApprove()
    setBusy(null)
  }

  const systemIssues = quote.issues.filter((issue) => issue.source === 'system')
  const aiIssues = quote.issues.filter((issue) => issue.source === 'ai')

  return (
    <Box>
      {(systemIssues.length > 0 || aiIssues.length > 0) && (
        <Box sx={{ border: '1px solid var(--lq-warn-border)', bgcolor: 'var(--lq-warn-bg)', borderRadius: '14px', p: 2, mb: 3 }}>
          <Typography variant="h2" sx={{ mb: 1 }}>Confira antes de aprovar</Typography>
          <IssueList title="Conferido no sistema" issues={systemIssues} />
          <IssueList title="Observações da IA" issues={aiIssues} />
        </Box>
      )}

      <Box sx={{ bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '14px', boxShadow: 'var(--lq-shadow)' }}>
        <Section title="Cliente">
          <Autocomplete
            freeSolo
            size="small"
            disabled={readOnly}
            sx={{ maxWidth: 420 }}
            options={customers}
            getOptionLabel={(option) => (typeof option === 'string' ? option : option.name)}
            inputValue={edit.customerName}
            onInputChange={(_, name) => setEdit((current) => ({ ...current, customerName: name, customerId: customers.find((customer) => customer.name === name)?.id ?? null }))}
            renderInput={(params) => <TextField {...params} placeholder="Nome do cliente" />}
          />
          {!readOnly && (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 1, minHeight: 30, flexWrap: 'wrap' }}>
              {linked ? (
                linked.document_verified_at ? (
                  <Pill tone="green">Cadastrado · documento conferido</Pill>
                ) : (
                  <>
                    <Pill tone="amber">Documento não conferido</Pill>
                    <Button size="small" onClick={() => setCustomerForm(linked)}>Conferir no cadastro</Button>
                  </>
                )
              ) : edit.customerName.trim() ? (
                <>
                  <Pill tone="amber">Cliente não cadastrado</Pill>
                  <Button size="small" onClick={() => setCustomerForm('new')}>Cadastrar</Button>
                </>
              ) : null}
            </Box>
          )}
        </Section>

        <Section title="Equipamentos">
          {edit.lines.map((line) => {
            const row = savedById.get(line.key)
            const unchanged = row && row.equipment_id === line.equipment_id && row.quantity === line.quantity

            return (
              <Box
                key={line.key}
                sx={{
                  display: 'grid',
                  gridTemplateColumns: { xs: '84px minmax(0, 1fr) 32px', sm: 'minmax(0, 1fr) 84px 96px 32px' },
                  gridTemplateAreas: { xs: '"name name name" "qty price remove"', sm: '"name qty price remove"' },
                  gap: 1.5,
                  alignItems: 'start',
                  mb: { xs: 2.5, sm: 1.5 },
                }}
              >
                <Box sx={{ gridArea: 'name', minWidth: 0 }}>
                  <Autocomplete
                    size="small"
                    disabled={readOnly}
                    options={catalog.equipment}
                    groupBy={(option) => option.category}
                    getOptionLabel={(option) => option.name}
                    value={catalog.equipment.find((option) => option.id === line.equipment_id) ?? null}
                    onChange={(_, option) => setLine(line.key, { equipment_id: option?.id ?? null })}
                    renderInput={(params) => <TextField {...params} placeholder="Escolha o equipamento" />}
                  />
                  <Box sx={{ display: 'flex', gap: 0.75, mt: 0.5, flexWrap: 'wrap', alignItems: 'center', minHeight: 20 }}>
                    {line.requested_term && (
                      <Typography variant="caption" color="text.secondary">Cliente pediu “{line.requested_term}”</Typography>
                    )}
                    {unchanged && row.substitutes && <Pill tone="amber">No lugar de {row.substitutes}</Pill>}
                    {unchanged && !row.available && <Pill tone="red">Sem estoque no período</Pill>}
                  </Box>
                </Box>
                <TextField
                  size="small"
                  type="number"
                  label="Qtd."
                  disabled={readOnly}
                  value={line.quantity}
                  onChange={(event) => setLine(line.key, { quantity: Math.max(1, Number(event.target.value) || 1) })}
                  slotProps={{ htmlInput: { min: 1 } }}
                  sx={{ gridArea: 'qty' }}
                />
                <Box sx={{ gridArea: 'price', textAlign: 'right', pt: 0.75 }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>{unchanged ? money(row.line_total) : '—'}</Typography>
                  <Typography variant="caption" color="text.secondary" noWrap sx={{ display: 'block' }}>{unchanged ? `${money(row.daily_rate)}/dia` : 'a calcular'}</Typography>
                </Box>
                {!readOnly && (
                  <Tooltip title="Remover item">
                    <IconButton size="small" sx={{ gridArea: 'remove', mt: 0.25 }} onClick={() => setEdit((current) => ({ ...current, lines: current.lines.filter((other) => other.key !== line.key) }))}>
                      <Close fontSize="small" />
                    </IconButton>
                  </Tooltip>
                )}
              </Box>
            )
          })}

          {edit.lines.length === 0 && (
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>Nenhum equipamento neste orçamento.</Typography>
          )}

          {!readOnly && (
            <Button size="small" startIcon={<Add />} onClick={() => setEdit((current) => ({ ...current, lines: [...current.lines, { key: -Date.now(), equipment_id: null, quantity: 1, requested_term: null }] }))}>
              Adicionar equipamento
            </Button>
          )}
        </Section>

        <Section title="Período">
          <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
            <TextField size="small" type="date" label="Primeiro dia" disabled={readOnly} value={edit.start} onChange={(event) => setEdit({ ...edit, start: event.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
            <TextField size="small" type="date" label="Último dia" disabled={readOnly} value={edit.end} onChange={(event) => setEdit({ ...edit, end: event.target.value })} slotProps={{ inputLabel: { shrink: true }, htmlInput: { min: edit.start || undefined } }} />
            {!dirty && !isNew && (
              <Typography variant="body2" color="text.secondary">
                {period(quote.start_date, quote.end_date)}
                {quote.days > 0 && ` · ${quote.days} ${quote.days === 1 ? 'diária' : 'diárias'}`}
              </Typography>
            )}
          </Box>
        </Section>

        <Section title="Entrega">
          <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
            <Switch disabled={readOnly} checked={edit.delivery} onChange={(event) => setEdit({ ...edit, delivery: event.target.checked })} />
            {edit.delivery ? (
              <>
                <TextField size="small" label="Bairro ou cidade" disabled={readOnly} value={edit.neighborhood} onChange={(event) => setEdit({ ...edit, neighborhood: event.target.value })} />
                {!dirty && <Typography variant="body2" color="text.secondary">{quote.delivery_zone ?? 'Sem zona de entrega cadastrada'}</Typography>}
              </>
            ) : (
              <Typography variant="body2" color="text.secondary">Cliente retira na loja</Typography>
            )}
          </Box>
        </Section>

        <Box sx={{ p: { xs: 2, sm: 2.5 }, display: 'flex', alignItems: 'flex-end', flexWrap: 'wrap', gap: 3 }}>
          <Box sx={{ flex: 1, minWidth: 220, opacity: dirty ? 0.4 : 1, transition: 'opacity 150ms ease' }}>
            <Total label="Subtotal" value={quote.subtotal} />
            {quote.discount > 0 && <Total label={`Desconto por ${quote.days} diárias`} value={-quote.discount} />}
            {quote.delivery_fee > 0 && <Total label="Entrega" value={quote.delivery_fee} />}
            <Box sx={{ display: 'flex', justifyContent: 'space-between', maxWidth: 300, mt: 0.75, pt: 0.75, borderTop: 1, borderColor: 'divider' }}>
              <Typography sx={{ fontWeight: 650 }}>Total</Typography>
              <Typography sx={{ fontWeight: 650, fontSize: 18 }}>{money(quote.total)}</Typography>
            </Box>
          </Box>

          {!readOnly &&
            (dirty || isNew ? (
              <Box sx={{ display: 'flex', gap: 1 }}>
                {!isNew && <Button color="inherit" onClick={() => setEdit(initial)}>Desfazer</Button>}
                <Button variant="contained" disabled={!complete || !hasItems || busy !== null} onClick={save}>
                  {isNew ? 'Criar orçamento' : 'Salvar e recalcular'}
                </Button>
              </Box>
            ) : (
              <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 0.75 }}>
                <Button variant="contained" size="large" disabled={busy !== null || quote.items.length === 0 || !quote.start_date || !approvable} onClick={approve}>
                  Aprovar e reservar
                </Button>
                {!approvable && (
                  <Typography variant="caption" sx={{ color: 'text.secondary', maxWidth: 260, textAlign: 'right' }}>
                    Para aprovar, o orçamento precisa de um cliente cadastrado com documento conferido.
                  </Typography>
                )}
              </Box>
            ))}
        </Box>
      </Box>
      {customerForm && (
        <CustomerForm
          customer={customerForm === 'new' ? null : customerForm}
          initialName={edit.customerName.trim()}
          onClose={() => setCustomerForm(null)}
          onSaved={async (customer) => {
            await onCustomerSaved()
            setEdit((current) => ({ ...current, customerId: customer.id, customerName: customer.name }))
          }}
        />
      )}
    </Box>
  )
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Box sx={{ p: { xs: 2, sm: 2.5 }, borderBottom: 1, borderColor: 'divider' }}>
      <Typography variant="subtitle2" color="text.secondary" sx={{ mb: 1.5 }}>{title}</Typography>
      {children}
    </Box>
  )
}

function Total({ label, value }: { label: string; value: number }) {
  return (
    <Box sx={{ display: 'flex', justifyContent: 'space-between', maxWidth: 300 }}>
      <Typography variant="body2" color="text.secondary">{label}</Typography>
      <Typography variant="body2">{money(value)}</Typography>
    </Box>
  )
}

function IssueList({ title, issues }: { title: string; issues: Issue[] }) {
  if (issues.length === 0) return null

  return (
    <Box sx={{ mt: 1 }}>
      <Typography variant="caption" sx={{ fontWeight: 600, color: 'var(--pill-amber-fg)' }}>{title}</Typography>
      <Box component="ul" sx={{ m: 0, pl: 2.5 }}>
        {issues.map((issue, index) => (
          <Typography component="li" variant="body2" key={index}>{issue.message}</Typography>
        ))}
      </Box>
    </Box>
  )
}
