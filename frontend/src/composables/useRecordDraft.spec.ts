import { beforeEach, describe, expect, it, vi } from "vitest";

import { useRecordDraft } from "@/composables/useRecordDraft";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ getDraft: vi.fn(), saveDraft: vi.fn(), submitDraft: vi.fn() }));
vi.mock("@/api/editorial", () => api);

describe("useRecordDraft", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("starts empty when no draft exists", async () => {
    api.getDraft.mockResolvedValue({ data: null });
    const draft = useRecordDraft();

    await draft.init("artists", 36);

    expect(api.getDraft).toHaveBeenCalledWith("artists", 36);
    expect(draft.status.value).toBeNull();
    expect(draft.hasDraft.value).toBe(false);
    expect(draft.draftPayload.value).toEqual({});
  });

  it("loads an existing draft's status, note and payload", async () => {
    api.getDraft.mockResolvedValue({
      data: { status: "changes_requested", review_note: "Fix the birth year", payload: { fields: { legacy_code: "AR036" } } },
    });
    const draft = useRecordDraft();

    await draft.init("artists", 36);

    expect(draft.status.value).toBe("changes_requested");
    expect(draft.reviewNote.value).toBe("Fix the birth year");
    expect(draft.draftPayload.value).toEqual({ fields: { legacy_code: "AR036" } });
    expect(draft.hasDraft.value).toBe(true);
  });

  it("turns a 409 on init into a blocker", async () => {
    api.getDraft.mockRejectedValue(new ApiError("server", "conflict", { status: 409, body: { message: "conflict", proposal_id: "p9" } }));
    const draft = useRecordDraft();

    await draft.init("artists", 36);

    expect(draft.blocker.value).toEqual({ proposal_id: "p9" });
    expect(draft.hasDraft.value).toBe(false);
  });

  it("merges sections, PUTs the full payload and tracks dirty sections", async () => {
    api.getDraft.mockResolvedValue({ data: { status: "draft", payload: { fields: { legacy_code: "AR036" } } } });
    api.saveDraft.mockResolvedValue({ data: { status: "draft" } });
    const draft = useRecordDraft();
    await draft.init("artists", 36);

    await draft.saveSection("curation", { owner_type: "gallery" });

    expect(api.saveDraft).toHaveBeenCalledWith("artists", 36, {
      fields: { legacy_code: "AR036" },
      curation: { owner_type: "gallery" },
    });
    expect(draft.status.value).toBe("draft");
    expect(draft.draftPayload.value).toEqual({ fields: { legacy_code: "AR036" }, curation: { owner_type: "gallery" } });
    expect(draft.sectionInDraft("curation")).toBe(true);
    expect(draft.sectionInDraft("fields")).toBe(false);

    await draft.saveSection("educations", []);
    expect(api.saveDraft).toHaveBeenLastCalledWith("artists", 36, {
      fields: { legacy_code: "AR036" },
      curation: { owner_type: "gallery" },
      educations: [],
    });
    expect([...draft.dirtySections.value].sort()).toEqual(["curation", "educations"]);
  });

  it("does not commit the merge when the save fails, and rethrows", async () => {
    api.getDraft.mockResolvedValue({ data: null });
    api.saveDraft.mockRejectedValue(new ApiError("validation", "Invalid", { status: 422, fieldErrors: { "payload.fields.birth": ["bad"] } }));
    const draft = useRecordDraft();
    await draft.init("artists", 36);

    await expect(draft.saveSection("fields", { legacy_code: "X" })).rejects.toBeInstanceOf(ApiError);
    expect(draft.draftPayload.value).toEqual({});
    expect(draft.sectionInDraft("fields")).toBe(false);
  });

  it("submits the draft and flips the status to pending", async () => {
    api.getDraft.mockResolvedValue({ data: { status: "draft", payload: { fields: {} } } });
    api.saveDraft.mockResolvedValue({ data: { status: "draft" } });
    api.submitDraft.mockResolvedValue({ data: { status: "pending" } });
    const draft = useRecordDraft();
    await draft.init("artists", 36);
    await draft.saveSection("fields", {});

    await draft.submit();

    expect(api.submitDraft).toHaveBeenCalledWith("artists", 36);
    expect(draft.status.value).toBe("pending");
    expect(draft.dirtySections.value.size).toBe(0);
  });

  it("resetLocally clears the overlay without calling the backend", async () => {
    api.getDraft.mockResolvedValue({ data: { status: "draft", payload: { fields: {} } } });
    const draft = useRecordDraft();
    await draft.init("artists", 36);

    draft.resetLocally();

    expect(draft.status.value).toBeNull();
    expect(draft.draftPayload.value).toEqual({});
    expect(api.saveDraft).not.toHaveBeenCalled();
    expect(api.submitDraft).not.toHaveBeenCalled();
  });
});
