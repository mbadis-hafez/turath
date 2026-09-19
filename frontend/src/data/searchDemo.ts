/**
 * Static demo data for the archive index design.
 * Placeholder content — replaced by real API responses once search ships.
 */

export interface DemoArtist {
  nameAr: string;
  nameEn: string;
  years: string;
  verified: boolean;
}

export interface DemoArtwork {
  title: string;
  artist: string;
  year: number;
  medium: string;
  collection: string;
}

export type DemoMaterialKind = "article" | "photo" | "catalog" | "av";

export interface DemoMaterial {
  kind: DemoMaterialKind;
  restricted: boolean;
  date: string;
  title: string;
  snippet: string;
  meta: string;
}

export interface DemoEvent {
  range: string;
  title: string;
  city: string;
}

export const demoArtists: DemoArtist[] = [
  {
    nameAr: "منيرة الموصلي",
    nameEn: "Mounirah Mosly",
    years: "1952–2019",
    verified: true,
  },
  {
    nameAr: "عبدالحليم رضوي",
    nameEn: "Abdulhalim Radwi",
    years: "1939–2006",
    verified: true,
  },
  {
    nameAr: "صفية بن زقر",
    nameEn: "Safeya Binzagr",
    years: "1940–2024",
    verified: true,
  },
  {
    nameAr: "طه الصبان",
    nameEn: "Taha Al-Sabban",
    years: "– 1948",
    verified: false,
  },
];

export const demoArtworks: DemoArtwork[] = [
  {
    title: "بلا عنوان، من سلسلة الصحراء",
    artist: "منيرة الموصلي",
    year: 1983,
    medium: "أكريليك على ورق",
    collection: "مجموعة خاصة، الرياض",
  },
  {
    title: "النخيل عند المغيب",
    artist: "عبدالحليم رضوي",
    year: 1978,
    medium: "زيت على قماش",
    collection: "مجموعة وزارة الثقافة",
  },
  {
    title: "بيت البلد، حارة المظلوم",
    artist: "صفية بن زقر",
    year: 1968,
    medium: "ألوان مائية على ورق",
    collection: "مجموعة خاصة، جدة",
  },
  {
    title: "الأبواب السبعة",
    artist: "طه الصبان",
    year: 1991,
    medium: "وسائط مختلطة",
    collection: "أرشيف أثر",
  },
];

export const demoMaterials: DemoMaterial[] = [
  {
    kind: "article",
    restricted: false,
    date: "14 مارس 1979",
    title: "«الفن التشكيلي السعودي يفتح موسمه في صالة الرياض»",
    snippet:
      "وضمّ المعرض أعمالاً لعدد من الفنانين الشباب، من بينهم منيرة الموصلي، التي عرضت ثلاث لوحات من سلسلتها الجديدة.",
    meta: "جريدة الرياض · العدد 428 · ص 11",
  },
  {
    kind: "photo",
    restricted: true,
    date: "1979",
    title: "افتتاح معرض الفنانات السعوديات، دار الفنون، جدة",
    snippet:
      "تظهر في الصف الأول منيرة الموصلي إلى جوار صفية بن زقر ومنى القصي.",
    meta: "أرشيف عائللي · نسخة نضية جيلاتينية",
  },
  {
    kind: "catalog",
    restricted: false,
    date: "1984",
    title: "كتالوج بينالي القاهرة الدولي، الجناح السعودي",
    snippet:
      "ترجمة السيرة الذاتية للفنانة منيرة الموصلي في الصفحات 34–36، مع صورتين بالأبيض والأسود.",
    meta: "مطبوع · 128 صفحة · عربي / إنجليزي",
  },
  {
    kind: "av",
    restricted: true,
    date: "1979",
    title: "حديث إذاعي: الفن والهوية في الخليج",
    snippet:
      "مقابلة مدتها 26 دقيقة مع منيرة الموصلي ضمن برنامج «نوافذ» على إذاعة جدة.",
    meta: "شريط كاسيت مرقمن · 26 د 12 ث",
  },
];

export const demoEvents: DemoEvent[] = [
  {
    range: "19 مارس – أبريل 1979",
    title: "معرض الفنانات السعوديات الأول",
    city: "جدة",
  },
  {
    range: "ديسمبر 1984 – فبراير 1985",
    title: "بينالي القاهرة الدولي، الدورة الأولى",
    city: "القاهرة، مصر",
  },
  {
    range: "1988",
    title: "جائزة الأمير فيصل بن فهد للفنون التشكيلية",
    city: "الرياض",
  },
];

export const demoFeaturedArtist = {
  ...demoArtists[0],
  city: "جدة",
  bio: "رشّامة وكاتبة سعودية من جدة، من الجيل المؤسّس للحركة التشكيلية في المملكة. درست في القاهرة ولوس أنجلوس، وشاركت في أول معرض جماعي للفنانات السعوديات عام 1979.",
  works: 32,
  materials: 148,
  events: 9,
};
