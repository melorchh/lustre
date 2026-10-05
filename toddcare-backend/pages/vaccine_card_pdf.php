<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["patient_id"])) {
    http_response_code(403);
    exit('Unauthorized');
}

$patient_id = (int)$_SESSION["patient_id"];
$vacc_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($vacc_id <= 0) {
    http_response_code(400);
    exit('Invalid vaccination request');
}

require_once __DIR__ . '/../src/db.php';

$stmt = $conn->prepare("
    SELECT
        v.vaccine_name,
        v.dose_label,
        v.administered_date,
        v.next_due_date,
        v.notes,
        v.created_at,
        p.name     AS patient_name,
        p.age,
        p.patient_type
    FROM vaccinations v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.id = ? AND v.patient_id = ?
");
$stmt->bind_param("ii", $vacc_id, $patient_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    http_response_code(404);
    exit('Vaccination record not found');
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

$w_pt = 842;
$h_pt = 595;
$M    = 48;

$stream = '';

function rectfill(&$s, $x, $yTop, $w, $h, $c) {
    $x1 = $x;
    $y1 = 595 - $yTop;
    $x2 = $x + $w;
    $y2 = 595 - ($yTop + $h);
    $s .= "$c[0] $c[1] $c[2] rg $x1 $y2 $w " . ($y1 - $y2) . " re f\n";
}

function pdftxt(&$s, $x, $yTop, $str, $font, $size, $c) {
    $s .= "BT /F$font $size Tf $x " . (595 - $yTop) . " Td $c[0] $c[1] $c[2] rg (" . esc($str) . ") Tj ET\n";
}

function pdftxt_multi(&$s, $x, $yTop, $str, $font, $size, $c, $maxWidth) {
    $words = explode(' ', $str);
    $line  = '';
    $y     = $yTop;
    foreach ($words as $word) {
        $test = $line ? $line . ' ' . $word : $word;
        $tw = strlen($test) * $size * 0.52;
        if ($tw > $maxWidth && $line !== '') {
            pdftxt($s, $x, $y, $line, $font, $size, $c);
            $y  += $size + 4;
            $line = $word;
        } else {
            $line = $test;
        }
    }
    if ($line !== '') {
        pdftxt($s, $x, $y, $line, $font, $size, $c);
        $y += $size + 4;
    }
    return $y;
}

$blue      = rgb('#1e6fbf');
$blue_dark = rgb('#1a5a9e');
$blue_deep = rgb('#143d6b');
$amber     = rgb('#f59e0b');
$amber_deep= rgb('#92400e');
$gray_mid  = rgb('#52604f');
$gray_light= rgb('#93a08f');
$line_gray = rgb('#e5e9e4');
$white     = [1, 1, 1];

$v_date = date('F j, Y', strtotime($row['administered_date']));
$next   = $row['next_due_date'] ? date('F j, Y', strtotime($row['next_due_date'])) : null;

$Y = $M;

// â”€â”€ Blue header band â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$header_h = 64;
rectfill($stream, 0, 0, $w_pt, $header_h, $blue_dark);
pdftxt($stream, $M, 40, 'LUSTRE MDC CLINICS & DIAGNOSTICS', 'B', 13, $white);
pdftxt($stream, $M, 26, 'VACCINATION CARD', 'R', 9, [0.85, 0.93, 0.98]);
pdftxt($stream, $w_pt - $M, 40, 'VACC-' . str_pad($vacc_id, 6, '0', STR_PAD_LEFT), 'B', 13, $white);
pdftxt($stream, $w_pt - $M, 26, 'RECORD NO.', 'R', 6.5, [0.85, 0.93, 0.98]);
$Y = $header_h + 16;

// â”€â”€ Patient row â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
pdftxt($stream, $M, $Y, 'PATIENT', 'B', 7, $gray_light);
$Y += 14;
pdftxt($stream, $M, $Y, $row['patient_name'], 'B', 15, $blue_deep);
$Y += 8;
pdftxt($stream, $M, $Y, 'Present this card at the clinic for your vaccination records.', 'R', 8.5, $gray_mid);
$Y += 16;
rectfill($stream, $M, $Y, $w_pt - 2 * $M, 1, $line_gray);
$Y += 16;

// â”€â”€ Details (4 columns in landscape) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$labelW = ($w_pt - 2 * $M) / 4;
function fieldcol(&$s, $x, $y, $label, $value, $color = null) {
    pdftxt($s, $x, $y, strtoupper($label), 'B', 6.5, rgb('#93a08f'));
    pdftxt($s, $x, $y + 13, $value, 'R', 12, $color ?? rgb('#143d6b'));
}
$colX = $M;
fieldcol($stream, $colX, $Y, 'Vaccine', $row['vaccine_name'], $blue_deep);
$colX += $labelW;
fieldcol($stream, $colX, $Y, 'Dose', $row['dose_label']);
$colX += $labelW;
fieldcol($stream, $colX, $Y, 'Date Administered', $v_date);
$colX += $labelW;
fieldcol($stream, $colX, $Y, 'Patient Type', ucfirst($row['patient_type'] ?? 'Adult'));
$Y += 30;

// â”€â”€ Next dose schedule panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$panelH = $next ? 64 : 52;
$panelBg = $next ? rgb('#fffbeb') : rgb('#f0fdf4');
$panelBorder = $next ? rgb('#fde68a') : rgb('#bbf7d0');
rectfill($stream, $M, $Y, $w_pt - 2 * $M, $panelH, $panelBg);
rectfill($stream, $M, $Y, $w_pt - 2 * $M, 1, $panelBorder);

if ($next) {
    pdftxt($stream, $M + 14, $Y + 16, 'NEXT DOSE SCHEDULE', 'B', 9, $amber_deep);
    pdftxt($stream, $M + 14, $Y + 32, $next, 'B', 13, rgb('#78350f'));
    pdftxt($stream, $M + 14, $Y + 48, 'Please visit the clinic on or before this date for your next dose.', 'R', 8, $gray_mid);
} else {
    pdftxt($stream, $M + 14, $Y + 16, 'SCHEDULE COMPLETE', 'B', 9, rgb('#166534'));
    pdftxt($stream, $M + 14, $Y + 34, 'No upcoming dose is scheduled for this vaccine series.', 'R', 10, $gray_mid);
}
$Y = $Y + $panelH + 18;

// â”€â”€ Footer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
rectfill($stream, $M, $Y, $w_pt - 2 * $M, 1, $line_gray);
$Y += 13;
pdftxt($stream, $M, $Y, 'This document serves as proof of vaccination administered by LustreMDC.', 'R', 8.5, $gray_light);
$Y += 12;
pdftxt($stream, $M, $Y, 'Bring this card and a valid ID to the clinic front desk on your next visit.', 'R', 8.5, $gray_light);
$Y += 16;
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

$vacc_name_safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $row['vaccine_name']);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Vaccine_Card_' . $vacc_name_safe . '_' . $vacc_id . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
