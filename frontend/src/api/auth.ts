import { request } from "@/api/http";
import type { User } from "@/types/api";

interface LoginResponse {
  data: User;
}

export function fetchUser(): Promise<User> {
  return request<LoginResponse>({
    method: "GET",
    url: "/api/v1/auth/user",
  }).then((res) => res.data);
}

export function login(email: string, password: string): Promise<User> {
  return request<LoginResponse>({
    method: "POST",
    url: "/api/v1/auth/login",
    data: { email, password },
  }).then((res) => res.data);
}

export function logout(): Promise<void> {
  return request<void>({ method: "POST", url: "/api/v1/auth/logout" }).then(
    () => undefined,
  );
}
