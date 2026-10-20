<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/api.php';

// Dual-format endpoint: React staff portals send X-Lustre-Api: 1 and receive
// {ok:...} JSON; the legacy PHP pages keep the plain-text "success"/"error: ...".
$wantJson = (isset($_SERVER['HTTP_X_LUSTRE_API']) && $_SERVER['HTTP_X_LUSTRE_API'] === '1');

function upd_out($msg, $isError = false, $status = 400) {
    global $wantJson;
    if ($wantJson) {
        if ($isError) { api_fail(preg_replace('/^error:\s*/i', '', $msg), $status); }
        api_ok();
    }
    echo $msg;
    exit;
}

if (!isset($_SESSION["admin_id"])) {
    if ($wantJson) { api_fail('Unauthorized', 401); }
    echo "error: Unauthorized access";
    exit;
}

require_once __DIR__ . '/../src/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = api_input();
    if (empty($in)) { $in = $_POST; }

    $appointment_id = isset($in['appointment_id']) ? intval($in['appointment_id']) : 0;

    if (empty($appointment_id)) {
        upd_out("error: Invalid appointment ID", true);
    }

    if (isset($in['status'])) {
        $status = $in['status'];
        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            upd_out("error: Invalid status", true);
        }

        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $appointment_id);

        if ($stmt->execute()) {
            require_once __DIR__ . '/appointment_mailer.php';
            send_appointment_email($conn, $appointment_id, $status);
            $stmt->close();
            upd_out("success");
        } else {
            $stmt->close();
            upd_out("error: Failed to update status", true);
        }
    }
    elseif (isset($in['payment_status'])) {
        $payment_status = $in['payment_status'];
        if (!in_array($payment_status, ['pending', 'paid'])) {
            upd_out("error: Invalid payment status", true);
        }

        $stmt = $conn->prepare("UPDATE appointments SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $payment_status, $appointment_id);

        if ($stmt->execute()) {
            $stmt->close();
            upd_out("success");
        } else {
            $stmt->close();
            upd_out("error: Failed to update payment status", true);
        }
    }
    else {
        upd_out("error: No update parameter provided", true);
    }
}
?>
