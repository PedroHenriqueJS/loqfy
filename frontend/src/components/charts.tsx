import { useEffect, useRef, useState } from 'react'
import { Box, Typography } from '@mui/material'

const compact = new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 })

function useWidth() {
  const ref = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)

  useEffect(() => {
    if (!ref.current) return
    const observer = new ResizeObserver(([entry]) => setWidth(Math.floor(entry.contentRect.width)))
    observer.observe(ref.current)
    return () => observer.disconnect()
  }, [])

  return [ref, width] as const
}

function scale(max: number) {
  const rough = Math.max(max, 1) / 4
  const magnitude = 10 ** Math.floor(Math.log10(rough))
  const step = [1, 2, 2.5, 5, 10].map((factor) => factor * magnitude).find((candidate) => candidate >= rough)!
  return { top: step * 4, ticks: [0, step, step * 2, step * 3, step * 4] }
}

export interface Series {
  name: string
  color: string
}

interface TooltipProps {
  x: number
  title: string
  rows: Array<{ color: string; name: string; value: string }>
  width: number
}

function Tooltip({ x, title, rows, width }: TooltipProps) {
  const left = x > width - 190
  return (
    <Box
      sx={{
        position: 'absolute', top: 8, left: x, transform: left ? 'translateX(calc(-100% - 12px))' : 'translateX(12px)', pointerEvents: 'none',
        bgcolor: 'var(--lq-paper)', border: '1px solid var(--lq-border)', borderRadius: '10px', boxShadow: '0 6px 24px rgb(0 0 0 / 0.12)', px: 1.5, py: 1, minWidth: 150, zIndex: 1,
      }}
    >
      <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 600 }}>{title}</Typography>
      {rows.map((row) => (
        <Box key={row.name} sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 0.25 }}>
          <Box sx={{ width: 8, height: 8, borderRadius: '2px', bgcolor: row.color, flexShrink: 0 }} />
          <Typography variant="body2" sx={{ flex: 1, color: 'text.secondary' }}>{row.name}</Typography>
          <Typography variant="body2" sx={{ fontWeight: 600 }}>{row.value}</Typography>
        </Box>
      ))}
    </Box>
  )
}

function Legend({ series }: { series: Series[] }) {
  return (
    <Box sx={{ display: 'flex', gap: 2, mb: 1 }}>
      {series.map((item) => (
        <Box key={item.name} sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
          <Box sx={{ width: 10, height: 10, borderRadius: '3px', bgcolor: item.color }} />
          <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 500 }}>{item.name}</Typography>
        </Box>
      ))}
    </Box>
  )
}

