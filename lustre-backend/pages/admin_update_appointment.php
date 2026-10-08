<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    echo "error: Unauthorized access";
    exit;
}

require_once __DIR__ . '/../src/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
    
    if (empty($appointment_id)) {
        echo "error: Invalid appointment ID";
        $conn->close();
        exit;
    }

    // Check if updating status or payment
    if (isset($_POST['status'])) {
        $status = $_POST['status'];
        
        // Validate status
        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            echo "error: Invalid status";
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $appointment_id);
        
        if ($stmt->execute()) {
            // Notify the patient by email for meaningful status changes
            require_once __DIR__ . '/appointment_mailer.php';
            $event = $status; // confirmed / cancelled / completed map directly to mailer events
            send_appointment_email($conn, $appointment_id, $event);
            echo "success";
        } else {
            echo "error: Failed to update status";
        }
        
        $stmt->close();
    } 
    elseif (isset($_POST['payment_status'])) {
        $payment_status = $_POST['payment_status'];
        
        // Validate payment status
        if (!in_array($payment_status, ['pending', 'paid'])) {
            echo "error: Invalid payment status";
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare("UPDATE appointments SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $payment_status, $appointment_id);
        
        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error: Failed to update payment status";
        }
        
        $stmt->close();
    } 
    else {
        echo "error: No update parameter provided";
    }

    $conn->close();
}
?>