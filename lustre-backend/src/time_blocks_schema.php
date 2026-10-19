<?php
// Runtime migration for doctor_time_blocks week-range support:
// block_type 'week' covers a date range (block_date .. end_date).
// Safe to call from every endpoint that reads or writes time blocks.

function ensure_time_blocks_schema($conn)
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $conn->query("ALTER TABLE doctor_time_blocks ADD COLUMN IF NOT EXISTS end_date DATE NULL");
    $ensured = true;
}
