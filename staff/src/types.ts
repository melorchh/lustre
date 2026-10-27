// Shared types for the staff portals. The bundle shape is whatever the
// matching api_*_data.php `view` returns under {ok:true}.

export type Role = 'admin' | 'doctor';

export interface Staff {
  role: Role;
  portal: string;
  id: number;
  accountId?: number;
  name: string;
  username: string;
  mustChange: boolean;
  loginUrl: string;
  logoutUrl: string;
  changePasswordUrl: string;
}

export interface InitialData {
  staff: Staff;
  view: string;
  data: Record<string, unknown>;
}

declare global {
  interface Window {
    __LUSTRE__?: InitialData;
  }
}

export interface DoctorRow {
  id: number;
  name: string;
  specialty: string;
  schedule?: string;
  experience?: number;
  test_procedures?: string;
  daily_limit?: number;
  appointment_count?: number;
}

export interface PatientRow {
  id: number;
  unique_code?: string;
  name: string;
  email?: string;
  contact?: string;
  address?: string;
  is_walk_in?: number;
  weight_kg?: number | null;
  height_cm?: number | null;
  patient_type?: string;
  age?: number | null;
  gender?: string | null;
  created_at?: string;
  appointment_count?: number;
  last_appointment?: string | null;
}

export interface AppointmentRow {
  id: number;
  patient_id: number;
  unique_code?: string;
  appointment_date: string;
  appointment_time: string;
  status: string;
  payment_status: string;
  is_urgent?: number;
  reschedule_count?: number;
  updated_at?: string;
  appointment_duration?: number;
  service_requested?: string;
  result?: string | null;
  result_date?: string | null;
  last_rescheduled_at?: string | null;
  vital_count?: number;
  patient_name?: string;
  patient_email?: string;
  patient_address?: string;
  patient_age?: number;
  doctor_name?: string;
  specialty?: string;
}

export interface LabTestRow {
  id: number;
  patient_id: number;
  doctor_id: number;
  appointment_id?: number | null;
  test_type: string;
  priority: string;
  status: string;
  scheduled_date?: string | null;
  scheduled_time?: string | null;
  result?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
  patient_name?: string;
  patient_email?: string;
  doctor_name?: string;
  specialty?: string;
  appointment_date?: string;
  appointment_time?: string;
}

export interface VitalRow {
  id: number;
  patient_id: number;
  appointment_id?: number | null;
  visit_date: string;
  weight_kg?: number | null;
  height_cm?: number | null;
  blood_pressure?: string | null;
  heart_rate?: number | null;
  fundal_height?: number | null;
  notes?: string | null;
  created_at?: string;
  patient_name?: string;
  patient_email?: string;
}

export interface VaccinationRow {
  id: number;
  patient_id?: number;
  vaccine_name: string;
  dose_label: string;
  administered_date: string;
  next_due_date?: string | null;
  notes?: string | null;
  created_at?: string;
  patient_name?: string;
}

export interface WalkinRow {
  id: number;
  arrival_date?: string;
  arrival_time: string;
  status: string;
  test_procedure?: string;
  created_at?: string;
  patient_name?: string;
  patient_type?: string;
  contact?: string;
  patient_id?: number;
  doctor_id?: number;
  doctor_name?: string;
  specialty?: string;
  queue?: number;
  appointment_id?: number | null;
  result?: string;
}

export interface ScheduleRow {
  id: number;
  doctor_id: number;
  schedule_date?: string | null;
  day_of_week: string;
  start_time: string;
  end_time: string;
  is_active: number;
  doctor_name?: string;
  specialty?: string;
}

export interface TimeBlockRow {
  id: number;
  doctor_id: number;
  block_type: string;
  block_date?: string | null;
  end_date?: string | null;
  day_of_week?: string | null;
  start_time: string;
  end_time: string;
  reason?: string | null;
  doctor_name?: string;
}

export interface Stats {
  [key: string]: number | string;
}
