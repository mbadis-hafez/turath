import { describe, expect, it } from "vitest";

import OcrStatusCard from "@/components/curation/OcrStatusCard.vue";
import { mountWithPlugins } from "@/test/utils";
import type { FileOcrBundle, OcrStageRun } from "@/types/ocr";

function stage(patch: Partial<OcrStageRun> & Pick<OcrStageRun, "stage">): OcrStageRun {
  return {
    core: patch.stage !== "correct", status: "succeeded", reason: null, attempts: 1, error: null, stale: false, can_run: true,
    queued_at: null, started_at: null, finished_at: null, summary: null,
    ...patch,
  };
}

function bundle(stages?: OcrStageRun[]): FileOcrBundle {
  return {
    status: "completed", progress_pct: 100, language_confidence: { ar: 90, en: 88 }, failure_reason: null, document_type: null,
    stages, texts: { ar: [], en: [] }, fields: [], regions: [], form_fields: [], dates: [],
  };
}

function mount(stages?: OcrStageRun[], canRerun = true) {
  return mountWithPlugins(OcrStatusCard, { locale: "en", props: { bundle: bundle(stages), pendingCount: 0, averageConfidence: null, canRerun } });
}

describe("OcrStatusCard stages", () => {
  it("lists nothing for a file processed before stages were tracked", () => {
    const wrapper = mount([stage({ stage: "recognize", status: null }), stage({ stage: "extract", status: null }), stage({ stage: "correct", status: null })]);
    expect(wrapper.find("[data-testid=ocr-stages]").exists()).toBe(false);
    expect(mount(undefined).find("[data-testid=ocr-stages]").exists()).toBe(false);
  });

  it("shows each stage's state, including why one was skipped and what AI correction cost", () => {
    const wrapper = mount([
      stage({ stage: "recognize", status: "skipped", reason: "up_to_date" }),
      stage({ stage: "extract" }),
      stage({ stage: "correct", summary: { provider_calls: 1, cache_hits: 3 } }),
    ]);

    expect(wrapper.get("[data-testid=ocr-stage-recognize] [data-testid=ocr-stage-status]").text()).toBe("Up to date");
    expect(wrapper.get("[data-testid=ocr-stage-extract] [data-testid=ocr-stage-status]").text()).toBe("Done");
    expect(wrapper.get("[data-testid=ocr-stage-cost]").text()).toBe("1 AI calls · 3 reused");
  });

  it("shows a failed or retrying stage's error, and a stalled stage as stalled", () => {
    const wrapper = mount([
      stage({ stage: "recognize" }),
      stage({ stage: "extract", status: "running", stale: true }),
      stage({ stage: "correct", status: "queued", reason: "retrying", attempts: 2, error: "OcrCorrectionException: HTTP 429" }),
    ]);

    expect(wrapper.get("[data-testid=ocr-stage-extract] [data-testid=ocr-stage-status]").text()).toContain("Stalled");
    expect(wrapper.get("[data-testid=ocr-stage-correct] [data-testid=ocr-stage-status]").text()).toContain("2 attempts so far");
    expect(wrapper.get("[data-testid=ocr-stage-correct] [data-testid=ocr-stage-error]").text()).toContain("HTTP 429");
  });

  it("falls back to the plain status for a skip reason it has no wording for", () => {
    const wrapper = mount([stage({ stage: "recognize" }), stage({ stage: "extract" }), stage({ stage: "correct", status: "skipped", reason: "something_new" })]);
    expect(wrapper.get("[data-testid=ocr-stage-correct] [data-testid=ocr-stage-status]").text()).toBe("Skipped");
  });

  it("offers a re-run only for stages that can run, and only to someone who may run them", async () => {
    const stages = [stage({ stage: "recognize" }), stage({ stage: "extract" }), stage({ stage: "correct", can_run: false })];
    const wrapper = mount(stages);

    expect(wrapper.find("[data-testid=ocr-stage-rerun-correct]").exists()).toBe(false);
    await wrapper.get("[data-testid=ocr-stage-rerun-extract]").trigger("click");
    expect(wrapper.emitted("rerun-stage")?.[0]).toEqual(["extract"]);

    expect(mount(stages, false).find("[data-testid=ocr-stage-rerun-extract]").exists()).toBe(false);
  });
});
