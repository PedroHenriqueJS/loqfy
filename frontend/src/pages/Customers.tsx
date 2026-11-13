import { useState } from 'react'
import { Button, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material'
import Add from '@mui/icons-material/Add'
import { api } from '../api'
import { useData } from '../hooks'
import CustomerForm from '../components/CustomerForm'
import { Pill } from '../components/Pill'
import { Card, Empty, Page, PageHeader } from '../components/ui'
import type { Customer } from '../types'

const cnpj = (d: string) => `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 8)}/${d.slice(8, 12)}-${d.slice(12)}`

export default function Customers() {
  const { data: customers, reload } = useData(api.customers)
  const [editing, setEditing] = useState<Customer | 'new' | null>(null)

  return (
    <Page width={1040}>
      <PageHeader title="Clientes" subtitle="Quem aluga com a locadora. Uma locação só é aprovada para cliente com documento conferido." actions={<Button variant="contained" startIcon={<Add />} onClick={() => setEditing('new')}>Novo cliente</Button>} />

      <Card flush>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Nome</TableCell>
              <TableCell>Tipo</TableCell>
              <TableCell>Telefone</TableCell>
              <TableCell>Documento</TableCell>
              <TableCell align="right">Orçamentos</TableCell>
              <TableCell align="right">Locações</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {customers?.map((customer) => (
              <TableRow key={customer.id} hover onClick={() => setEditing(customer)} sx={{ cursor: 'pointer', '&:last-child td': { borderBottom: 0 } }}>
                <TableCell>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>{customer.name}</Typography>
                  {customer.cnpj && <Typography variant="caption" sx={{ color: 'text.secondary' }}>{cnpj(customer.cnpj)}</Typography>}
                </TableCell>
                <TableCell>{customer.kind === 'company' ? 'Empresa' : 'Pessoa'}</TableCell>
                <TableCell>{customer.phone}</TableCell>
                <TableCell>{customer.document_verified_at ? <Pill tone="green">Conferido</Pill> : <Pill tone="amber">Não conferido</Pill>}</TableCell>
                <TableCell align="right">{customer.quotes_count}</TableCell>
                <TableCell align="right">{customer.rentals_count}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {customers?.length === 0 && <Empty>Nenhum cliente cadastrado.</Empty>}
      </Card>

      {editing && (
        <CustomerForm
          customer={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={async () => reload()}
          onDeleted={reload}
        />
      )}
    </Page>
  )
}
