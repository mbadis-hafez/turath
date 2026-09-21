export interface User {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
}

export interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: PaginationLink[];
  meta: PaginationMeta;
}

export type ApiErrorKind =
  | "validation"
  | "authentication"
  | "forbidden"
  | "not_found"
  | "throttled"
  | "network"
  | "server"
  | "unknown";

export class ApiError extends Error {
  readonly kind: ApiErrorKind;
  readonly status: number | null;
  readonly fieldErrors: Record<string, string[]>;
  /** The raw response body, for statuses that carry a structured payload (e.g. a 409 conflict). */
  readonly body: unknown;

  constructor(
    kind: ApiErrorKind,
    message: string,
    options: {
      status?: number | null;
      fieldErrors?: Record<string, string[]>;
      body?: unknown;
    } = {},
  ) {
    super(message);
    this.name = "ApiError";
    this.kind = kind;
    this.status = options.status ?? null;
    this.fieldErrors = options.fieldErrors ?? {};
    this.body = options.body ?? null;
  }

  static fromHttp(status: number, body: unknown): ApiError {
    const data = (body ?? {}) as {
      message?: string;
      errors?: Record<string, string[]>;
    };
    const message = data.message ?? "Something went wrong";
    const fieldErrors = data.errors ?? {};
    if (status === 422) {
      return new ApiError("validation", message, { status, fieldErrors });
    }
    if (status === 429) {
      return new ApiError("throttled", message, { status });
    }
    if (status === 401) {
      return new ApiError("authentication", message, { status });
    }
    if (status === 403) {
      return new ApiError("forbidden", message, { status });
    }
    if (status === 404) {
      return new ApiError("not_found", message, { status });
    }
    return new ApiError("server", message, { status, body });
  }
}
