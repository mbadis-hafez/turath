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

/** Answers the same whether or not the address has an account. */
export function requestPasswordReset(email: string): Promise<void> {
  return request<void>({
    method: "POST",
    url: "/api/v1/auth/forgot-password",
    data: { email },
  }).then(() => undefined);
}

export interface PasswordResetPayload {
  token: string;
  email: string;
  password: string;
  passwordConfirmation: string;
}

export function resetPassword(payload: PasswordResetPayload): Promise<void> {
  return request<void>({
    method: "POST",
    url: "/api/v1/auth/reset-password",
    data: {
      token: payload.token,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.passwordConfirmation,
    },
  }).then(() => undefined);
}

export function changePassword(
  password: string,
  passwordConfirmation: string,
): Promise<User> {
  return request<LoginResponse>({
    method: "POST",
    url: "/api/v1/auth/password",
    data: { password, password_confirmation: passwordConfirmation },
  }).then((res) => res.data);
}
