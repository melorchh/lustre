export const SERVICES_BY_SPECIALTY: Record<string, string[]> = {
  'Obstetrics & Gynecology': [
    'Prenatal Consultation',
    'General OB-GYN Consultation',
    'Transvaginal Ultrasound',
    'Pelvic / Abdominal Ultrasound',
    'Pap Smear',
    'Pregnancy Test (Serum hCG)',
    'Prenatal Panel',
    'Pelvic Examination',
    'Family Planning Consultation',
  ],
  'High-Risk Pregnancy': [
    'High-Risk Prenatal Consultation',
    'Fetal Anomaly Scan',
    'Non-Stress Test (NST)',
    'Amniotic Fluid Index (AFI)',
    'Glucose Tolerance Test (GTT)',
    'Transvaginal Ultrasound',
    'Prenatal Panel',
    'Preterm Labor Evaluation',
  ],
  'Gynecologic Surgery': [
    'Surgical Consultation',
    'Colposcopy',
    'Hysteroscopy',
    'Endometrial / Cervical Biopsy',
    'Minor Gynecologic Procedure',
    'Post-operative Follow-up',
  ],
  'Reproductive Health': [
    'Fertility Consultation',
    'Hormonal Panel (FSH, LH, Estrogen)',
    'Transvaginal Ultrasound',
    'Semen Analysis',
    'IUI / IVF Consultation',
    'Sexually Transmitted Infection (STI) Panel',
    'Pregnancy Test (Serum hCG)',
  ],
};

export const DEFAULT_SERVICES = [
  'General Consultation',
  'Follow-up Consultation',
  'Diagnostic Procedure',
];

export const OTHER_SERVICE = 'Other (specify below)';

const normalize = (s: string) =>
  s
    .toLowerCase()
    .replace(/[^a-z0-9]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

type Entry = { key: string; aliases?: string[] };
const ENTRIES: Entry[] = [
  {
    key: 'Obstetrics & Gynecology',
    aliases: ['OB-GYN', 'OB/GYN', 'OB GYN', 'Ob-Gyne', 'Obstetrics and Gynecology', 'ObGyn'],
  },
  {
    key: 'High-Risk Pregnancy',
    aliases: ['High Risk Pregnancy', 'High Risk OB', 'Maternal Fetal Medicine', 'MFM'],
  },
  {
    key: 'Gynecologic Surgery',
    aliases: ['Gynecological Surgery', 'Gyn Surgery', 'Gynaecologic Surgery', 'Minimally Invasive Gynecology'],
  },
  {
    key: 'Reproductive Health',
    aliases: ['Reproductive Medicine', 'Infertility', 'Fertility Medicine'],
  },
];

function lookup(specialty: string): string | null {
  const key = normalize(specialty);
  if (!key) return null;
  for (const entry of ENTRIES) {
    if (normalize(entry.key) === key) return entry.key;
  }
  for (const entry of ENTRIES) {
    if ((entry.aliases ?? []).some((a) => normalize(a) === key)) return entry.key;
  }
  return null;
}

export function servicesFor(specialty: string | null | undefined): string[] {
  const key = lookup(specialty ?? '');
  return key ? SERVICES_BY_SPECIALTY[key] : DEFAULT_SERVICES;
}
