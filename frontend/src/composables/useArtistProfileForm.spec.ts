import { describe, expect, it } from "vitest";

import { useArtistProfileForm } from "@/composables/useArtistProfileForm";

describe("useArtistProfileForm contacts", () => {
  it("keeps a contact that only has an address and trims values", () => {
    const { form, contactsPayload } = useArtistProfileForm();
    form.contacts = [
      { name: null, role_note: null, email: null, phone: null, address: "  Al Rawand Street, Saihat " },
      { name: null, role_note: null, email: null, phone: null, address: " " },
    ];

    expect(contactsPayload()).toEqual([{ name: null, role_note: null, email: null, phone: null, address: "Al Rawand Street, Saihat" }]);
  });
});
