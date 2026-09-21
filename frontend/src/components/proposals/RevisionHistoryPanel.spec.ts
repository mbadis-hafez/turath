import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";

import RevisionHistoryPanel from "@/components/proposals/RevisionHistoryPanel.vue";
import { useAuthStore } from "@/stores/auth";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";
import type { Revision } from "@/types/proposal";

const api = vi.hoisted(() => ({ listRevisions: vi.fn(), rollbackRevision: vi.fn() }));
vi.mock("@/api/proposals", () => api);

const revision = (n: number, patch: Partial<Revision> = {}): Revision => ({
  id: `r${n}`, revision_number: n, source: "direct_edit", edit_proposal_id: null,
  field_diffs: { bio_en: { old: "Older.", new: "Newer." } }, field_labels: { bio_en: { ar: "السيرة", en: "Biography (English)" } },
  applied_by: { id: 1, name: "Munir" }, applied_at: "2026-09-01T10:00:00Z", reverted_by_revision_id: null, ...patch,
});

function signIn(permissions: string[]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useAuthStore().$patch({ user: { id: 1, name: "E", email: "e@x", roles: [], permissions } as never, initialized: true });
  return pinia;
}

let pinia: ReturnType<typeof createPinia>;
const mount = () => mountWithPlugins(RevisionHistoryPanel, {
  locale: "en", pinia, props: { type: "artists", recordId: 7, managePermission: "artists.manage" },
});

beforeEach(() => {
  api.listRevisions.mockReset().mockResolvedValue({ data: [revision(2, { source: "approved_proposal" }), revision(1)] });
  api.rollbackRevision.mockReset().mockResolvedValue({ data: revision(3, { source: "rollback" }) });
});

describe("RevisionHistoryPanel", () => {
  it("lists revisions newest first with their source, and expands a diff on demand", async () => {
    pinia = signIn(["artists.manage"]);
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.findAll("[data-testid=revision]")).toHaveLength(2);
    expect(wrapper.findAll("[data-testid=revision-source]").map((s) => s.text())).toEqual(["Approved proposal", "Direct edit"]);
    expect(wrapper.find("[data-testid=field-diffs]").exists()).toBe(false);

    await wrapper.findAll("[data-testid=toggle-diff]")[0].trigger("click");
    expect(wrapper.get("[data-testid=field-diffs]").text()).toContain("Biography (English)");
  });

  it("hides rollback from users without the manage permission", async () => {
    pinia = signIn(["proposals.submit"]);
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.findAll("[data-testid=revision]")).toHaveLength(2);
    expect(wrapper.findAll("[data-testid=rollback]")).toHaveLength(0);
  });

  it("hides rollback on a revision that was already reverted", async () => {
    api.listRevisions.mockResolvedValue({ data: [revision(1, { reverted_by_revision_id: "r2" })] });
    pinia = signIn(["artists.manage"]);
    const wrapper = mount();
    await flushPromises();

    expect(wrapper.findAll("[data-testid=reverted-badge]")).toHaveLength(1);
    expect(wrapper.findAll("[data-testid=rollback]")).toHaveLength(0);
  });

  it("rolls back, and requires explicit confirmation when the API says it would unpublish", async () => {
    pinia = signIn(["artists.manage"]);
    const wrapper = mount();
    await flushPromises();

    api.rollbackRevision.mockRejectedValueOnce(
      new ApiError("server", "would unpublish", { status: 409, body: { would_unpublish: true, blocking: ["death_year_or_living_confirmed"] } }),
    );
    await wrapper.findAll("[data-testid=rollback]")[0].trigger("click");
    await flushPromises();

    expect(wrapper.get("[data-testid=unpublish-warning]").text()).toContain("death_year_or_living_confirmed");
    expect(wrapper.emitted("rolledBack")).toBeUndefined();

    await wrapper.get("[data-testid=confirm-unpublish]").trigger("click");
    await flushPromises();

    expect(api.rollbackRevision).toHaveBeenLastCalledWith("artists", 7, "r2", true);
    expect(wrapper.emitted("rolledBack")).toHaveLength(1);
  });
});
