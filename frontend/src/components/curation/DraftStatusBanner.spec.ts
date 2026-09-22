import { flushPromises } from "@vue/test-utils";
import { describe, expect, it } from "vitest";

import DraftStatusBanner from "@/components/curation/DraftStatusBanner.vue";
import { mountWithPlugins } from "@/test/utils";

function mountBanner(props: Record<string, unknown> = {}) {
  return mountWithPlugins(DraftStatusBanner, {
    locale: "en",
    props: { status: null, blocker: null, canSubmit: false, submitting: false, reviewNote: null, ...props },
  });
}

describe("DraftStatusBanner", () => {
  it("shows a subtle hint when there is no draft", () => {
    const wrapper = mountBanner();

    expect(wrapper.get("[data-testid=draft-hint]").text()).toContain("stored as a draft");
    expect(wrapper.find("[data-testid=draft-banner]").exists()).toBe(false);
  });

  it("shows the draft badge with a disabled send button until a section is dirty", () => {
    const wrapper = mountBanner({ status: "draft" });

    expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("draft");
    expect(wrapper.text()).toContain("Draft — not sent yet");
    const send = wrapper.get("[data-testid=send-for-review]");
    expect(send.attributes("disabled")).toBeDefined();
    expect(send.attributes("title")).toContain("Save at least one section");
  });

  it("enables send for review once a section is dirty and hides discard for changes_requested", () => {
    const draft = mountBanner({ status: "draft", canSubmit: true });
    expect(draft.get("[data-testid=send-for-review]").attributes("disabled")).toBeUndefined();
    expect(draft.find("[data-testid=discard-draft]").exists()).toBe(true);

    const changes = mountBanner({ status: "changes_requested", canSubmit: true, reviewNote: "Fix the year" });
    expect(changes.get("[data-testid=draft-banner]").attributes("data-state")).toBe("changes_requested");
    expect(changes.get("[data-testid=reviewer-note]").text()).toContain('Reviewer note: "Fix the year"');
    expect(changes.get("[data-testid=send-for-review]").attributes("disabled")).toBeUndefined();
    expect(changes.find("[data-testid=discard-draft]").exists()).toBe(false);
  });

  it("renders the pending state without any actions", () => {
    const wrapper = mountBanner({ status: "pending" });

    expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("pending");
    expect(wrapper.text()).toContain("Awaiting review");
    expect(wrapper.find("[data-testid=send-for-review]").exists()).toBe(false);
  });

  it("renders a non-actionable blocker state", () => {
    const wrapper = mountBanner({ blocker: { proposal_id: "p9" }, status: null });

    expect(wrapper.get("[data-testid=draft-banner]").attributes("data-state")).toBe("blocked");
    expect(wrapper.text()).toContain("open review by someone else");
    expect(wrapper.find("[data-testid=send-for-review]").exists()).toBe(false);
  });

  it("emits discard only after the confirm dialog is accepted", async () => {
    const wrapper = mountBanner({ status: "draft", canSubmit: true });

    await wrapper.get("[data-testid=discard-draft]").trigger("click");
    expect(wrapper.emitted("discard")).toBeUndefined();

    document.querySelector("[data-testid=confirm-dialog-confirm]")!.dispatchEvent(new Event("click", { bubbles: true }));
    await flushPromises();
    expect(wrapper.emitted("discard")).toHaveLength(1);
  });

  it("emits submit when the send button is clicked", async () => {
    const wrapper = mountBanner({ status: "draft", canSubmit: true });

    await wrapper.get("[data-testid=send-for-review]").trigger("click");

    expect(wrapper.emitted("submit")).toHaveLength(1);
  });
});
