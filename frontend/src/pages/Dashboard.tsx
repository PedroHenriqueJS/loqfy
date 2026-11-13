import { useState } from 'react'
import { Alert, Box, Button, CircularProgress, Dialog, DialogActions, DialogContent, Typography, useMediaQuery } from '@mui/material'
import type { Theme } from '@mui/material/styles'
import Add from '@mui/icons-material/Add'
import AutoAwesome from '@mui/icons-material/AutoAwesome'
import PaymentsOutlined from '@mui/icons-material/PaymentsOutlined'
import SpeedOutlined from '@mui/icons-material/SpeedOutlined'
import LocalShippingOutlined from '@mui/icons-material/LocalShippingOutlined'
import ReportProblemOutlined from '@mui/icons-material/ReportProblemOutlined'
import { api } from '../api'
import { clock, day, money, month, percent } from '../format'
import { useData } from '../hooks'
import { go } from '../router'
import { BarList, ColumnChart } from '../components/charts'
import { Pill } from '../components/Pill'
import type { Tone } from '../components/Pill'
import { Card, Empty, Page, PageHeader, Stat } from '../components/ui'
import type { Analysis, Insight, RentalLine } from '../types'

const longDate = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })

export default function Dashboard() {
  const { data, setData } = useData(api.dashboard, 30000)
  const [analyzing, setAnalyzing] = useState(false)
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function analyze() {
    setAnalyzing(true)
    setError(null)
    try {
      const { analysis } = await api.analyzeDashboard()
      setData((current) => (current ? { ...current, analysis } : current))
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível gerar a análise')
    } finally {
      setAnalyzing(false)
    }
  }

  if (!data) return <Page><PageHeader title="Início" /></Page>

  const { metrics, analysis } = data
  const { kpis } = metrics
  const growth = kpis.revenue_last_month_same_days > 0 ? (100 * (kpis.revenue_this_month_so_far - kpis.revenue_last_month_same_days)) / kpis.revenue_last_month_same_days : null
  const quotes = metrics.quotes_last_30_days

  return (
    <Page>
      <PageHeader
        title="Início"
        subtitle={longDate.format(new Date())}
        actions={
          <>
            <Button variant="outlined" startIcon={<AutoAwesome sx={{ color: 'var(--lq-violet)' }} />} onClick={() => go('ia/mensagens')}>Orçamento por mensagem</Button>
            <Button variant="contained" startIcon={<Add />} onClick={() => go('orcamentos/novo')}>Novo orçamento</Button>
          </>
        }
      />

      <AnalysisStrip
        analysis={analysis}
        onOpen={() => {
          setOpen(true)
          if (!analysis && !analyzing) analyze()
        }}
      />
      <AnalysisDialog open={open} analysis={analysis} analyzing={analyzing} error={error} onAnalyze={analyze} onClose={() => setOpen(false)} />

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr 1fr', lg: 'repeat(4, 1fr)' }, gap: 2, my: 2 }}>
        <Stat
          icon={<PaymentsOutlined />}
          label="Receita no mês"
          value={money(kpis.revenue_this_month_so_far)}
          tone={growth === null ? 'default' : growth >= 0 ? 'good' : 'bad'}
          note={growth === null ? 'Sem base de comparação' : `${growth >= 0 ? '+' : ''}${percent(growth)} sobre o mesmo período do mês passado`}
        />
        <Stat icon={<SpeedOutlined />} label="Ocupação do estoque" value={percent(kpis.utilization_percent)} note={`${kpis.units_out_today} de ${kpis.units_total} unidades alugadas hoje`} />
        <Stat icon={<LocalShippingOutlined />} label="Locações com clientes" value={kpis.rentals_out} note={`${kpis.rentals_reserved} reservadas para sair`} />
        <Stat
          icon={<ReportProblemOutlined />}
          label="A receber em atraso"
          value={money(kpis.overdue_receivable)}
          tone={kpis.overdue_receivable > 0 ? 'bad' : 'good'}
          note={kpis.overdue_receivable > 0 ? 'Veja no Financeiro' : 'Nada em atraso'}
        />
      </Box>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: 'minmax(0, 1.7fr) minmax(0, 1fr)' }, gap: 2, mb: 2 }}>
        <Card title="Receita por mês" hint="Pagamentos de locação recebidos nos últimos seis meses">
          <ColumnChart
            caption="Receita de locação recebida por mês"
            series={[{ name: 'Receita', color: 'var(--lq-series-1)' }]}
            data={metrics.revenue_by_month.map((row) => ({ label: month(row.month), values: [row.revenue] }))}
            format={money}
          />
        </Card>

        <Card title="Devoluções" hint="Atrasadas e previstas para os próximos três dias" flush action={<Button size="small" href="#/locacoes">Ver locações</Button>}>
          {metrics.overdue_returns.length + metrics.upcoming_returns.length === 0 && <Empty>Nenhuma devolução prevista.</Empty>}
          {metrics.overdue_returns.map((line) => <ReturnRow key={line.rental_id} line={line} overdue />)}
          {metrics.upcoming_returns.map((line) => <ReturnRow key={line.rental_id} line={line} />)}
        </Card>
      </Box>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: 'repeat(3, minmax(0, 1fr))' }, gap: 2 }}>
        <Card title="O que mais rende" hint="Receita por equipamento em 90 dias">
          <BarList rows={metrics.top_equipment_last_90_days.map((row) => ({ label: row.name, value: row.revenue, display: money(row.revenue), note: `${row.rentals} locações` }))} />
        </Card>

        <Card title="Parado há 90 dias" hint="Equipamentos sem nenhuma locação no período">
          {metrics.idle_equipment_last_90_days.length === 0 && <Empty>Todo o catálogo teve saída.</Empty>}
          {metrics.idle_equipment_last_90_days.map((item) => (
            <Box key={item.name} sx={{ display: 'flex', alignItems: 'baseline', gap: 1, py: 1, '& + &': { borderTop: '1px solid var(--lq-border)' } }}>
              <Typography variant="body2" sx={{ flex: 1, fontWeight: 500 }} noWrap>{item.name}</Typography>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>{item.units} un. · {money(item.daily_rate)}/dia</Typography>
            </Box>
          ))}
        </Card>

        <Card title="Orçamentos em 30 dias" action={<Button size="small" href="#/orcamentos">Ver todos</Button>}>
          <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', rowGap: 2 }}>
            <Figure label="Criados" value={quotes.total} />
            <Figure label="Aprovados" value={quotes.approved} />
            <Figure label="Em aberto" value={quotes.open} note={kpis.quotes_needing_review > 0 ? `${kpis.quotes_needing_review} para revisar` : undefined} />
            <Figure label="Montados pela IA" value={quotes.drafted_by_ai} note={`${quotes.typed_by_hand} feitos à mão`} />
          </Box>
        </Card>
      </Box>
    </Page>
  )
}

