import axios, { type AxiosInstance, type AxiosResponse, type AxiosError, type AxiosRequestConfig } from 'axios';
import { useAuthStore } from '@/stores/auth-store';
import { ApiError } from './api-error';

const BASE_URL = import.meta.env.VITE_API_BASE_URL;

function buildUrlWithQueryParams(url: string, params: Record<string, never>): string {
  const queryParams = new URLSearchParams(params);
  return `${url}?${queryParams.toString()}`;
}

export async function getRequest<T>(url: string, params?: Record<string, never>, config?: AxiosRequestConfig) {
  let finalUrl = url;
  if (params) {
    finalUrl = buildUrlWithQueryParams(url, params);
  }
  const response = await apiClient.get<T>(finalUrl, config);
  return response.data;
}

export async function postRequest<T, U>(url: string, data: U, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.post<T>(url, data, config);
  return response.data;
}

export async function putRequest<T, U>(url: string, data: U, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.put<T>(url, data, config);
  return response.data;
}

export async function patchRequest<T, U>(url: string, data: U, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.patch<T>(url, data, config);
  return response.data;
}

export async function deleteRequest<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const response = await apiClient.delete<T>(url, config);
  return response.data;
}


const apiClient: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  // timeout: 10000,
});

apiClient.interceptors.request.use(
  (config) => {
    const accessToken = useAuthStore.getState().accessToken;

    if (accessToken && config.headers) {
      config.headers['Authorization'] = `Bearer ${accessToken}`;
    }
    return config;
  },
  (error: AxiosError) => {
    return Promise.reject(error);
  }
);

apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error: AxiosError) => {
    const { reset } = useAuthStore.getState();

    let apiError: ApiError;

    if (error.response) {
      const apiErrorData = error.response.data as {
        message?: string
        code?: string | null
        errors?: Record<string, string[]>
        meta?: unknown[]
      } | undefined
      const status = error.response.status;
      const message = apiErrorData?.message || 'Unknown API error';
      const code = apiErrorData?.code || null;
      const errors = apiErrorData?.errors || {};
      const meta = apiErrorData?.meta || [];

      apiError = new ApiError(
        status,
        code,
        message,
        errors,
        meta,
        error
      );


      const originalRequest = error.config as
        | (AxiosRequestConfig & { _retry?: boolean })
        | undefined

      if (error.response?.status === 401 && originalRequest && !originalRequest._retry) {
        originalRequest._retry = true;
        reset();
        window.location.href = `/sign-in?redirect=${encodeURIComponent(window.location.pathname)}`;
        // const authState = useAuthStore.getState();
      }

    } else if (error.request) {
      apiError = new ApiError(
        0,
        null,
        'Network error or no response from server.',
        {},
        [],
        error
      );
    } else {
      apiError = new ApiError(
        -1,
        null,
        'Error in setting up the request.',
        {},
        [],
        error
      );
    }

    return Promise.reject(apiError);
  }
);


export default apiClient;

