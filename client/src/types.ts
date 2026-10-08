export interface Doctor {
  id: number;
  name: string;
  specialty: string;
  schedule: string | null;
  experience: number | null;
  test_procedures?: string | null;
}

export interface MedicalDate {
  date: string;
  display: string;
}

export interface Appointment {
  id: number;
  doctor_id: number;
  appointment_date: string;
  appointment_time: string;
  status: string;
  payment_status: string;
  result: string | null;
  result_date: string | null;
  created_at: string;
  doctor_name: string;
  specialty: string;
}

export interface LabTest {
  id: number;
  test_type: string;
  priority: string;
  status: string;
  scheduled_date: string | null;
  result: string | null;
  notes: string | null;
  created_at: string;
  doctor_name: string;
  specialty: string;
}

export interface Vital {
  id: number;
  visit_date: string;
  weight_kg: number | null;
  blood_pressure: string | null;
  heart_rate: number | null;
  fundal_height: number | null;
  notes: string | null;
  created_at: string;
}

export interface Vaccination {
  id: number;
  vaccine_name: string;
  dose_label: string;
  administered_date: string;
  next_due_date: string | null;
  notes: string | null;
  created_at: string;
}

export interface Patient {
  id: number;
  name: string;
  email: string;
  contact: string;
  address: string | null;
  weight_kg: number | null;
  height_cm: number | null;
  patient_type: string | null;
  age: number | null;
  created_at: string;
}

export type PageKey = 'home' | 'book' | 'records' | 'lab' | 'profile';

export interface InitialData {
  patientName: string;
  patient?: Patient;
  doctors?: Doctor[];
  appointments?: Appointment[];
  labTests?: LabTest[];
  vitals?: Vital[];
  vaccinations?: Vaccination[];
  categories?: Record<string, string[]>;
  patientId?: number;
}

declare global {
  interface Window {
    __Lustre__?: InitialData;
  }
}