function Figure({ label, value, note }: { label: string; value: number; note?: string }) {
  return (
    <Box>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>{label}</Typography>
      <Typography sx={{ fontFamily: '"Outfit Variable", sans-serif', fontSize: 24, fontWeight: 600, lineHeight: 1.2 }}>{value}</Typography>
      {note && <Typography variant="caption" sx={{ color: 'text.secondary' }}>{note}</Typography>}
    </Box>
  )
}

function ReturnRow({ line, overdue }: { line: RentalLine; overdue?: boolean }) {
  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, px: 2.5, py: 1.5, borderTop: '1px solid var(--lq-border)' }}>
      <Box sx={{ flex: 1, minWidth: 0 }}>
        <Typography variant="body2" sx={{ fontWeight: 600 }} noWrap>{line.customer ?? `Locação nº ${line.rental_id}`}</Typography>
        <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }} noWrap>{line.items}</Typography>
      </Box>
      {overdue ? <Pill tone="red">Atrasada desde {day(line.end_date)}</Pill> : <Pill tone="neutral">{day(line.end_date)}</Pill>}
    </Box>
  )
}

const KIND: Record<Insight['kind'], { label: string; tone: Tone }> = {
  risk: { label: 'Risco', tone: 'red' },
  opportunity: { label: 'Oportunidade', tone: 'green' },
  info: { label: 'Para saber', tone: 'blue' },
}

const violetButton = { bgcolor: 'var(--lq-violet)', color: 'var(--lq-on-violet)', '&:hover': { bgcolor: 'var(--lq-violet-hover)' } }

