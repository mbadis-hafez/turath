import { request } from "@/api/http";
import type { PermissionCatalogue, Role, RoleDetail, RolePayload } from "@/types/role";

export async function listRoles(signal?: AbortSignal): Promise<Role[]> {
  const response = await request<{ data: Role[] }>({ method: "GET", url: "/api/v1/admin/roles", signal });
  return response.data;
}

export function getRole(id: number, signal?: AbortSignal): Promise<{ data: RoleDetail }> {
  return request({ method: "GET", url: `/api/v1/admin/roles/${id}`, signal });
}

export function getPermissionCatalogue(signal?: AbortSignal): Promise<PermissionCatalogue> {
  return request({ method: "GET", url: "/api/v1/admin/permissions", signal });
}

export function createRole(payload: RolePayload): Promise<{ data: RoleDetail }> {
  return request({ method: "POST", url: "/api/v1/admin/roles", data: payload });
}

export function updateRole(id: number, payload: RolePayload): Promise<{ data: RoleDetail }> {
  return request({ method: "PATCH", url: `/api/v1/admin/roles/${id}`, data: payload });
}

export function deleteRole(id: number): Promise<{ data: { deleted: boolean } }> {
  return request({ method: "DELETE", url: `/api/v1/admin/roles/${id}` });
}
