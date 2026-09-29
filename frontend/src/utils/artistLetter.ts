export const ARABIC_ALPHABET: string[] = [
  "أ",
  "ب",
  "ت",
  "ث",
  "ج",
  "ح",
  "خ",
  "د",
  "ذ",
  "ر",
  "ز",
  "س",
  "ش",
  "ص",
  "ض",
  "ط",
  "ظ",
  "ع",
  "غ",
  "ف",
  "ق",
  "ك",
  "ل",
  "م",
  "ن",
  "ه",
  "و",
  "ي",
];

export const ENGLISH_ALPHABET: string[] = [
  "A",
  "B",
  "C",
  "D",
  "E",
  "F",
  "G",
  "H",
  "I",
  "J",
  "K",
  "L",
  "M",
  "N",
  "O",
  "P",
  "Q",
  "R",
  "S",
  "T",
  "U",
  "V",
  "W",
  "X",
  "Y",
  "Z",
];

/**
 * Return the directory first-letter for a name, matching the backend rule.
 *
 * Arabic: alef forms (أ إ آ ا ٱ) are normalized to أ.
 * English: the first ASCII letter is uppercased.
 */
export function artistLetter(
  name: string | null | undefined,
  locale: "ar" | "en",
): string | null {
  if (!name || name.trim() === "") return null;

  const first = name.trim().charAt(0);

  if (locale === "ar") {
    return first.replace(/[إآاٱ]/g, "أ");
  }

  const upper = first.toUpperCase();
  return /[A-Z]/.test(upper) ? upper : null;
}