function AnalysisStrip({ analysis, onOpen }: { analysis: Analysis | null; onOpen: () => void }) {
  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap', bgcolor: 'var(--lq-ai-bg)', border: '1px solid var(--lq-ai-border)', borderRadius: '14px', px: 2.5, py: 1.75 }}>
      <Box sx={{ display: 'grid', placeItems: 'center', width: 36, height: 36, borderRadius: '10px', bgcolor: 'var(--pill-violet-bg)', color: 'var(--lq-violet)' }}>
        <AutoAwesome sx={{ fontSize: 19 }} />
      </Box>
      <Box sx={{ flex: 1, minWidth: 240 }}>
        <Typography sx={{ fontWeight: 600 }}>Análise da IA</Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }} noWrap>
          {analysis ? `Última leitura às ${clock(analysis.generated_at)}: ${analysis.headline}` : 'A IA lê os indicadores de hoje e aponta riscos, oportunidades e o que fazer na semana.'}
        </Typography>
      </Box>
      <Button variant="contained" startIcon={<AutoAwesome />} onClick={onOpen} sx={violetButton}>
        {analysis ? 'Ver análise' : 'Analisar com IA'}
      </Button>
    </Box>
  )
}

interface DialogProps {
  open: boolean
  analysis: Analysis | null
  analyzing: boolean
  error: string | null
  onAnalyze: () => void
  onClose: () => void
}

function AnalysisDialog({ open, analysis, analyzing, error, onAnalyze, onClose }: DialogProps) {
  const phone = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'))
  return (
    <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth fullScreen={phone} transitionDuration={{ enter: 220, exit: 150 }}>
      <DialogContent sx={{ p: { xs: 2.5, sm: 3.5 }, pb: 2 }}>
        <Typography variant="subtitle2" sx={{ display: 'flex', alignItems: 'center', gap: 0.75, color: 'var(--lq-violet)' }}>
          <AutoAwesome sx={{ fontSize: 14 }} /> Análise da IA
        </Typography>

        {analyzing && (
          <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 2, minHeight: 320, color: 'text.secondary' }}>
            <CircularProgress size={28} thickness={5} sx={{ color: 'var(--lq-violet)', animationDuration: '600ms' }} />
            <Typography>Lendo os números de hoje</Typography>
          </Box>
        )}

        {error && !analyzing && <Alert severity="error" sx={{ mt: 2 }}>{error}</Alert>}

        {analysis && !analyzing && (
          <Box className="enter" key={analysis.generated_at}>
            <Typography variant="h1" sx={{ mt: 1, fontSize: 22, lineHeight: 1.3 }}>{analysis.headline}</Typography>

            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, minmax(0, 1fr))' }, gap: 1.5, mt: 2.5 }}>
              {analysis.insights.map((insight, index) => (
                <Box key={index} sx={{ bgcolor: 'var(--lq-subtle)', border: '1px solid var(--lq-border)', borderRadius: '12px', p: 2 }}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.75, flexWrap: 'wrap' }}>
                    <Pill tone={KIND[insight.kind].tone}>{KIND[insight.kind].label}</Pill>
                    {insight.unverified_numbers.length > 0 && <Pill tone="amber">Número não conferido: {insight.unverified_numbers.join(', ')}</Pill>}
                  </Box>
                  <Typography sx={{ fontWeight: 600 }}>{insight.title}</Typography>
                  <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.5 }}>{insight.evidence}</Typography>
                  <Typography variant="body2" sx={{ mt: 1 }}><Box component="span" sx={{ fontWeight: 600 }}>O que fazer: </Box>{insight.action}</Typography>
                </Box>
              ))}
            </Box>

            <Typography variant="caption" sx={{ display: 'block', color: 'text.secondary', mt: 2 }}>
              Gerada às {clock(analysis.generated_at)} por {analysis.model}. A IA recebeu só os indicadores desta tela, e cada número citado foi conferido contra eles. Ainda assim é uma leitura automática: confira antes de decidir.
            </Typography>
          </Box>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3.5, pb: 2.5 }}>
        <Button color="inherit" onClick={onClose}>Fechar</Button>
        <Button variant="contained" disabled={analyzing} startIcon={<AutoAwesome />} onClick={onAnalyze} sx={violetButton}>
          {analysis ? 'Analisar de novo' : 'Analisar'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
