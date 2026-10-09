import { deleteRequest, getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Post, PostPayload } from "../data/schema";
import { type PaginatedResponse, type SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "content/admin/posts";

export function fetchPosts(queryParams = {}){
  return getRequest<PaginatedResponse<Post>>(BASE_URL, queryParams);
}

export async function fetchOnePost(id: string): Promise<Post>{
  const { data } = await getRequest<SingleResponse<Post>>(`${BASE_URL}/${id}`);
  return data as Post;
}

export function deletePost(id: string) {
  return deleteRequest(`${BASE_URL}/${id}`);
}

export async function createPost(request: PostPayload): Promise<Post> {
  const { data } = await postRequest<SingleResponse<Post>, PostPayload>(BASE_URL, request);
  return data as Post;
}

export function updatePost(id: string, data: PostPayload): Promise<Post> {
  return patchRequest<Post, PostPayload>(`${BASE_URL}/${id}`, data);
}
