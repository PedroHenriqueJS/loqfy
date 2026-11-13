import { useState } from 'react'
import { Alert, Box, Button, Checkbox, CircularProgress, FormControlLabel, TextField, Typography } from '@mui/material'
import Search from '@mui/icons-material/Search'
import { api } from '../api'
import type { CustomerEdit } from '../api'
import FormDialog from './FormDialog'
import { Segmented } from './ui'
import type { Customer } from '../types'

const digits = (value: string) => value.replace(/\D/g, '')

function formatCnpj(value: string) {
  const d = digits(value).slice(0, 14)
  return [d.slice(0, 2), d.slice(2, 5), d.slice(5, 8)].filter(Boolean).join('.') + (d.length > 8 ? `/${d.slice(8, 12)}` : '') + (d.length > 12 ? `-${d.slice(12)}` : '')
}

interface Props {
  customer: Customer | null
  initialName?: string
  onClose: () => void
  onSaved: (customer: Customer) => Promise<void>
  onDeleted?: () => Promise<void>
}

export default function CustomerForm({ customer, initialName, onClose, onSaved, onDeleted }: Props) {
  const [edit, setEdit] = useState({
    name: customer?.name ?? initialName ?? '',
    kind: customer?.kind ?? ('person' as Customer['kind']),
    cnpj: formatCnpj(customer?.cnpj ?? ''),
    phone: customer?.phone ?? '',
    email: customer?.email ?? '',
    address: customer?.address ?? '',
    notes: customer?.notes ?? '',
    verified: customer ? customer.document_verified_at !== null : false,
  })
  const [lookup, setLookup] = useState<{ busy: boolean; status: string | null; error: string | null }>({ busy: false, status: null, error: null })
  const field = (name: 'name' | 'phone' | 'email' | 'address' | 'notes') => ({ value: edit[name], onChange: (event: React.ChangeEvent<HTMLInputElement>) => setEdit({ ...edit, [name]: event.target.value }) })
  const company = edit.kind === 'company'

  async function findCnpj() {
    setLookup({ busy: true, status: null, error: null })
    try {
      const found = await api.cnpj(digits(edit.cnpj))
      setEdit((current) => ({
        ...current,
        name: current.name.trim() || found.trade_name || found.legal_name || '',
        address: current.address.trim() || found.address || '',
        phone: current.phone.trim() || found.phone || '',
        email: current.email.trim() || found.email || '',
      }))
      setLookup({ busy: false, status: [found.legal_name, found.status && `situação ${found.status.toLowerCase()}`].filter(Boolean).join(' · '), error: null })
    } catch (e) {
      setLookup({ busy: false, status: null, error: e instanceof Error ? e.message : 'Não foi possível consultar o CNPJ' })
    }
  }

  const payload: CustomerEdit = {
    name: edit.name.trim(),
    kind: edit.kind,
    cnpj: company ? digits(edit.cnpj) || null : null,
    phone: edit.phone.trim() || null,
    email: edit.email.trim() || null,
    address: edit.address.trim() || null,
    notes: edit.notes.trim() || null,
    document_verified: edit.verified,
  }

  return (
    <FormDialog
      title={customer ? 'Editar cliente' : 'Novo cliente'}
      canSave={payload.name !== '' && (!company || payload.cnpj === null || payload.cnpj.length === 14)}
      onClose={onClose}
      onSave={async () => {
        const saved = await (customer ? api.updateCustomer(customer.id, payload) : api.createCustomer(payload))
        await onSaved(saved)
      }}
      onDelete={customer && onDeleted ? async () => { await api.deleteCustomer(customer.id); await onDeleted() } : undefined}
    >
      <Segmented
        value={edit.kind}
        onChange={(kind) => setEdit({ ...edit, kind })}
        options={[{ value: 'person', label: 'Pessoa' }, { value: 'company', label: 'Empresa' }]}
      />

      {company && (
        <Box>
          <Box sx={{ display: 'flex', gap: 1 }}>
            <TextField
              size="small"
              label="CNPJ"
              fullWidth
              value={edit.cnpj}
              onChange={(event) => setEdit({ ...edit, cnpj: formatCnpj(event.target.value) })}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && digits(edit.cnpj).length === 14) {
                  event.preventDefault()
                  findCnpj()
                }
              }}
              placeholder="00.000.000/0000-00"
              autoComplete="off"
              slotProps={{ inputLabel: { shrink: true }, input: { notched: true }, htmlInput: { inputMode: 'numeric' } }}
            />
            <Button
              variant="outlined"
              disabled={digits(edit.cnpj).length !== 14 || lookup.busy}
              onClick={findCnpj}
              startIcon={lookup.busy ? <CircularProgress size={14} thickness={6} color="inherit" sx={{ animationDuration: '600ms' }} /> : <Search />}
              sx={{ flexShrink: 0 }}
            >
              Buscar
            </Button>
          </Box>
          {lookup.status && <Typography variant="caption" sx={{ display: 'block', color: 'text.secondary', mt: 0.75 }}>Receita Federal: {lookup.status}</Typography>}
          {lookup.status && !/ativa/i.test(lookup.status) && <Alert severity="warning" sx={{ mt: 1 }}>A empresa não está ativa na Receita Federal.</Alert>}
          {lookup.error && <Alert severity="error" sx={{ mt: 1 }}>{lookup.error}</Alert>}
        </Box>
      )}

      <TextField size="small" label={company ? 'Nome da empresa' : 'Nome'} autoFocus={!company} required {...field('name')} />
      <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2 }}>
        <TextField size="small" label="Telefone" {...field('phone')} />
        <TextField size="small" label="E-mail" type="email" {...field('email')} />
      </Box>
      <TextField size="small" label="Endereço" {...field('address')} />
      <TextField size="small" label="Observações" multiline minRows={2} {...field('notes')} />

      <Box sx={{ border: '1px solid var(--lq-border)', borderRadius: '10px', px: 1.5, py: 1 }}>
        <FormControlLabel
          control={<Checkbox checked={edit.verified} onChange={(event) => setEdit({ ...edit, verified: event.target.checked })} />}
          label={<Typography sx={{ fontWeight: 600 }}>Documento conferido</Typography>}
        />
        <Typography variant="caption" sx={{ display: 'block', color: 'text.secondary', pl: 4, pb: 0.5 }}>
          {company
            ? 'Você conferiu o CNPJ e um documento com foto de quem representa a empresa.'
            : 'Você viu um documento com foto do cliente. O sistema registra só a conferência, sem guardar número nem imagem.'}{' '}
          Locações só são aprovadas para clientes conferidos.
        </Typography>
      </Box>
    </FormDialog>
  )
}