function DataTable({ caption, series, data, format }: { caption: string; series: Series[]; data: Array<{ label: string; values: number[] }>; format: (value: number) => string }) {
  return (
    <Box component="table" sx={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden', clip: 'rect(0 0 0 0)' }}>
      <caption>{caption}</caption>
      <thead><tr><th>Período</th>{series.map((item) => <th key={item.name}>{item.name}</th>)}</tr></thead>
      <tbody>{data.map((row) => <tr key={row.label}><th>{row.label}</th>{row.values.map((value, index) => <td key={index}>{format(value)}</td>)}</tr>)}</tbody>
    </Box>
  )
}

interface ChartProps {
  caption: string
  series: Series[]
  data: Array<{ label: string; values: number[] }>
  format: (value: number) => string
  height?: number
}

const MARGIN = { top: 12, right: 8, bottom: 26, left: 44 }

export function ColumnChart({ caption, series, data, format, height = 220 }: ChartProps) {
  const [ref, width] = useWidth()
  const [hover, setHover] = useState<number | null>(null)
  const { top, ticks } = scale(Math.max(...data.flatMap((row) => row.values), 0))
  const plotWidth = Math.max(width - MARGIN.left - MARGIN.right, 0)
  const plotHeight = height - MARGIN.top - MARGIN.bottom
  const band = data.length ? plotWidth / data.length : 0
  const bar = Math.max(Math.min(24, (band * 0.6 - 2 * (series.length - 1)) / series.length), 2)
  const group = bar * series.length + 2 * (series.length - 1)
  const y = (value: number) => MARGIN.top + plotHeight * (1 - value / top)

  return (
    <Box>
      {series.length > 1 && <Legend series={series} />}
      <Box ref={ref} sx={{ position: 'relative' }} onMouseLeave={() => setHover(null)}>
        <DataTable caption={caption} series={series} data={data} format={format} />
        {width > 0 && (
          <svg width={width} height={height} role="img" aria-label={caption} style={{ display: 'block' }}>
            {ticks.map((tick) => (
              <g key={tick}>
                <line x1={MARGIN.left} x2={width - MARGIN.right} y1={y(tick)} y2={y(tick)} stroke="var(--lq-grid)" strokeWidth={1} />
                <text x={MARGIN.left - 8} y={y(tick) + 4} textAnchor="end" fontSize={11} fill="var(--lq-faint)">{compact.format(tick)}</text>
              </g>
            ))}
            {data.map((row, index) => {
              const x0 = MARGIN.left + band * index
              const start = x0 + (band - group) / 2
              return (
                <g key={row.label}>
                  {hover === index && <rect x={x0 + 2} y={MARGIN.top} width={band - 4} height={plotHeight} rx={6} fill="var(--lq-subtle)" />}
                  {row.values.map((value, seriesIndex) => {
                    const x = start + seriesIndex * (bar + 2)
                    const h = Math.max(plotHeight * (value / top), value > 0 ? 2 : 0)
                    const r = Math.min(4, h, bar / 2)
                    const baseline = MARGIN.top + plotHeight
                    return <path key={seriesIndex} d={`M${x},${baseline} V${baseline - h + r} Q${x},${baseline - h} ${x + r},${baseline - h} H${x + bar - r} Q${x + bar},${baseline - h} ${x + bar},${baseline - h + r} V${baseline} Z`} fill={series[seriesIndex].color} />
                  })}
                  <text x={x0 + band / 2} y={height - 8} textAnchor="middle" fontSize={11} fill="var(--lq-muted)">{row.label}</text>
                  <rect x={x0} y={0} width={band} height={height} fill="transparent" onMouseEnter={() => setHover(index)} />
                </g>
              )
            })}
          </svg>
        )}
        {hover !== null && (
          <Tooltip
            x={MARGIN.left + band * hover + band / 2}
            width={width}
            title={data[hover].label}
            rows={series.map((item, index) => ({ color: item.color, name: item.name, value: format(data[hover].values[index]) }))}
          />
        )}
      </Box>
    </Box>
  )
}

export function LineChart({ caption, series, data, format, height = 220 }: ChartProps) {
  const [ref, width] = useWidth()
  const [hover, setHover] = useState<number | null>(null)
  const values = data.map((row) => row.values[0])
  const { top, ticks } = scale(Math.max(...values, 0))
  const margin = { ...MARGIN, right: 16 }
  const plotWidth = Math.max(width - margin.left - margin.right, 0)
  const plotHeight = height - margin.top - margin.bottom
  const x = (index: number) => margin.left + (data.length > 1 ? (plotWidth * index) / (data.length - 1) : plotWidth / 2)
  const y = (value: number) => margin.top + plotHeight * (1 - Math.max(value, 0) / top)
  const line = values.map((value, index) => `${index === 0 ? 'M' : 'L'}${x(index)},${y(value)}`).join(' ')
  const color = series[0].color
  const last = data.length - 1

  return (
    <Box
      ref={ref}
      sx={{ position: 'relative' }}
      onMouseLeave={() => setHover(null)}
      onMouseMove={(event) => {
        if (data.length === 0 || plotWidth === 0) return
        const offset = event.clientX - event.currentTarget.getBoundingClientRect().left - margin.left
        setHover(Math.min(last, Math.max(0, Math.round((offset / plotWidth) * last))))
      }}
    >
      <DataTable caption={caption} series={series} data={data} format={format} />
      {width > 0 && data.length > 0 && (
        <svg width={width} height={height} role="img" aria-label={caption} style={{ display: 'block' }}>
          {ticks.map((tick) => (
            <g key={tick}>
              <line x1={margin.left} x2={width - margin.right} y1={y(tick)} y2={y(tick)} stroke="var(--lq-grid)" strokeWidth={1} />
              <text x={margin.left - 8} y={y(tick) + 4} textAnchor="end" fontSize={11} fill="var(--lq-faint)">{compact.format(tick)}</text>
            </g>
          ))}
          <path d={`${line} L${x(last)},${margin.top + plotHeight} L${x(0)},${margin.top + plotHeight} Z`} fill={color} opacity={0.1} />
          <path d={line} fill="none" stroke={color} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
          {data.map((row, index) => (
            <text key={row.label} x={x(index)} y={height - 8} textAnchor={index === 0 ? 'start' : index === last ? 'end' : 'middle'} fontSize={11} fill="var(--lq-muted)">{row.label}</text>
          ))}
          {hover !== null && <line x1={x(hover)} x2={x(hover)} y1={margin.top} y2={margin.top + plotHeight} stroke="var(--lq-faint)" strokeWidth={1} />}
          {[...new Set([last, hover ?? last])].map((index) => (
            <circle key={index} cx={x(index)} cy={y(values[index])} r={4} fill={color} stroke="var(--lq-paper)" strokeWidth={2} />
          ))}
        </svg>
      )}
      {hover !== null && <Tooltip x={x(hover)} width={width} title={data[hover].label} rows={[{ color, name: series[0].name, value: format(values[hover]) }]} />}
    </Box>
  )
}

export function BarList({ rows, color = 'var(--lq-series-1)' }: { rows: Array<{ label: string; value: number; display: string; note?: string }>; color?: string }) {
  const max = Math.max(...rows.map((row) => row.value), 1)

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
      {rows.map((row) => (
        <Box key={row.label}>
          <Box sx={{ display: 'flex', alignItems: 'baseline', gap: 1, mb: 0.5 }}>
            <Typography variant="body2" sx={{ flex: 1, fontWeight: 500 }} noWrap>{row.label}</Typography>
            {row.note && <Typography variant="caption" sx={{ color: 'text.secondary' }}>{row.note}</Typography>}
            <Typography variant="body2" sx={{ fontWeight: 600 }}>{row.display}</Typography>
          </Box>
          <Box sx={{ height: 8, borderRadius: '6px', bgcolor: 'var(--lq-subtle)' }}>
            <Box sx={{ height: 8, borderRadius: '6px', bgcolor: color, width: `${Math.max((row.value / max) * 100, row.value > 0 ? 1.5 : 0)}%` }} />
          </Box>
        </Box>
      ))}
    </Box>
  )
}
