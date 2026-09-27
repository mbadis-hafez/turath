import type { Localized } from "@/types/artistCuration";

export interface Role {
  id: number;
  name: string;
  display_name: Localized;
  description: Localized;
  is_built_in: boolean;
  permissions_editable: boolean;
  users_count: number;
  permissions_count: number;
}

export interface RoleDetail extends Role {
  updated_at: string;
  permissions: string[];
}

export interface Permission {
  name: string;
  label: Localized;
}

export interface PermissionGroup {
  group: string;
  permissions: Permission[];
}

export interface PermissionCatalogue {
  data: PermissionGroup[];
  meta: { grantable: string[] };
}

export interface RolePayload {
  name_ar?: string;
  name_en?: string;
  description_ar?: string | null;
  description_en?: string | null;
  permissions?: string[];
  updated_at?: string;
}
