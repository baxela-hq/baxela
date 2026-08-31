import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import {
  type ProductComment,
  type ProductCommentForm,
  type ProductCommentReplyRequest,
} from '../data/schema'
import { type PaginatedResponse } from '@/shared/types/common.types'

const BASE_URL = 'catalog/admin/product-comments'

export function fetchProductComments(queryParams = {}) {
  return getRequest<PaginatedResponse<ProductComment>>(BASE_URL, queryParams)
}

export function createProductComment(request: ProductCommentReplyRequest): Promise<ProductComment> {
  return postRequest<ProductComment, ProductCommentReplyRequest>(BASE_URL, request)
}

// the API patches every field of the record, so the full payload is sent
export function updateProductComment(id: string, data: ProductCommentForm): Promise<ProductComment> {
  return patchRequest<ProductComment, ProductCommentForm>(`${BASE_URL}/${id}`, data)
}

export function deleteProductComment(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
