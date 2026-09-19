import { beforeEach, describe, expect, it, vi } from "vitest";

import { ApiError } from "@/types/api";

const mockHttp = vi.hoisted(() => {
  let errorHandler: ((error: unknown) => Promise<never>) | null = null;
  return {
    errorHandlerRef: {
      get: () => errorHandler,
      set: (fn: (error: unknown) => Promise<never>) => {
        errorHandler = fn;
      },
    },
    instance: {
      get: vi.fn(),
      request: vi.fn(),
      interceptors: {
        request: { use: vi.fn() },
        response: {
          use: vi.fn(
            (_ok: unknown, err: (error: unknown) => Promise<never>) => {
              errorHandler = err;
            },
          ),
        },
      },
      defaults: {},
    },
  };
});

vi.mock("axios", () => ({
  default: {
    create: vi.fn(() => mockHttp.instance),
  },
}));

import { request, setUnauthorizedHandler } from "@/api/http";

function httpError(status: number, data: unknown, config: object = {}) {
  return { response: { status, data }, config };
}

describe("api/http", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("419 refreshes the CSRF cookie and retries the original request once", async () => {
    const config = { url: "/api/v1/auth/user", method: "GET" };
    mockHttp.instance.get.mockResolvedValue({ status: 204 });
    mockHttp.instance.request.mockResolvedValue({ data: { ok: true } });

    const handler = mockHttp.errorHandlerRef.get();
    const result = await handler!(httpError(419, {}, config));

    expect(mockHttp.instance.get).toHaveBeenCalledTimes(1);
    expect(mockHttp.instance.get).toHaveBeenCalledWith("/sanctum/csrf-cookie");
    expect(mockHttp.instance.request).toHaveBeenCalledTimes(1);
    expect(mockHttp.instance.request).toHaveBeenCalledWith(
      expect.objectContaining({ url: "/api/v1/auth/user" }),
    );
    expect(result).toEqual({ data: { ok: true } });
  });

  it("419 retries only once, then rejects", async () => {
    const config = { url: "/api/v1/x", method: "GET" };
    const handler = mockHttp.errorHandlerRef.get();

    await handler!(httpError(419, {}, config));
    // Second failure on the already-retried config must not refresh again.
    await expect(handler!(httpError(419, {}, config))).rejects.toBeInstanceOf(
      ApiError,
    );
    expect(mockHttp.instance.get).toHaveBeenCalledTimes(1);
    expect(mockHttp.instance.request).toHaveBeenCalledTimes(1);
  });

  it("422 normalizes to field errors", async () => {
    const handler = mockHttp.errorHandlerRef.get();
    const error = await handler!(
      httpError(422, {
        message: "The given data was invalid.",
        errors: { email: ["The email field is required."] },
      }),
    ).catch((err: unknown) => err);

    expect(error).toBeInstanceOf(ApiError);
    const apiError = error as ApiError;
    expect(apiError.kind).toBe("validation");
    expect(apiError.status).toBe(422);
    expect(apiError.fieldErrors).toEqual({
      email: ["The email field is required."],
    });
    expect(apiError.message).toBe("The given data was invalid.");
  });

  it("429 becomes a typed throttled error", async () => {
    const handler = mockHttp.errorHandlerRef.get();
    const error = await handler!(
      httpError(429, { message: "Slow down" }),
    ).catch((err: unknown) => err);
    expect(error).toMatchObject({ kind: "throttled", status: 429 });
  });

  it("401 invokes the unauthorized handler and rejects as authentication", async () => {
    const onUnauthorized = vi.fn();
    setUnauthorizedHandler(onUnauthorized);
    const handler = mockHttp.errorHandlerRef.get();

    const error = await handler!(
      httpError(401, { message: "Unauthenticated" }),
    ).catch((err: unknown) => err);

    expect(onUnauthorized).toHaveBeenCalledTimes(1);
    expect(error).toMatchObject({ kind: "authentication", status: 401 });
  });

  it("network failures (no response) become kind 'network'", async () => {
    const handler = mockHttp.errorHandlerRef.get();
    const error = await handler!({ config: {}, request: {} }).catch(
      (err: unknown) => err,
    );
    expect(error).toMatchObject({ kind: "network", status: null });
  });

  it("request() resolves with the response body", async () => {
    mockHttp.instance.request.mockResolvedValue({ data: { id: 1 } });
    const data = await request<{ id: number }>({ url: "/api/v1/auth/user" });
    expect(data).toEqual({ id: 1 });
  });
});
