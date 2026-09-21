import { request } from "@/api/http";
import type { PaginatedResponse } from "@/types/api";
import type { AdminUserDetail, AdminUserRow, UserPayload } from "@/types/user";

export interface AdminUserFilters {
  search?: string;
  role?: string;
  page?: number;
  per_page?: number;
}

export function fetchAdminUsers(
  filters: AdminUserFilters,
  signal?: AbortSignal,
): Promise<PaginatedResponse<AdminUserRow>> {
  const params: Record<string, string | number> = {};
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== null && value !== "") {
      params[key] = value;
    }
  }
  return request<PaginatedResponse<AdminUserRow>>({
    method: "GET",
    url: "/api/v1/admin/users",
    params,
    signal,
  });
}

export function fetchUser(id: number): Promise<{ data: AdminUserDetail }> {
  return request<{ data: AdminUserDetail }>({ method: "GET", url: `/api/v1/admin/users/${id}` });
}

export function createUser(payload: UserPayload): Promise<{ data: AdminUserDetail }> {
  return request<{ data: AdminUserDetail }>({ method: "POST", url: "/api/v1/admin/users", data: payload });
}

export function updateUser(id: number, payload: UserPayload): Promise<{ data: AdminUserDetail }> {
  return request<{ data: AdminUserDetail }>({ method: "PATCH", url: `/api/v1/admin/users/${id}`, data: payload });
}

export function deleteUser(id: number): Promise<void> {
  return request<void>({ method: "DELETE", url: `/api/v1/admin/users/${id}` });
}
