import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createMemoryHistory, createRouter, type Router } from "vue-router";

import MaterialSubmissionPage from "@/pages/MaterialSubmissionPage.vue";
import { mountWithPlugins } from "@/test/utils";
import { ApiError } from "@/types/api";

const api = vi.hoisted(() => ({ submitMaterial: vi.fn() }));
vi.mock("@/api/submissions", () => api);

let router: Router;

async function mountPage() {
  setActivePinia(createPinia());
  await router.push("/en/submit");
  const wrapper = mountWithPlugins(MaterialSubmissionPage, { locale: "en", router });
  await flushPromises();
  return wrapper;
}

const file = (name: string, type: string, size = 100) => {
  const f = new File(["x"], name, { type });
  Object.defineProperty(f, "size", { value: size });
  return f;
};

async function addFiles(wrapper: Awaited<ReturnType<typeof mountPage>>, files: File[]) {
  const input = wrapper.get("[data-testid=file-input]");
  Object.defineProperty(input.element, "files", { value: files, configurable: true });
  await input.trigger("change");
}

async function fillRequired(wrapper: Awaited<ReturnType<typeof mountPage>>) {
  await wrapper.get("[data-testid=name]").setValue("Abdullah");
  await wrapper.get("[data-testid=contact]").setValue("0505 867 193");
  await wrapper.get("[data-testid=role]").setValue("artist_family");
  await wrapper.get("[data-testid=description]").setValue("Twelve photographs from the late 1970s.");
}

beforeEach(() => {
  api.submitMaterial.mockReset().mockResolvedValue({ data: { id: 41, status: "submitted" } });
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/:locale/submit", name: "submit", component: MaterialSubmissionPage },
      { path: "/:locale", name: "home", component: { template: "<div />" } },
    ],
  });
});

describe("MaterialSubmissionPage (arriving from a link)", () => {
  it("pre-selects the researcher role from ?role=researcher, and the visitor can still change it", async () => {
    await router.push("/en/submit?role=researcher");
    const wrapper = mountWithPlugins(MaterialSubmissionPage, { locale: "en", router });
    await flushPromises();

    const role = wrapper.get("[data-testid=role]");
    expect((role.element as HTMLSelectElement).value).toBe("researcher");
    await role.setValue("artist");
    expect((role.element as HTMLSelectElement).value).toBe("artist");
  });

  it("ignores a role it does not recognise and leaves the choice empty", async () => {
    await router.push("/en/submit?role=admin");
    const wrapper = mountWithPlugins(MaterialSubmissionPage, { locale: "en", router });
    await flushPromises();

    expect((wrapper.get("[data-testid=role]").element as HTMLSelectElement).value).toBe("");
  });
});

describe("MaterialSubmissionPage", () => {
  it("explains the four steps beside the form", async () => {
    const wrapper = await mountPage();

    expect(wrapper.findAll("[data-testid=steps] li")).toHaveLength(4);
    expect(wrapper.get("[data-testid=steps]").text()).toContain("Signed authorization");
  });

  it("stays disabled until name, contact, role and description are filled and the attestation is ticked", async () => {
    const wrapper = await mountPage();
    const submit = wrapper.get("[data-testid=submit]");
    expect(submit.attributes("disabled")).toBeDefined();

    await fillRequired(wrapper);
    expect(submit.attributes("disabled")).toBeDefined(); // attestation still unticked

    await wrapper.get("[data-testid=attestation]").setValue(true);
    expect(submit.attributes("disabled")).toBeUndefined();

    await wrapper.get("[data-testid=contact]").setValue("   ");
    expect(submit.attributes("disabled")).toBeDefined();
  });

  it("rejects an unsupported file type before anything uploads, naming the file", async () => {
    const wrapper = await mountPage();

    await addFiles(wrapper, [file("notes.docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"), file("scan.pdf", "application/pdf")]);

    expect(wrapper.get("[data-testid=file-problems]").text()).toContain("notes.docx");
    expect(wrapper.findAll("[data-testid=file-list] li")).toHaveLength(1);
    expect(api.submitMaterial).not.toHaveBeenCalled();
  });

  it("accepts TIFF and MP3 even when the browser reports no type, using the extension", async () => {
    const wrapper = await mountPage();

    await addFiles(wrapper, [file("card.tif", ""), file("talk.mp3", "")]);

    expect(wrapper.findAll("[data-testid=file-list] li")).toHaveLength(2);
    expect(wrapper.find("[data-testid=file-problems]").exists()).toBe(false);
  });

  it("refuses a file that would push the submission over 2 GB", async () => {
    const wrapper = await mountPage();
    const GB = 1024 * 1024 * 1024;

    await addFiles(wrapper, [file("a.pdf", "application/pdf", 1.5 * GB), file("b.pdf", "application/pdf", 1 * GB)]);

    expect(wrapper.findAll("[data-testid=file-list] li")).toHaveLength(1);
    expect(wrapper.get("[data-testid=file-problems]").text()).toContain("2 GB");
  });

  it("sends the form and files, then shows an on-page confirmation of the four steps", async () => {
    const wrapper = await mountPage();
    await fillRequired(wrapper);
    await wrapper.get("[data-testid=city]").setValue("Saihat");
    await addFiles(wrapper, [file("a.jpg", "image/jpeg")]);
    await wrapper.get("[data-testid=attestation]").setValue(true);

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    const body = api.submitMaterial.mock.calls[0][0] as FormData;
    expect(body.get("submitter_name")).toBe("Abdullah");
    expect(body.get("submitter_role")).toBe("artist_family");
    expect(body.get("city")).toBe("Saihat");
    expect(body.get("attestation")).toBe("1");
    expect(body.getAll("files[]")).toHaveLength(1);

    const confirmation = wrapper.get("[data-testid=confirmation]");
    expect(confirmation.text()).toContain("Reference number 41");
    expect(confirmation.text()).toContain("Inspection and numbering");
    expect(wrapper.find("form").exists()).toBe(false);
  });

  it("shows the server's specific reason for a rejected upload, not a generic failure", async () => {
    api.submitMaterial.mockRejectedValueOnce(
      new ApiError("validation", "invalid", { status: 422, fieldErrors: { "files.0": ["The files.0 field must be a file of type: image/jpeg."] } }),
    );
    const wrapper = await mountPage();
    await fillRequired(wrapper);
    await wrapper.get("[data-testid=attestation]").setValue(true);

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.get("[data-testid=banner-error]").text()).toContain("must be a file of type");
    expect(wrapper.find("[data-testid=confirmation]").exists()).toBe(false);
  });

  it("tells the visitor plainly when they have been rate limited", async () => {
    api.submitMaterial.mockRejectedValueOnce(new ApiError("throttled", "Too many", { status: 429 }));
    const wrapper = await mountPage();
    await fillRequired(wrapper);
    await wrapper.get("[data-testid=attestation]").setValue(true);

    await wrapper.get("form").trigger("submit");
    await flushPromises();

    expect(wrapper.get("[data-testid=banner-error]").text()).toContain("in an hour");
  });
});
