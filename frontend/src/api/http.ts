import axios, {
  type AxiosError,
  type AxiosRequestConfig,
  type InternalAxiosRequestConfig,
} from "axios";

import { ApiError } from "@/types/api";
import { i18n } from "@/i18n";

type RetryableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean };

let unauthorizedHandler: (() => void) | null = null;

/** Registers a callback invoked whenever any request responds with 401. */
export function setUnauthorizedHandler(handler: () => void): void {
  unauthorizedHandler = handler;
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? "",
  withCredentials: true,
  withXSRFToken: true,
});

http.interceptors.request.use((config) => {
  config.headers.set("Accept-Language", i18n.global.locale.value);
  return config;
});

http.interceptors.response.use(undefined, async (error: AxiosError) => {
  const status = error.response?.status ?? null;
  const config = error.config as RetryableConfig | undefined;

  // Expired CSRF token: refresh the cookie and retry the original request once.
  if (status === 419 && config && !config._csrfRetried) {
    config._csrfRetried = true;
    await http.get("/sanctum/csrf-cookie");
    return http.request(config);
  }

  if (status === 401) {
    unauthorizedHandler?.();
  }

  if (status !== null) {
    return Promise.reject(ApiError.fromHttp(status, error.response?.data));
  }

  return Promise.reject(
    new ApiError("network", "Network failure", { status: null }),
  );
});

export async function request<T>(config: AxiosRequestConfig): Promise<T> {
  const response = await http.request<T>(config);
  return response.data;
}
