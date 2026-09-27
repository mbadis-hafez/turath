import { onBeforeUnmount, ref } from "vue";

import { getEditorDashboard } from "@/api/dashboard";
import type { EditorDashboardData } from "@/types/editorDashboard";

/** Single fetch of the editor workspace payload; aborts on reload/unmount. */
export function useEditorDashboard() {
  const data = ref<EditorDashboardData | null>(null);
  const loading = ref(false);
  const error = ref<unknown>(null);
  let controller: AbortController | null = null;

  async function load(): Promise<void> {
    controller?.abort();
    const self = new AbortController();
    controller = self;
    loading.value = true;
    error.value = null;
    try {
      const response = await getEditorDashboard(self.signal);
      if (controller !== self) return;
      data.value = response.data;
    } catch (err) {
      if (err instanceof DOMException && err.name === "AbortError") return;
      if (controller !== self) return;
      error.value = err;
    } finally {
      if (controller === self) loading.value = false;
    }
  }

  void load();
  onBeforeUnmount(() => controller?.abort());

  return { data, loading, error, retry: load };
}
