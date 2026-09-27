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
