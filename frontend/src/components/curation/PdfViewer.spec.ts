import { flushPromises } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";

import PdfViewer from "@/components/curation/PdfViewer.vue";
import { mountWithPlugins } from "@/test/utils";

const page = { getViewport: () => ({ width: 100, height: 140 }), render: () => ({ promise: Promise.resolve(), cancel: vi.fn() }) };
const doc = { numPages: 3, getPage: vi.fn().mockResolvedValue(page), destroy: vi.fn() };

vi.mock("pdfjs-dist", () => ({
  GlobalWorkerOptions: {},
  getDocument: vi.fn(() => ({ promise: Promise.resolve(doc) })),
}));
vi.mock("pdfjs-dist/build/pdf.worker.min.mjs?url", () => ({ default: "worker.js" }));

// jsdom's canvas has no real 2D context; stub it so render() calls don't throw.
HTMLCanvasElement.prototype.getContext = vi.fn().mockReturnValue({}) as never;

describe("PdfViewer", () => {
  it("loads the document and shows the page indicator", async () => {
    const wrapper = mountWithPlugins(PdfViewer, { props: { url: "/f/1", name: "report.pdf" } });
    await flushPromises();

    expect(wrapper.get("[data-testid=pdf-page-indicator]").text()).toContain("1");
    expect(wrapper.get("[data-testid=pdf-page-indicator]").text()).toContain("3");
  });

  it("navigates to the next page", async () => {
    const wrapper = mountWithPlugins(PdfViewer, { props: { url: "/f/1", name: "report.pdf" } });
    await flushPromises();

    const buttons = wrapper.findAll("button");
    await buttons[1]!.trigger("click"); // "›" next-page button
    await flushPromises();

    expect(wrapper.get("[data-testid=pdf-page-indicator]").text()).toContain("2");
  });

  it("shows an error message when the document fails to load", async () => {
    const { getDocument } = await import("pdfjs-dist");
    vi.mocked(getDocument).mockReturnValueOnce({ promise: Promise.reject(new Error("boom")) } as never);

    const wrapper = mountWithPlugins(PdfViewer, { props: { url: "/bad", name: "bad.pdf" } });
    await flushPromises();

    expect(wrapper.text()).toContain("boom");
  });
});
