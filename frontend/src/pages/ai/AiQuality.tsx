import { Box, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material'
import { api } from '../../api'
import { useData } from '../../hooks'
import { Pill } from '../../components/Pill'
import { Page, PageHeader } from '../../components/ui'
import type { EvalReport } from '../../types'

const METRIC_LABEL: Record<string, string> = {
  cases_fully_correct: 'Casos 100% corretos',
  valid_output: 'Resposta legível',
  items_correct: 'Equipamentos corretos',
  dates_correct: 'Datas corretas',
  delivery_zone_correct: 'Zona de entrega correta',
  review_flag_correct: 'Sinal de revisão correto',
  total_correct_when_checked: 'Total correto',
  invented_prices: 'Preços inventados',
  average_latency_ms: 'Tempo médio',
  average_tokens: 'Tokens por caso',
  damage_detected: 'Avarias detectadas',
  false_alarms: 'Falsos alarmes',
  total_correct: 'Valor total correto',
  date_correct: 'Data correta',
  category_correct: 'Categoria correta',
}

const FEATURE_LABEL: Record<string, string> = { quote: 'Orçamento por mensagem', inspection: 'Vistoria por foto', insights: 'Análise do início', receipt: 'Comprovante por foto' }

export default function AiQuality() {
  const { data } = useData(api.aiSummary)

  return (
    <Page width={1040}>
      <PageHeader
        title="Qualidade da IA"
        subtitle={`Cada recurso de IA é medido contra casos com resposta conhecida. Os números abaixo vêm da última execução, incluindo os erros.${data ? ` Modelo: ${data.model}.` : ''}`}
      />

        {data && (
          <>
            <Report title="Orçamento a partir do pedido" command="php artisan loqfy:eval quote" report={data.evals.quote} />
            <Report title="Vistoria por foto" command="php artisan loqfy:eval inspection" report={data.evals.inspection} />
            <Report title="Comprovante por foto" command="php artisan loqfy:eval receipt" report={data.evals.receipt} />

            {data.usage.length > 0 && (
              <Box sx={{ bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '14px', boxShadow: 'var(--lq-shadow)', p: 2.5 }}>
                <Typography variant="h2" sx={{ mb: 1.5 }}>Uso neste ambiente</Typography>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Recurso</TableCell>
                      <TableCell align="right">Execuções</TableCell>
                      <TableCell align="right">Com resposta válida</TableCell>
                      <TableCell align="right">Tempo médio</TableCell>
                      <TableCell align="right">Tokens médios</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {data.usage.map((row) => (
                      <TableRow key={row.feature}>
                        <TableCell>{FEATURE_LABEL[row.feature] ?? row.feature}</TableCell>
                        <TableCell align="right">{row.runs}</TableCell>
                        <TableCell align="right">{row.successes}</TableCell>
                        <TableCell align="right">{(row.avg_latency_ms / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} s</TableCell>
                        <TableCell align="right">{row.avg_tokens.toLocaleString('pt-BR')}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </Box>
            )}
          </>
        )}
    </Page>
  )
}

function Report({ title, command, report }: { title: string; command: string; report: EvalReport | null }) {
  return (
    <Box sx={{ bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '14px', boxShadow: 'var(--lq-shadow)', p: 2.5, mb: 3 }}>
      <Box sx={{ display: 'flex', alignItems: 'baseline', gap: 2, mb: 2, flexWrap: 'wrap' }}>
        <Typography variant="h2" sx={{ flex: 1 }}>{title}</Typography>
        {report && (
          <Typography variant="caption" color="text.secondary">
            {report.cases.length} casos · {new Date(report.ran_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}
          </Typography>
        )}
      </Box>

      {!report ? (
        <Typography variant="body2" color="text.secondary">
          Ainda sem resultado. Rode <Box component="code" sx={{ fontFamily: 'ui-monospace, Menlo, monospace', fontSize: 13 }}>{command}</Box>.
        </Typography>
      ) : (
        <>
          <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(170px, 1fr))', gap: 1.5, mb: 2.5 }}>
            {Object.entries(report.summary).map(([name, value]) => (
              <Box key={name} sx={{ border: 1, borderColor: 'divider', borderRadius: '8px', px: 1.5, py: 1.25 }}>
                <Typography variant="caption" color="text.secondary">{METRIC_LABEL[name] ?? name}</Typography>
                <Typography sx={{ fontWeight: 650, fontSize: 17 }}>{formatMetric(name, value)}</Typography>
              </Box>
            ))}
          </Box>

          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Caso</TableCell>
                <TableCell>Entrada</TableCell>
                <TableCell>Resultado</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {report.cases.map((testCase) => (
                <TableRow key={testCase.id}>
                  <TableCell sx={{ whiteSpace: 'nowrap', verticalAlign: 'top' }}>{testCase.id}</TableCell>
                  <TableCell sx={{ verticalAlign: 'top' }}>{testCase.text}</TableCell>
                  <TableCell sx={{ verticalAlign: 'top', minWidth: 220 }}>
                    {testCase.passed ? <Pill tone="green">Correto</Pill> : <Pill tone="red">Errou</Pill>}
                    {testCase.failures.map((failure) => (
                      <Typography key={failure} variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.5, wordBreak: 'break-word' }}>{failure}</Typography>
                    ))}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </>
      )}
    </Box>
  )
}

function formatMetric(name: string, value: string | number) {
  if (name === 'average_latency_ms') return `${(Number(value) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} s`
  if (typeof value === 'number') return value.toLocaleString('pt-BR')
  return value
}
