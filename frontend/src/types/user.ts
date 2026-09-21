export interface AdminUserRow {
  id: number;
  name: string;
  email: string;
  roles: string[];
  is_active: boolean;
}

export interface AdminUserDetail {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  is_active: boolean;
}

export interface UserPayload {
  name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
  role?: string;
  is_active?: boolean;
}

export const ADMIN_ROLES = [
  "reader",
  "contributor",
  "verified_researcher",
  "institution",
  "artist_claimed",
  "editor",
  "reviewer",
  "admin",
  "superadmin",
] as const;

export type AdminRole = (typeof ADMIN_ROLES)[number];
