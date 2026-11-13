import { useState } from 'react'
import { Box, Typography } from '@mui/material'
import { api } from '../../api'
import { useData } from '../../hooks'
import { go } from '../../router'
import { Card, Page, PageHeader } from '../../components/ui'
import { EntryForm, ReceiptButton } from '../Finance'
import type { ReceiptRead } from '../../types'

export default function ReceiptReader() {
  const { data: summary } = useData(api.financeSummary)
  const [receipt, setReceipt] = useState<ReceiptRead | null>(null)

  return (
    <Page width={760}>
      <PageHeader
        title="Comprovante por foto"
        subtitle="Fotografe o cupom do posto, o recibo da oficina ou a nota de uma peça. A IA lê fornecedor, data, valor e categoria e deixa a despesa pronta para você conferir."
      />

      <Card>
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2, py: 4, textAlign: 'center' }}>
          <ReceiptButton large onRead={setReceipt} />
          <Typography variant="body2" sx={{ color: 'text.secondary', maxWidth: 440 }}>
            Nada é salvo sozinho. O formulário abre com a foto ao lado, e a despesa só entra no Financeiro quando você confirma.
          </Typography>
        </Box>
      </Card>

      {receipt && summary && (
        <EntryForm entry={null} receipt={receipt} categories={summary.categories} onClose={() => setReceipt(null)} onSaved={async () => go('financeiro')} />
      )}
    </Page>
  )
}
