/**
 * Static demo data for the home page design.
 * Placeholder content — replaced by real API responses later.
 */

export interface LocalizedText {
  ar: string;
  en: string;
}

export interface HomeStat {
  key: "materials" | "artists" | "artworks" | "sources";
  value: number;
}

export interface HomeTheme {
  title: LocalizedText;
  note: LocalizedText;
  count: number;
}

export interface ArchiveFeature {
  kind: "image";
  kindLabel: LocalizedText;
  meta: LocalizedText;
  title: LocalizedText;
  body: LocalizedText;
  collection: LocalizedText;
  recordId: string;
}

export interface RecentItem {
  kind: "article" | "image";
  meta: LocalizedText;
  title: LocalizedText;
  credit: LocalizedText;
}

export interface SpotlightArtist {
  name: LocalizedText;
  items: number;
}

export interface CityEntry {
  name: LocalizedText;
  items: number;
}

export const homeStats: HomeStat[] = [
  { key: "materials", value: 12 },
  { key: "artists", value: 12 },
  { key: "artworks", value: 8 },
  { key: "sources", value: 6 },
];

export const popularSearches: LocalizedText[] = [
  { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" },
  { ar: "معرض الرواد 1978", en: "Pioneers Exhibition 1978" },
  { ar: "جريدة اليوم", en: "Al-Youm Newspaper" },
  { ar: "بينالي دكا الرابع", en: "4th Dhaka Biennale" },
  { ar: "الحروفية", en: "Hurufiyya" },
];

export const homeThemes: HomeTheme[] = [
  {
    title: {
      ar: "نشأة الحركة التشكيلية",
      en: "The Establishment of the Modern Art Movement in Saudi Arabia",
    },
    note: {
      ar: "المعارض الأولى، الجمعيات، ومعاهد التربية الفنية في الستينيات والسبعينيات.",
      en: "The first exhibitions, associations, and art-education institutes of the 1960s and 1970s.",
    },
    count: 4,
  },
  {
    title: { ar: "الحياة الاجتماعية", en: "Social Life" },
    note: {
      ar: "المدينة والبيت والسوق كما رآها الرسامون، ومقالات الصحافة عنها.",
      en: "The city, the home, and the market as painters saw them, and what the press wrote about them.",
    },
    count: 3,
  },
  {
    title: { ar: "أحلام ورموز", en: "Dreams and Symbols" },
    note: {
      ar: "التجريد والحروفية والرمز في أعمال الثمانينيات.",
      en: "Abstraction, hurufiyya, and symbolism in the works of the 1980s.",
    },
    count: 2,
  },
  {
    title: { ar: "الطبيعة والمكان", en: "Nature & Landscape" },
    note: {
      ar: "النخيل والصحراء وطيبة، بين الرسم الميداني والكتاب المطبوع.",
      en: "Palms, desert, and Tayba — between plein-air painting and the printed book.",
    },
    count: 1,
  },
];

export const archiveFeature: ArchiveFeature = {
  kind: "image",
  kindLabel: { ar: "صورة", en: "Image" },
  meta: { ar: "1978 · الأحساء", en: "1978 · Al-Ahsa" },
  title: {
    ar: "افتتاح أول معرض جماعي لرواد الفن التشكيلي السعودي",
    en: "Opening of the first group exhibition of Saudi art pioneers",
  },
  body: {
    ar: "صورة الفنان أحمد المغلوث مع الأمير سلمان بن عبدالعزيز، أمير الرياض آنذاك، في افتتاح أول معرض جماعي لرواد الفن التشكيلي السعودي بفندق الإنتركونتيننتال بالرياض عام 1978.",
    en: "Artist Ahmad Almaghlout with Prince Salman bin Abdulaziz, then governor of Riyadh, at the opening of the first group exhibition of Saudi art pioneers at the InterContinental Hotel, Riyadh, 1978.",
  },
  collection: {
    ar: "مجموعة أحمد المغلوث",
    en: "Ahmad Almaghlout collection",
  },
  recordId: "AR036_Almaghlout_SV061224_ARCIMG003",
};

export const recentItems: RecentItem[] = [
  {
    kind: "article",
    meta: { ar: "1989 · عكاظ", en: "1989 · Okaz" },
    title: {
      ar: "طيبة في عيون فنان تشكيلي",
      en: "Tayba through the eyes of a painter",
    },
    credit: {
      ar: "فؤاد مغربل · المدينة المنورة",
      en: "Fouad Mougharbel · Medina",
    },
  },
  {
    kind: "article",
    meta: { ar: "1988 · عكاظ", en: "1988 · Okaz" },
    title: {
      ar: "اختتام معرض المقتنيات التشكيلية الحادي عشر",
      en: "11th collectors' exhibition closes",
    },
    credit: { ar: "يوسف جاها · مكة المكرمة", en: "Yousef Jaha · Makkah" },
  },
  {
    kind: "image",
    meta: { ar: "1967 · الدرعية", en: "1967 · Diriyah" },
    title: { ar: "رحلة فنية إلى الدرعية", en: "An artistic journey to Diriyah" },
    credit: {
      ar: "فؤاد مغربل · معهد التربية الفنية",
      en: "Fouad Mougharbel · Art Education Institute",
    },
  },
  {
    kind: "article",
    meta: { ar: "1985 · جريدة العراق", en: "1985 · Al-Iraq Newspaper" },
    title: {
      ar: "الفنان السعودي أحمد المغلوث: تنويعات الإبداع ضمن الوحدة",
      en: "Ahmad Almaghlout: variations of creativity within unity",
    },
    credit: { ar: "أحمد المغلوث · الأحساء", en: "Ahmad Almaghlout · Al-Ahsa" },
  },
];

export const spotlightArtists: SpotlightArtist[] = [
  { name: { ar: "أحمد المغلوث", en: "Ahmad Almaghlout" }, items: 8 },
  { name: { ar: "ناصر الموسى", en: "Nasser Al Mousa" }, items: 9 },
  { name: { ar: "بكر شيخون", en: "Baker Sheikhoun" }, items: 4 },
  { name: { ar: "فؤاد مغربل", en: "Fouad Mougharbel" }, items: 4 },
  { name: { ar: "يوسف جاها", en: "Yousef Jaha" }, items: 4 },
  { name: { ar: "محمد الصندل", en: "Mohammed Alsandal" }, items: 2 },
];

export const cityEntries: CityEntry[] = [
  { name: { ar: "الأحساء والهفوف", en: "Al-Ahsa and Hofuf" }, items: 6 },
  { name: { ar: "الرياض", en: "Riyadh" }, items: 9 },
  { name: { ar: "جدة", en: "Jeddah" }, items: 4 },
  { name: { ar: "المدينة المنورة", en: "Medina" }, items: 4 },
  { name: { ar: "مكة المكرمة", en: "Makkah" }, items: 4 },
  {
    name: { ar: "جمعية الفنون والثقافة بالأحساء", en: "Al-Ahsa Arts & Culture Society" },
    items: 4,
  },
];

export const lastUpdate = {
  batch: "BAM_260526",
  date: { ar: "26 مايو 2026", en: "26 May 2026" },
};
