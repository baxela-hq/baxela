import { type AxiosError } from "axios";

export class ApiError extends Error {
  isApiError: boolean = true;
  status: number;
  code: string | null;
  errors: Record<string, string[]>;
  meta: unknown[];
  axiosError?: AxiosError; 

  constructor(
    status: number,
    code: string | null,
    message: string,
    errors: Record<string, string[]> = {},
    meta: unknown[] = [],
    axiosError?: AxiosError
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.errors = errors;
    this.meta = meta;
    this.axiosError = axiosError;
    // Object.setPrototypeOf(this, ApiError.prototype); 
  }
}