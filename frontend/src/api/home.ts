import { request } from "@/api/http";
import type { HomeOverview } from "@/types/home";

export function getHomeOverview(signal?: AbortSignal): Promise<{ data: HomeOverview }> {
  return request({ method: "GET", url: "/api/v1/home", signal });
}
