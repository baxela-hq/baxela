import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { PostCategory, PostCategoryForm } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "content/admin/post-categories";

export async function createPostCategory(request: PostCategoryForm): Promise<PostCategory> {
  const { data } = await postRequest<SingleResponse<PostCategory>, PostCategoryForm>(BASE_URL, request);
  return data as PostCategory;
}

export function updatePostCategory(id: string, data: PostCategoryForm): Promise<PostCategory> {
  return patchRequest<PostCategory, PostCategoryForm>(`${BASE_URL}/${id}`, data);
}

export function fetchPostCategories(queryParams = {}){
  return getRequest<PaginatedResponse<PostCategory>>(BASE_URL, queryParams);
}

export async function fetchOnePostCategory(id: string): Promise<PostCategory>{
  const { data } = await getRequest<SingleResponse<PostCategory>>(`${BASE_URL}/${id}`);
  return data as PostCategory;
}

export function deletePostCategory(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}
