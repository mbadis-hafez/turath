import { beforeEach, describe, expect, it, vi } from "vitest";

import ArtistLetterBar from "@/components/artists/ArtistLetterBar.vue";
import { mountWithPlugins } from "@/test/utils";

describe("ArtistLetterBar", () => {
  beforeEach(() => {
    vi.stubGlobal("matchMedia", () => ({ matches: false }));
  });

  it("enables letters returned by the API and disables the rest", () => {
    const wrapper = mountWithPlugins(ArtistLetterBar, {
      props: { letters: { ar: ["أ", "ب"], en: ["A"] } },
      locale: "ar",
    });

    expect(wrapper.find('[data-testid="letter-أ"]').attributes("disabled")).toBeUndefined();
    expect(wrapper.find('[data-testid="letter-ب"]').attributes("disabled")).toBeUndefined();
    expect(wrapper.find('[data-testid="letter-ت"]').attributes("disabled")).toBe("");
  });

  it("emits a click with the selected letter for enabled letters", async () => {
    const wrapper = mountWithPlugins(ArtistLetterBar, {
      props: { letters: { ar: ["أ"], en: [] } },
      locale: "ar",
    });

    await wrapper.find('[data-testid="letter-أ"]').trigger("click");
    expect(wrapper.emitted("click")?.[0]).toEqual(["أ"]);

    await wrapper.find('[data-testid="letter-ب"]').trigger("click");
    expect(wrapper.emitted("click")).toHaveLength(1);
  });

  it("uses the English alphabet in the en locale", () => {
    const wrapper = mountWithPlugins(ArtistLetterBar, {
      props: { letters: { ar: [], en: ["A", "Z"] } },
      locale: "en",
    });

    expect(wrapper.find('[data-testid="letter-A"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="letter-Z"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="letter-أ"]').exists()).toBe(false);
  });
});
