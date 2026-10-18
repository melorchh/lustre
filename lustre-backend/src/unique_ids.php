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
}
