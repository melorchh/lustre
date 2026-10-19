<?php
/* Auto-generated unique identifiers for clients (patients) and appointments.
   Codes look like CL-000123 / APT-000123 and are assigned by a BEFORE INSERT
   trigger, so every insert path (registration, walk-ins, admin/patient
   booking) gets one without touching the call sites.
   ensure_unique_ids() upgrades an existing database in place: adds the
   columns, backfills from the existing primary keys, then installs the
   trigger. Safe to call on every request (idempotent, static-guarded). */

function ensure_unique_ids($conn)
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $conn->query("ALTER TABLE patients ADD COLUMN IF NOT EXISTS unique_code VARCHAR(32)");
    $conn->query("ALTER TABLE appointments ADD COLUMN IF NOT EXISTS unique_code VARCHAR(32)");

    $conn->query("UPDATE patients SET unique_code = 'CL-' || LPAD(id::text, 6, '0') WHERE unique_code IS NULL OR unique_code = ''");
    $conn->query("UPDATE appointments SET unique_code = 'APT-' || LPAD(id::text, 6, '0') WHERE unique_code IS NULL OR unique_code = ''");

    $conn->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_patients_unique_code ON patients(unique_code)");
    $conn->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_appointments_unique_code ON appointments(unique_code)");

    $conn->query("CREATE OR REPLACE FUNCTION assign_unique_code() RETURNS trigger AS $fn$
BEGIN
    IF NEW.unique_code IS NULL OR NEW.unique_code = '' THEN
        NEW.unique_code := TG_ARGV[0] || '-' || LPAD(nextval(TG_ARGV[1])::text, 6, '0');
    END IF;
    RETURN NEW;
END;
$fn$ LANGUAGE plpgsql");

    $conn->query("DROP TRIGGER IF EXISTS trg_patients_unique_code ON patients");
    $conn->query("CREATE TRIGGER trg_patients_unique_code BEFORE INSERT ON patients FOR EACH ROW EXECUTE FUNCTION assign_unique_code('CL', 'patients_id_seq')");

    $conn->query("DROP TRIGGER IF EXISTS trg_appointments_unique_code ON appointments");
    $conn->query("CREATE TRIGGER trg_appointments_unique_code BEFORE INSERT ON appointments FOR EACH ROW EXECUTE FUNCTION assign_unique_code('APT', 'appointments_id_seq')");

    // Vitals can belong to a specific appointment (consultation).
    $conn->query("ALTER TABLE vitals ADD COLUMN IF NOT EXISTS appointment_id INTEGER");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_vitals_appointment ON vitals(appointment_id)");

    // Older databases created the vitals -> appointments FK without
    // ON DELETE SET NULL (NO ACTION), which makes deleting a doctor fail
    // with "vitals_appointment_id_fkey ... is still referenced" because
    // doctor deletion cascades to appointments. Re-create the FK so the
    // vitals record is preserved and only the appointment link is cleared.
    // Runs only when the constraint is missing or wrong (cheap catalog check).
    $needs_fk_fix = true;
    $fk = $conn->query("SELECT confdeltype FROM pg_constraint WHERE conname = 'vitals_appointment_id_fkey' AND conrelid = 'vitals'::regclass AND confrelid = 'appointments'::regclass");
    if ($fk && ($frow = $fk->fetch_assoc())) {
        $needs_fk_fix = ($frow['confdeltype'] !== 's'); // 's' = SET NULL
    }
    if ($needs_fk_fix) {
        $conn->query("ALTER TABLE vitals DROP CONSTRAINT IF EXISTS vitals_appointment_id_fkey");
        $conn->query("ALTER TABLE vitals ADD CONSTRAINT vitals_appointment_id_fkey FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL");
    }
}
