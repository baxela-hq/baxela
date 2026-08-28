
export interface SingleResponse<T> {
  data: T
}

export interface AllResponse<T> {
  data: T[]
}


export interface PaginatedResponse<T> {
  data: T[];
  links: Record<string, never>;
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    current_page_url?: string;
    first_page_url?: string;
    last_page_url?: string;
    next_page_url?: string;
    prev_page_url?: string;
    path?: string;
  };
}