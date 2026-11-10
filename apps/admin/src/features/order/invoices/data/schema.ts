import { z } from 'zod'

export const invoiceStatusSchema = z.enum(['active', 'void'])
export type InvoiceStatus = z.infer<typeof invoiceStatusSchema>

export const pdfStatusSchema = z.enum([
  'pending_generation',
  'generating',
  'ready',
  'generation_failed',
])
export type PdfStatus = z.infer<typeof pdfStatusSchema>

export const invoiceSchema = z.object({
  id: z.number(),
  order_id: z.number(),
  revision: z.number(),
  number: z.string(),
  status: invoiceStatusSchema,
  issued_at: z.string().nullable(),
  voided_at: z.string().nullable().optional(),
  void_reason: z.string().nullable().optional(),
  currency_code: z.string(),
  subtotal_amount: z.string().nullable(),
  discount_amount: z.string().nullable(),
  coupon_code: z.string().nullable().optional(),
  shipping_amount: z.string().nullable(),
  shipping_tax_amount: z.string().nullable(),
  tax_amount: z.string().nullable(),
  prices_contain_tax: z.boolean(),
  total_amount: z.string().nullable(),
  credited_amount: z.string(),
  balance: z.string(),
  billing_address: z.record(z.unknown()).nullable().optional(),
  buyer_identity: z.record(z.unknown()).nullable().optional(),
  seller_identity: z.record(z.unknown()).nullable().optional(),
  meta: z.record(z.unknown()).nullable().optional(),
  pdf_status: pdfStatusSchema,
})
export type Invoice = z.infer<typeof invoiceSchema>

export const creditNoteSchema = z.object({
  id: z.number(),
  invoice_id: z.number(),
  refund_uuid: z.string().nullable().optional(),
  number: z.string(),
  status: invoiceStatusSchema,
  issued_at: z.string().nullable(),
  currency_code: z.string(),
  amount: z.string(),
  reason: z.string().nullable().optional(),
  pdf_status: pdfStatusSchema,
})
export type CreditNote = z.infer<typeof creditNoteSchema>

export const voidSchema = z.object({
  void_reason: z.string().min(1).max(500),
})
export type InvoiceVoid = z.infer<typeof voidSchema>
