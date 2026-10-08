<?php
// Lazily ensure the walk_ins.result column exists (idempotent; safe to call often).
function walkins_ensure_result_column($conn)
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $chk = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'walk_ins' AND column_name = 'result' LIMIT 1");
    if ($chk && $chk->num_rows === 0) {
        @$conn->query("ALTER TABLE walk_ins ADD COLUMN result TEXT");
    }
}
