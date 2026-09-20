import { listAdminArtists } from "@/api/artistCuration";
import { searchHolders } from "@/api/artworkCuration";
import type { PickerOption } from "@/components/curation/EntityPicker.vue";
import type { Localized } from "@/types/artistCuration";

export const labelOf = (name: Localized): string => name.ar ?? name.en ?? "";

export const searchArtistOptions = async (q: string): Promise<PickerOption[]> =>
  (await listAdminArtists({ q })).data.map((a) => ({ id: a.id, label: labelOf(a.name) }));

export const searchHolderOptions = async (q: string): Promise<PickerOption[]> =>
  (await searchHolders(q)).map((h) => ({ id: h.id, label: labelOf(h.name) }));
