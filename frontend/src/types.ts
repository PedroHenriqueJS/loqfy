export type RequestStatus = 'pending' | 'processing' | 'ready' | 'needs_review' | 'approved' | 'discarded' | 'failed'

export interface Issue {
  type: 'unavailable' | 'not_in_catalog' | 'ambiguous' | 'missing_info' | 'substitution' | 'delivery_zone_unknown'
  message: string
  source: 'ai' | 'system'
}

export interface QuoteItem {
  id: number
  equipment_id: number
  name: string
  requested_term: string | null
  substitutes: string | null
  quantity: number
  daily_rate: number
  line_total: number
  available: boolean
}

export type Origin = 'ai' | 'manual'

export interface Quote {
  id: number
  status: 'draft' | 'approved' | 'discarded'
  origin: Origin
  needs_review: boolean
  rental_request_id: number | null
  customer_id: number | null
  customer_name: string | null
  customer_verified: boolean
  rental_id: number | null
  created_at: string | null
  start_date: string | null
  end_date: string | null
  days: number
  neighborhood: string | null
  delivery_zone: string | null
  delivery_fee: number
  subtotal: number
  discount: number
  total: number
  confidence: 'low' | 'medium' | 'high' | null
  issues: Issue[]
  items: QuoteItem[]
}

export interface ToolCall {
  name: string
  arguments: Record<string, unknown>
  result: unknown
}

export interface RentalRequest {
  id: number
  customer_name: string | null
  channel: string
  raw_text: string
  status: RequestStatus
  error: string | null
  created_at: string
  processed_at: string | null
  quote?: Quote | null
  ai_run?: { model: string; tool_calls: ToolCall[] | null; tokens: number; latency_ms: number; success: boolean } | null
}

export interface Equipment {
  id: number
  name: string
  category: string
  description: string | null
  aliases: string[]
  daily_rate: number
  quantity_total: number
}

export interface DeliveryZone {
  id: number
  name: string
  neighborhoods: string[]
  fee: number
}

export interface Customer {
  id: number
  name: string
  kind: 'person' | 'company'
  cnpj: string | null
  phone: string | null
  email: string | null
  address: string | null
  notes: string | null
  document_verified_at: string | null
  quotes_count?: number
  rentals_count?: number
}

export interface Catalog {
  equipment: Equipment[]
  delivery_zones: DeliveryZone[]
}

export interface Damage {
  location: string
  description: string
  severity: 'minor' | 'moderate' | 'severe'
  confidence: 'low' | 'medium' | 'high'
}

export interface Inspection {
  id: number
  rental_item_id: number
  origin: Origin
  status: 'awaiting_return' | 'pending' | 'analyzed' | 'failed'
  checkout_photo_url: string | null
  return_photo_url: string | null
  ai_result: { same_equipment: boolean; damages: Damage[]; photo_quality: 'ok' | 'poor'; summary: string } | null
  checks: Check[]
  error: string | null
  decision: 'no_damage' | 'damage_confirmed' | null
  decision_note: string | null
  decided_at: string | null
}

export interface RentalItem {
  id: number
  equipment_id: number
  name: string
  quantity: number
  inspection: Inspection | null
}

export interface Rental {
  id: number
  customer_id: number | null
  customer_name: string | null
  quote_id: number | null
  origin: Origin
  start_date: string
  end_date: string
  status: 'reserved' | 'out' | 'returned'
  total: number
  items: RentalItem[]
}

export interface EvalReport {
  suite: string
  model: string
  reference_date?: string
  ran_at: string
  summary: Record<string, string | number>
  cases: Array<{
    id: string
    category: string
    text?: string
    passed: boolean
    failures: string[]
    latency_ms: number
    tokens: number
  }>
}

export interface AiSummary {
  model: string
  usage: Array<{ feature: string; runs: number; successes: number; avg_latency_ms: number; avg_tokens: number }>
  evals: { quote: EvalReport | null; inspection: EvalReport | null; receipt: EvalReport | null }
}

export interface RentalLine {
  rental_id: number
  customer: string | null
  end_date: string
  items: string
}

export interface DashboardMetrics {
  date: string
  kpis: {
    revenue_this_month_so_far: number
    revenue_last_month_same_days: number
    revenue_last_month_full: number
    rentals_out: number
    rentals_reserved: number
    units_out_today: number
    units_total: number
    utilization_percent: number
    open_quotes: number
    quotes_needing_review: number
    overdue_returns: number
    overdue_receivable: number
    cash_balance: number
  }
  revenue_by_month: Array<{ month: string; revenue: number }>
  top_equipment_last_90_days: Array<{ name: string; revenue: number; rentals: number }>
  idle_equipment_last_90_days: Array<{ name: string; units: number; daily_rate: number }>
  utilization_by_category: Array<{ category: string; units_out: number; units_total: number; percent: number }>
  upcoming_returns: RentalLine[]
  overdue_returns: RentalLine[]
  quotes_last_30_days: { total: number; approved: number; discarded: number; open: number; drafted_by_ai: number; typed_by_hand: number }
}

export interface Insight {
  kind: 'risk' | 'opportunity' | 'info'
  title: string
  evidence: string
  action: string
  unverified_numbers: string[]
}

export interface Analysis {
  headline: string
  insights: Insight[]
  generated_at: string
  model: string
}

export interface FinancialEntry {
  id: number
  type: 'income' | 'expense'
  category: string
  description: string
  amount: number
  due_date: string
  paid_at: string | null
  status: 'paid' | 'pending' | 'overdue'
  rental_id: number | null
  origin: Origin
  receipt_photo: string | null
  receipt_photo_url: string | null
}

export interface ReceiptRead {
  receipt_photo: string
  receipt_photo_url: string
  suggestion: {
    is_receipt: boolean
    supplier: string | null
    description: string | null
    category: string
    date: string | null
    total_as_printed: string | null
    amount: number | null
    legible: boolean
  } | null
  checks: Check[]
}

export interface Check {
  code: string
  message: string
  moment?: 'saída' | 'devolução'
}

export interface FinanceSummary {
  balance: number
  income_this_month: number
  expense_this_month: number
  receivable_next_30_days: number
  payable_next_30_days: number
  projected_balance_in_30_days: number
  overdue_receivable: number
  monthly: Array<{ month: string; income: number; expense: number; balance: number }>
  expenses_by_category: Array<{ category: string; amount: number }>
  categories: { income: string[]; expense: string[] }
}

export interface CnpjLookup {
  cnpj: string
  legal_name: string | null
  trade_name: string | null
  status: string | null
  address: string | null
  phone: string | null
  email: string | null
}
