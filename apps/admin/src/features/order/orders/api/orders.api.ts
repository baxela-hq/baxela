import { getRequest, patchRequest } from "@/shared/lib/api-client";
import type { Order, OrderForm, OrderItem } from "../data/schema";
import type { PaginatedResponse, AllResponse, SingleResponse } from "@/shared/types/common.types";

const BASE_URL = "order/admin/orders";

export async function updateOrder(id: string, body: OrderForm): Promise<Order> {
  const { data } = await patchRequest<SingleResponse<Order>, OrderForm>(`${BASE_URL}/${id}`, body);
  return data;
}

export function fetchOrders(queryParams = {}){
  return getRequest<PaginatedResponse<Order>>(BASE_URL, queryParams);
}

export async function fetchItems(id: string): Promise<OrderItem[]>{
  const {data} = await getRequest<AllResponse<OrderItem>>(`${BASE_URL}/${id}/items`);
  return data;
}

export async function fetchOneOrder(id: string): Promise<Order>{
  const { data } = await getRequest<SingleResponse<Order>>(`${BASE_URL}/${id}`);
  return data as Order;
}


