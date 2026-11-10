import apiClient, { getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import type { CreditNote, Invoice } from '../data/schema'
import { type PaginatedResponse, type SingleResponse } from '@/shared/types/common.types'

const BASE_URL = 'order/admin'

export function fetchInvoices(queryParams = {}) {
  return getRequest<PaginatedResponse<Invoice>>(`${BASE_URL}/invoices`, queryParams)
}

export function fetchInvoice(id: string) {
  return getRequest<SingleResponse<Invoice>>(`${BASE_URL}/invoices/${id}`)
}

export function voidInvoice(id: number, reason: string) {
  return patchRequest<SingleResponse<Invoice>, { void_reason: string }>(
    `${BASE_URL}/invoices/${id}/void`,
    { void_reason: reason }
  )
}

export function issueInvoice(orderId: number) {
  return postRequest<SingleResponse<Invoice>, Record<string, never>>(`${BASE_URL}/orders/${orderId}/invoice`)
}

export function retryPdfGeneration(id: number) {
  return postRequest<SingleResponse<Invoice>, Record<string, never>>(`${BASE_URL}/invoices/${id}/pdf/retry`)
}

export function fetchCreditNotes(queryParams = {}) {
  return getRequest<PaginatedResponse<CreditNote>>(`${BASE_URL}/credit-notes`, queryParams)
}

/** Download the invoice PDF as a Blob. */
export async function downloadInvoicePdf(id: number): Promise<Blob> {
  const response = await apiClient.get(`${BASE_URL}/invoices/${id}/pdf`, {
    responseType: 'blob',
    headers: { Accept: 'application/pdf' },
  })
  return response.data as Blob
}

/** Download the credit note PDF as a Blob. */
export async function downloadCreditNotePdf(id: number): Promise<Blob> {
  const response = await apiClient.get(`${BASE_URL}/credit-notes/${id}/pdf`, {
    responseType: 'blob',
    headers: { Accept: 'application/pdf' },
  })
  return response.data as Blob
}
