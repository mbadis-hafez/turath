import { request } from "@/api/http";
import type { Holder } from "@/types/holder";

export function getHolder(
  id: number,
  signal?: AbortSignal,
): Promise<{ data: Holder }> {
  return request<{ data: Holder }>({
    method: "GET",
    url: `/api/v1/holders/${id}`,
    signal,
  });
}
