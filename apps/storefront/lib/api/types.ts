// Response types for the Laravel backend API — mirrors the API resources
// (see api/bruno for the request collection). Prices arrive as decimal
// strings; format with next-intl's formatter at render time.

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

export interface Paginated<T> {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: PaginationMeta;
}

export interface ApiProduct {
  id: number;
  title: string | null;
  slug: string | null;
  description: string | null;
  price: string | null;
  compare_price: string | null;
  image_url: string | null;
  created_at: string;
}

export interface ApiOptionValueRef {
  id: number;
  title: string | null;
}

export interface ApiVariant {
  id: number;
  sku: string;
  price: string;
  compare_price: string | null;
  is_default: boolean;
  option_values: ApiOptionValueRef[];
}

export interface ApiImage {
  id: number;
  url: string;
  collection: string;
  position: number;
}

export interface ApiCategoryRef {
  id: number;
  title: string | null;
  slug: string | null;
}

export interface ApiProductDetail {
  id: number;
  title: string | null;
  slug: string | null;
  description: string | null;
  content: string | null;
  price: string | null;
  compare_price: string | null;
  variants: ApiVariant[];
  images: ApiImage[];
  categories: ApiCategoryRef[];
}

export interface ApiPublicCategory {
  id: number;
  parent_id: number | null;
  position: number | null;
  title: string | null;
  slug: string | null;
}

export interface ApiUser {
  id: number;
  email: string;
  email_verified_at: string | null;
  is_active: boolean;
  is_admin: boolean;
  comment: string | null;
}

export interface ApiCartItem {
  id: number;
  variant_id: number;
  cart_id: number;
  price_snapshot: string;
  product_name_snapshot: string;
  quantity: number;
  created_at: string;
  updated_at: string;
}

export type ApiGender = "male" | "female" | "other";

export interface ApiProfile {
  full_name: string | null;
  display_name: string | null;
  bio: string | null;
  avatar: string | null;
  gender: ApiGender | null;
  date_of_birth: string | null;
}

export type ApiOrderStatus =
  | "draft"
  | "pending_payment"
  | "paid"
  | "processing"
  | "shipped"
  | "completed"
  | "cancelled"
  | "refunded";

export interface ApiOrder {
  id: number;
  status: ApiOrderStatus;
  total_amount: string;
  shipping_method_name: string | null;
  shipping_cost: string | null;
  addresses: ApiOrderAddress[];
}

export interface ApiOrderItem {
  id: number;
  variant_id: number;
  product_name_snapshot: string;
  price_snapshot: string;
  quantity: number;
}

export interface ApiOrderAddress {
  order_id: number;
  type: string;
  full_name: string;
  phone: string;
  address_line: string;
  city: string;
  postal_code: string | null;
  country_code: string;
}

export interface ApiAddress {
  id: number;
  type: string;
  full_name: string;
  phone: string;
  address_line: string;
  city: string;
  postal_code: string | null;
  country_code: string;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface ApiShippingMethod {
  id: number;
  name: string;
  price: number;
}

export interface ApiCountry {
  id: number;
  name: string;
  code: string;
}

export interface ApiProductCommentUser {
  id: number;
  name: string | null;
}

export interface ApiProductComment {
  id: number;
  body: string;
  created_at: string;
  user: ApiProductCommentUser | null;
  replies: ApiProductComment[];
}
