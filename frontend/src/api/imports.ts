import { request } from "@/api/http";
import type { PaginatedResponse } from "@/types/api";
import type {
  ImportBatch,
  ImportBatchesQueryParams,
  ImportBatchRow,
  ImportBatchRowsQueryParams,
  ImportEntityType,
  ImportMappingProfile,
  ImportRowResolution,
} from "@/types/import";

function cleanParams(
  params: Record<string, string | number | undefined>,
): Record<string, string | number> {
  const result: Record<string, string | number> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") result[key] = value;
  }
  return result;
}

export function listImportBatches(
  params: ImportBatchesQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ImportBatch>> {
  return request<PaginatedResponse<ImportBatch>>({
    method: "GET",
    url: "/api/v1/imports",
    params: cleanParams({ ...params }),
    signal,
  });
}

export function getImportBatch(
  id: string,
  signal?: AbortSignal,
): Promise<{ data: ImportBatch }> {
  return request<{ data: ImportBatch }>({
    method: "GET",
    url: `/api/v1/imports/${id}`,
    signal,
  });
}

export interface CreateImportBatchPayload {
  entityType: ImportEntityType;
  file: File;
  mappingProfileId?: string;
  columnMap?: Record<string, string>;
}

export function createImportBatch(
  payload: CreateImportBatchPayload,
): Promise<{ data: ImportBatch }> {
  const form = new FormData();
  form.append("entity_type", payload.entityType);
  form.append("file", payload.file);
  if (payload.mappingProfileId) {
    form.append("mapping_profile_id", payload.mappingProfileId);
  } else if (payload.columnMap) {
    for (const [header, field] of Object.entries(payload.columnMap)) {
      form.append(`column_map[${header}]`, field);
    }
  }
  return request<{ data: ImportBatch }>({
    method: "POST",
    url: "/api/v1/imports",
    data: form,
  });
}

export function listImportBatchRows(
  batchId: string,
  params: ImportBatchRowsQueryParams = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<ImportBatchRow>> {
  return request<PaginatedResponse<ImportBatchRow>>({
    method: "GET",
    url: `/api/v1/imports/${batchId}/rows`,
    params: cleanParams({ ...params }),
    signal,
  });
}

export interface UpdateImportBatchRowPayload {
  resolution?: ImportRowResolution;
  matched_entity_id?: number | null;
}

export function updateImportBatchRow(
  batchId: string,
  rowId: string,
  payload: UpdateImportBatchRowPayload,
): Promise<{ data: ImportBatchRow }> {
  return request<{ data: ImportBatchRow }>({
    method: "PATCH",
    url: `/api/v1/imports/${batchId}/rows/${rowId}`,
    data: payload,
  });
}

export function revalidateImportBatch(
  batchId: string,
): Promise<{ data: ImportBatch }> {
  return request<{ data: ImportBatch }>({
    method: "POST",
    url: `/api/v1/imports/${batchId}/revalidate`,
  });
}

export function commitImportBatch(
  batchId: string,
): Promise<{ data: ImportBatch }> {
  return request<{ data: ImportBatch }>({
    method: "POST",
    url: `/api/v1/imports/${batchId}/commit`,
  });
}

export function cancelImportBatch(
  batchId: string,
): Promise<{ data: ImportBatch }> {
  return request<{ data: ImportBatch }>({
    method: "POST",
    url: `/api/v1/imports/${batchId}/cancel`,
  });
}

export function listMappingProfiles(
  entityType?: ImportEntityType,
  signal?: AbortSignal,
): Promise<{ data: ImportMappingProfile[] }> {
  return request<{ data: ImportMappingProfile[] }>({
    method: "GET",
    url: "/api/v1/import-mapping-profiles",
    params: cleanParams({ entity_type: entityType }),
    signal,
  });
}

export function createMappingProfile(payload: {
  entity_type: ImportEntityType;
  name: string;
  column_map: Record<string, string>;
}): Promise<{ data: ImportMappingProfile }> {
  return request<{ data: ImportMappingProfile }>({
    method: "POST",
    url: "/api/v1/import-mapping-profiles",
    data: payload,
  });
}
