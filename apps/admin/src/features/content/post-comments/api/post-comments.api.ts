import { deleteRequest, getRequest, patchRequest, postRequest } from '@/shared/lib/api-client'
import {
  type PostComment,
  type PostCommentForm,
  type PostCommentReplyRequest,
} from '../data/schema'
import { type PaginatedResponse } from '@/shared/types/common.types'

const BASE_URL = 'content/admin/post-comments'

export function fetchPostComments(queryParams = {}) {
  return getRequest<PaginatedResponse<PostComment>>(BASE_URL, queryParams)
}

export function createPostComment(request: PostCommentReplyRequest): Promise<PostComment> {
  return postRequest<PostComment, PostCommentReplyRequest>(BASE_URL, request)
}

// the API patches every field of the record, so the full payload is sent
export function updatePostComment(id: string, data: PostCommentForm): Promise<PostComment> {
  return patchRequest<PostComment, PostCommentForm>(`${BASE_URL}/${id}`, data)
}

export function deletePostComment(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`)
}
