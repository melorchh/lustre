# LUSTRE MEDICAL DIAGNOSTIC CLINIC — System Flowchart

## Full System Overview

```mermaid
flowchart TD
    START([User Visits Clinic Website]) --> LANDING[Landing Page<br>landing.php]

    LANDING --> REG{New Patient?}
    LANDING --> LOGIN[Sign In<br>login.php / admin_login.php]

    REG --> REGISTER[Register Account<br>register.php]
    REGISTER --> OTP[Email OTP Verification<br>register_otp_send.php]
    OTP --> REG_OK{OTP Valid?}
    REG_OK -- No --> OTP
    REG_OK -- Yes --> LOGIN

    LOGIN --> AUTH{Valid Credentials?}
    AUTH -- No --> LOGIN
    AUTH -- Yes --> ROLE{User Role}

    %% ==================== PATIENT FLOW ====================
    ROLE -- Patient --> PDASH[Dashboard<br>dashboard.php]

    PDASH --> BOOK[Book Appointment<br>book_appointment.php]
    BOOK --> PICK[Dates & Times<br>get_available_dates / get_available_times]
    PICK --> CONFIRM{Confirm Booking}
    CONFIRM -- Yes --> SLIP[Appointment Confirmation<br>appointment_confirmation.php / PDF]
    CONFIRM -- No --> BOOK

    PDASH --> MYAPPT[My Appointments<br>my_appointments.php]
    MYAPPT --> ACTION1{Action}
    ACTION1 -- Reschedule --> RESCHED[Reschedule<br>reschedule_appointment.php]
    ACTION1 -- Cancel --> CANCEL[Cancel<br>cancel_appointment.php]

    PDASH --> LABREQ[Request Lab Test<br>lab_request.php]
    LABREQ --> LABSTATUS[View Lab Results<br>patient_lab_action.php]

    %% ==================== ADMIN FLOW ====================
    ROLE -- Admin --> ADASH[Admin Dashboard<br>admin_dashboard.php]

    ADASH --> APPT[Manage Appointments<br>admin_appointments.php]
    APPT --> APPTACT{Action}
    APPTACT -- Update --> UPD[Update Appointment<br>admin_update_appointment.php]
    APPTACT -- Walk-in --> WALK[Add Walk-in<br>admin_walkin_action.php]
    APPTACT -- Approve --> OKAPPT

    ADASH --> PATIENTS[Manage Patients<br>admin_patients.php]
    ADASH --> DOCS[Manage Doctors<br>admin_doctors.php]
    DOCS --> DOCACT[Doctor Actions<br>admin_doctor_actions.php]

    ADASH --> SCHED[Manage Schedules<br>admin_schedules.php]
    SCHED --> SCHEDACTS[Schedule Actions<br>admin_schedule_action.php]

    ADASH --> VITALS[Manage Vitals<br>admin_vitals.php]
    VITALS --> VITACT[Vitals Actions<br>admin_vitals_action.php]

    ADASH --> LAB[Laboratory<br>admin_laboratory.php]
    LAB --> LABACT[Lab Actions<br>lab_action.php]
    LABACT --> LABRESULT[Lab Result PDF<br>lab_result_pdf.php]

    ADASH --> HIST[Patient History<br>admin_history.php]
    ADASH --> REPORTS[Reports<br>admin_reports.php]

    %% ==================== SHARED HELPERS ====================
    SLIP --> NOTIF[Email Notifications<br>appointment_mailer.php / send_reminders.php]
    ADASH --> LOGOUT_ADMIN[Sign Out<br>admin_logout.php]
    PDASH --> LOGOUT_PAT[Sign Out<br>logout.php]

    %% Styles
    classDef patient fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px;
    classDef admin fill:#e3f2fd,stroke:#1565c0,stroke-width:2px;
    classDef dec fill:#fff3e0,stroke:#ef6c00,stroke-width:2px;
    classDef term fill:#f3e5f5,stroke:#6a1b9a,stroke-width:2px;

    class START,LOGIN,REGISTER,OTP,PDASH,BOOK,SLIP,MYAPPT,LABREQ,LABSTATUS patient;
    class ADASH,APPT,PATIENTS,DOCS,SCHED,VITALS,LAB,HIST,REPORTS admin;
    class REG,REG_OK,AUTH,ROLE,CONFIRM,ACTION1,APPTACT dec;
    class START term;
```

## How to view this flowchart

This file uses **Mermaid**. To render it:

- **VS Code**: install the "Markdown Preview Mermaid Support" extension, then open the preview.
- **Online**: paste the `mermaid` block into https://mermaid.live
- **GitHub**: markdown `.md` files with mermaid blocks render automatically.

---

## Flow Summary

1. **Entry** — User lands on `landing.php`, either signs in or registers (with email OTP).
2. **Role split** — After login, the system routes patients to `dashboard.php` and admins to `admin_dashboard.php`.
3. **Patient** — Books/reschedules/cancels appointments, views confirmation slips, requests lab tests and views results.
4. **Admin** — Manages appointments (incl. walk-ins), patients, doctors, schedules, vitals, the lab, patient history, and generates reports.
5. **Notifications** — Email confirmations/reminders are sent via `appointment_mailer.php` / `send_reminders.php`.
