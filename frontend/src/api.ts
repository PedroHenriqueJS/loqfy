import type { AiSummary, Analysis, Catalog, CnpjLookup, Customer, DashboardMetrics, DeliveryZone, Equipment, FinanceSummary, FinancialEntry, Inspection, Quote, ReceiptRead, Rental, RentalRequest } from './types'

import { preparePhoto } from './photos'

export class ApiError extends Error {}

export const SIGNED_OUT = 'loqfy:signed-out'

export interface User {
  name: string
  email: string
}

async function call<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api${path}`, {
    ...init,
    headers: { Accept: 'application/json', ...(init?.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }) },
  })

  if (response.status === 401) window.dispatchEvent(new Event(SIGNED_OUT))

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const firstFieldError = body?.errors ? (Object.values(body.errors)[0] as string[])[0] : null
    if (response.status === 413) throw new ApiError('O arquivo é grande demais para enviar.')
    throw new ApiError(firstFieldError ?? body?.message ?? `Erro ${response.status}`)
  }

  return response.json()
}

const send = <T>(method: 'POST' | 'PUT' | 'DELETE', path: string, body?: unknown) =>
  call<T>(path, { method, body: body === undefined ? undefined : JSON.stringify(body) })

async function photo(file: File) {
  const form = new FormData()
  form.append('photo', await preparePhoto(file))
  return form
}

export interface QuoteEdit {
  customer_id: number | null
  customer_name: string | null
  items: Array<{ equipment_id: number; quantity: number; requested_term: string | null }>
  start_date: string | null
  end_date: string | null
  delivery: boolean
  neighborhood: string | null
}

export type CustomerEdit = Pick<Customer, 'name' | 'kind' | 'cnpj' | 'phone' | 'email' | 'address' | 'notes'> & { document_verified: boolean }
export type EquipmentEdit = Omit<Equipment, 'id'>
export type ZoneEdit = Omit<DeliveryZone, 'id'>
export type Decision = 'no_damage' | 'damage_confirmed'
export type EntryEdit = Pick<FinancialEntry, 'type' | 'category' | 'description' | 'amount' | 'due_date' | 'paid_at'> & Partial<Pick<FinancialEntry, 'origin' | 'receipt_photo'>>

export const api = {
  me: () => call<User>('/me'),
  demoLogin: () => send<User>('POST', '/login/demo'),
  logout: () => send<unknown>('POST', '/logout'),

  catalog: () => call<Catalog>('/catalog'),
  createEquipment: (edit: EquipmentEdit) => send<Equipment>('POST', '/equipment', edit),
  updateEquipment: (id: number, edit: EquipmentEdit) => send<Equipment>('PUT', `/equipment/${id}`, edit),
  deleteEquipment: (id: number) => send<unknown>('DELETE', `/equipment/${id}`),
  createZone: (edit: ZoneEdit) => send<DeliveryZone>('POST', '/delivery-zones', edit),
  updateZone: (id: number, edit: ZoneEdit) => send<DeliveryZone>('PUT', `/delivery-zones/${id}`, edit),
  deleteZone: (id: number) => send<unknown>('DELETE', `/delivery-zones/${id}`),

  customers: () => call<Customer[]>('/customers'),
  createCustomer: (edit: CustomerEdit) => send<Customer>('POST', '/customers', edit),
  updateCustomer: (id: number, edit: CustomerEdit) => send<Customer>('PUT', `/customers/${id}`, edit),
  deleteCustomer: (id: number) => send<unknown>('DELETE', `/customers/${id}`),
  cnpj: (digits: string) => call<CnpjLookup>(`/cnpj/${digits}`),

  quotes: () => call<Quote[]>('/quotes'),
  quote: (id: number) => call<Quote>(`/quotes/${id}`),
  createQuote: (edit: QuoteEdit) => send<Quote>('POST', '/quotes', edit),
  updateQuote: (id: number, edit: QuoteEdit) => send<Quote>('PUT', `/quotes/${id}`, edit),
  approveQuote: (id: number) => send<Quote>('POST', `/quotes/${id}/approve`),
  discardQuote: (id: number) => send<Quote>('POST', `/quotes/${id}/discard`),

  rentals: () => call<Rental[]>('/rentals'),
  contractUrl: (rentalId: number) => `/api/rentals/${rentalId}/contract`,
  checkoutRental: (id: number) => send<Rental>('POST', `/rentals/${id}/checkout`),
  manualReturn: (rentalItemId: number, decision: Decision, note: string | null) =>
    send<Inspection>('POST', `/rental-items/${rentalItemId}/manual-return`, { decision, note }),

  dashboard: () => call<{ metrics: DashboardMetrics; analysis: Analysis | null }>('/dashboard'),

  financeSummary: () => call<FinanceSummary>('/finance/summary'),
  entries: () => call<FinancialEntry[]>('/finance/entries'),
  createEntry: (edit: EntryEdit) => send<FinancialEntry>('POST', '/finance/entries', edit),
  updateEntry: (id: number, edit: EntryEdit) => send<FinancialEntry>('PUT', `/finance/entries/${id}`, edit),
  deleteEntry: (id: number) => send<unknown>('DELETE', `/finance/entries/${id}`),
  togglePaid: (id: number) => send<FinancialEntry>('POST', `/finance/entries/${id}/toggle-paid`),

  readReceipt: async (file: File) => call<ReceiptRead>('/finance/receipts', { method: 'POST', body: await photo(file) }),
  analyzeDashboard: () => send<{ analysis: Analysis }>('POST', '/dashboard/analysis'),
  requests: () => call<RentalRequest[]>('/rental-requests'),
  request: (id: number) => call<RentalRequest>(`/rental-requests/${id}`),
  createRequest: (text: string) => send<RentalRequest>('POST', '/rental-requests', { text }),
  reprocess: (id: number) => send<RentalRequest>('POST', `/rental-requests/${id}/reprocess`),
  discard: (id: number) => send<RentalRequest>('POST', `/rental-requests/${id}/discard`),
  checkoutPhoto: async (rentalItemId: number, file: File) =>
    call<Inspection>(`/rental-items/${rentalItemId}/checkout-photo`, { method: 'POST', body: await photo(file) }),
  returnPhoto: async (rentalItemId: number, file: File) =>
    call<Inspection>(`/rental-items/${rentalItemId}/return-photo`, { method: 'POST', body: await photo(file) }),
  decide: (id: number, decision: Decision, note: string | null) => send<Inspection>('POST', `/inspections/${id}/decision`, { decision, note }),
  aiSummary: () => call<AiSummary>('/ai/summary'),
}
