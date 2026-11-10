import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { PaginatedResponse } from '@/shared/types/common.types'
import { fetchCreditNotes, fetchInvoices, retryPdfGeneration } from '../api/invoices.api'
import { FeatureRoutes } from '../data/routes'
import { type CreditNote, type Invoice } from '../data/schema'

export function useInvoicesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<Invoice>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search],
    queryFn: () => fetchInvoices(search),
  })
}

export function useCreditNotesList(search: Record<string, unknown>) {
  return useQuery<PaginatedResponse<CreditNote>>({
    queryKey: [`${FeatureRoutes.CACHE_KEY}-cn`, search],
    queryFn: () => fetchCreditNotes(search),
  })
}

export function useRetryPdfMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => retryPdfGeneration(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
    },
  })
}
