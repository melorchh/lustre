<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["patient_id"])) {
    http_response_code(403);
    exit('Unauthorized');
}

$patient_id = (int)$_SESSION["patient_id"];
$appt_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appt_id <= 0) {
    http_response_code(400);
    exit('Invalid appointment request');
}

$conn = null;
include __DIR__ . '/../src/db.php';

$stmt = $conn->prepare("
    SELECT
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.payment_status,
        a.created_at,
        a.service_requested,
        d.name     AS doctor_name,
        d.specialty,
        p.name     AS patient_name
    FROM appointments a
    JOIN doctors  d ON a.doctor_id  = d.id
    JOIN patients p ON a.patient_id = p.id
    WHERE a.id = ? AND a.patient_id = ?
");
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    http_response_code(404);
    exit('Appointment not found');
}

/*
 * â”€â”€ Minimal pure-PHP PDF writer (no external library) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
 */

function esc($s) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$s);
}

function rgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
}

$w_pt = 595;
$h_pt = 842;
$M    = 48;

$stream = '';

function rectfill(&$s, $x, $yTop, $w, $h, $c) {
    $x1 = $x;
    $y1 = 842 - $yTop;
    $x2 = $x + $w;
    $y2 = 842 - ($yTop + $h);
    $s .= "$c[0] $c[1] $c[2] rg $x1 $y2 $w " . ($y1 - $y2) . " re f\n";
}

function pdftxt(&$s, $x, $yTop, $str, $font, $size, $c) {
    $s .= "BT /F$font $size Tf $x " . (842 - $yTop) . " Td $c[0] $c[1] $c[2] rg (" . esc($str) . ") Tj ET\n";
}

$green      = rgb('#16a34a');
$green_dark = rgb('#15803d');
$green_deep = rgb('#14532d');
$gray_mid   = rgb('#52604f');
$gray_light = rgb('#93a08f');
$ink        = rgb('#233026');
$line_gray  = rgb('#e5e9e4');
$white      = [1, 1, 1];

$Y = $M;

// â”€â”€ Green header band â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$header_h = 78;
rectfill($stream, 0, 0, $w_pt, $header_h, $green_dark);
pdftxt($stream, $M, 38, 'LustreMDC Clinics & Diagnostics', 'B', 17, $white);
pdftxt($stream, $M, 24, 'Appointment Confirmation', 'R', 10, [0.85, 0.93, 0.83]);
$Y = $header_h + 14;

// â”€â”€ Title â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
pdftxt($stream, $M, $Y, 'APPOINTMENT CONFIRMATION', 'B', 14, $green_deep);
$Y += 18;
rectfill($stream, $M, $Y, $w_pt - 2 * $M, 1, $line_gray);
$Y += 14;

// â”€â”€ Appointment reference box â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
pdftxt($stream, $M, $Y, 'REFERENCE NO.', 'B', 7, $gray_light);
$Y += 15;
pdftxt($stream, $M, $Y, 'APPT-' . str_pad($appt_id, 6, '0', STR_PAD_LEFT), 'B', 15, $green_dark);
$Y += 10;
pdftxt($stream, $M, $Y, 'Please present this confirmation at the clinic reception.', 'R', 8.5, $gray_mid);
$Y += 22;

// â”€â”€ Details (2 columns) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$colW  = ($w_pt - 2 * $M - 20) / 2;
function fieldrow(&$s, $x, $y, $label, $value, $second = null) {
    pdftxt($s, $x, $y, strtoupper($label), 'B', 7, rgb('#93a08f'));
    pdftxt($s, $x, $y + 13, $value, 'R', 11, rgb('#14532d'));
    if ($second !== null) {
        pdftxt($s, $x + ($GLOBALS['colW'] + 20), $y, strtoupper($second[0]), 'B', 7, rgb('#93a08f'));
        pdftxt($s, $x + ($GLOBALS['colW'] + 20), $y + 13, $second[1], 'R', 11, rgb('#14532d'));
    }
}

$appt_date = date('F j, Y', strtotime($row['appointment_date']));
$appt_time = date('g:i A', strtotime($row['appointment_time']));
$status    = ucfirst($row['status']);
$payment   = ucfirst($row['payment_status']);

fieldrow($stream, $M, $Y, 'Patient', $row['patient_name'], ['Doctor', 'Dr. ' . $row['doctor_name']]);
fieldrow($stream, $M, $Y + 30, 'Specialty', $row['specialty'], ['Reference No.', 'APPT-' . str_pad($appt_id, 6, '0', STR_PAD_LEFT)]);
fieldrow($stream, $M, $Y + 60, 'Date', $appt_date, ['Time', $appt_time]);
fieldrow($stream, $M, $Y + 90, 'Status', $status, ['Payment', $payment . ($payment === 'Paid' ? '' : ' (pay at clinic)')]);
// Service / test requested (full width)
$svc = trim((string)$row['service_requested']);
if ($svc !== '') {
    pdftxt($stream, $M, $Y + 120, 'SERVICE / TEST REQUESTED', 'B', 7, $gray_light);
    pdftxt($stream, $M, $Y + 120 + 13, $svc, 'R', 11, $green_deep);
    $Y += 152;
} else {
    $Y += 128;
}

// â”€â”€ Status / QR-style panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$panelH = 66;
rectfill($stream, $M, $Y, $w_pt - 2 * $M, $panelH, rgb('#f2faf5'));
rectfill($stream, $M, $Y, $w_pt - 2 * $M, 1, rgb('#d1fae5'));

pdftxt($stream, $M + 14, $Y + 18, 'APPOINTMENT STATUS', 'B', 10, $gray_mid);
pdftxt($stream, $M + 14, $Y + 34, $status . ' appointment on ' . $appt_date . ' at ' . $appt_time, 'R', 10.5, $ink);
pdftxt($stream, $M + 14, $Y + 48, 'Booked on ' . date('F j, Y g:i A', strtotime($row['created_at'])), 'R', 8.5, $gray_light);
$Y = $Y + $panelH + 20;

// â”€â”€ Footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
rectfill($stream, $M, $Y, $w_pt - 2 * $M, 1, $line_gray);
$Y += 14;
pdftxt($stream, $M, $Y, 'Appointments are scheduled as 2-hour visits. Please arrive on time.', 'R', 8.5, $gray_light);
$Y += 13;
pdftxt($stream, $M, $Y, 'Present this confirmation card at the clinic front desk upon arrival.', 'R', 8.5, $gray_light);
$Y += 20;
pdftxt($stream, $M, $Y, 'Generated by LustreMDC Clinics & Diagnostics  -  ' . date('F j, Y g:i A'), 'B', 8.5, $gray_mid);

// â”€â”€ Assemble PDF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$pdf  = "%PDF-1.4\n";

$objs = [];
$objs[] = "<< /Type /Catalog /Pages 2 0 R >>";
$objs[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objs[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w_pt $h_pt] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
$objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
$objs[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

$offset  = strlen($pdf);
$offsets = [0];
foreach ($objs as $i => $body) {
    $n = $i + 1;
    $offsets[$n] = $offset;
    $pdf .= "$n 0 obj\n" . $body . "\nendobj\n";
    $offset = strlen($pdf);
}
$xref_pos = $offset;
$count = count($objs) + 1;
$pdf .= "xref\n0 $count\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i < $count; $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref_pos\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Appointment_Confirmation_' . $appt_id . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
