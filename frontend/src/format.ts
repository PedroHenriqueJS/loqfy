const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const shortDate = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })
const time = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' })

export const money = (value: number) => currency.format(value)

export function day(isoDate: string) {
  const [year, month, dayOfMonth] = isoDate.split('-').map(Number)
  return shortDate.format(new Date(year, month - 1, dayOfMonth))
}

export const clock = (isoDateTime: string) => time.format(new Date(isoDateTime))

export function period(start: string | null, end: string | null) {
  if (!start || !end) return 'Período não definido'
  return start === end ? day(start) : `${day(start)} a ${day(end)}`
}

const monthName = new Intl.DateTimeFormat('pt-BR', { month: 'short' })

export function month(yearMonth: string) {
  const [year, monthNumber] = yearMonth.split('-').map(Number)
  return monthName.format(new Date(year, monthNumber - 1, 1)).replace('.', '')
}

export const percent = (value: number) => `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`

export const today = () => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
