<?php
require __DIR__ . '/../src/session.php';

if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    exit('Unauthorized');
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : '';
$day = isset($_GET['day']) ? $_GET['day'] : '';

$allowed_tabs = ['appointments', 'labtests', 'vitals', 'vaccinations'];
if (!in_array($tab, $allowed_tabs, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
    http_response_code(400);
    exit('Invalid request');
}

require_once __DIR__ . '/../src/db.php';

$apptCols = [
    ['label' => 'Patient',       'w' => 150],
    ['label' => 'Doctor',        'w' => 120],
    ['label' => 'Date / Time',   'w' => 95],
    ['label' => 'Status',        'w' => 70],
    ['label' => 'Payment',       'w' => 80],
];
$labCols = [
    ['label' => 'Patient',       'w' => 130],
    ['label' => 'Doctor',        'w' => 95],
    ['label' => 'Test Type',     'w' => 105],
    ['label' => 'Priority',      'w' => 60],
    ['label' => 'Status',        'w' => 60],
    ['label' => 'Scheduled',     'w' => 65],
];
$vitalCols = [
    ['label' => 'Patient',       'w' => 130],
    ['label' => 'Visit Date',    'w' => 70],
    ['label' => 'Weight',        'w' => 55],
    ['label' => 'Blood Pressure','w' => 60],
    ['label' => 'Heart Rate',    'w' => 55],
    ['label' => 'Notes',         'w' => 145],
];
$vaccCols = [
    ['label' => 'Patient',       'w' => 120],
    ['label' => 'Vaccine',       'w' => 110],
    ['label' => 'Dose',          'w' => 55],
    ['label' => 'Administered',  'w' => 75],
    ['label' => 'Next Due',      'w' => 75],
    ['label' => 'Notes',         'w' => 80],
];

$titles = [
    'appointments'  => 'Appointments',
    'labtests'      => 'Lab Tests',
    'vitals'        => 'Vitals',
    'vaccinations'  => 'Vaccinations',
];

$cols = null;
$rows = [];

switch ($tab) {
    case 'appointments':
        $cols = $apptCols;
        $q = $conn->query("
            SELECT a.appointment_date, a.appointment_time, a.status, a.payment_status, a.updated_at,
                   p.name AS patient_name, p.email AS patient_email,
                   d.name AS doctor_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN doctors  d ON a.doctor_id  = d.id
            WHERE a.status IN ('completed','cancelled')
            ORDER BY a.appointment_time ASC
        ");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                if (date('Y-m-d', strtotime($r['updated_at'])) !== $day) continue;
                $rows[] = [
                    $r['patient_name'],
                    'Dr. ' . $r['doctor_name'],
                    date('M j, Y', strtotime($r['appointment_date'])) . "\n" . date('g:i A', strtotime($r['appointment_time'])),
                    ucfirst($r['status']),
                    ucfirst($r['payment_status']),
                ];
            }
        }
        break;

    case 'labtests':
        $cols = $labCols;
        $q = $conn->query("
            SELECT lt.test_type, lt.priority, lt.status, lt.scheduled_date, lt.updated_at,
                   p.name AS patient_name, d.name AS doctor_name
            FROM lab_tests lt
            JOIN patients p ON lt.patient_id = p.id
            JOIN doctors  d ON lt.doctor_id  = d.id
            WHERE lt.status IN ('completed','cancelled')
            ORDER BY lt.test_type ASC
        ");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                if (date('Y-m-d', strtotime($r['updated_at'])) !== $day) continue;
                $rows[] = [
                    $r['patient_name'],
                    'Dr. ' . $r['doctor_name'],
                    $r['test_type'],
                    strtoupper($r['priority']),
                    ucfirst($r['status']),
                    $r['scheduled_date'] ? date('M j, Y', strtotime($r['scheduled_date'])) : '-',
                ];
            }
        }
        break;

    case 'vitals':
        $cols = $vitalCols;
        $q = $conn->query("
            SELECT v.visit_date, v.weight_kg, v.blood_pressure, v.heart_rate, v.fundal_height, v.notes, v.created_at,
                   p.name AS patient_name
            FROM vitals v
            JOIN patients p ON v.patient_id = p.id
            ORDER BY v.visit_date DESC
        ");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                if (date('Y-m-d', strtotime($r['created_at'])) !== $day) continue;
                $rows[] = [
                    $r['patient_name'],
                    date('M j, Y', strtotime($r['visit_date'])),
                    $r['weight_kg'] ? $r['weight_kg'] . ' kg' : '-',
                    $r['blood_pressure'] ?: '-',
                    $r['heart_rate'] ? $r['heart_rate'] . ' bpm' : '-',
                    $r['notes'] ?: '-',
                ];
            }
        }
        break;

    case 'vaccinations':
        $cols = $vaccCols;
        $q = $conn->query("
            SELECT va.vaccine_name, va.dose_label, va.administered_date, va.next_due_date, va.notes, va.created_at,
                   p.name AS patient_name
            FROM vaccinations va
            JOIN patients p ON va.patient_id = p.id
            ORDER BY va.administered_date DESC
        ");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                if (date('Y-m-d', strtotime($r['created_at'])) !== $day) continue;
                $rows[] = [
                    $r['patient_name'],
                    $r['vaccine_name'],
                    $r['dose_label'],
                    date('M j, Y', strtotime($r['administered_date'])),
                    $r['next_due_date'] ? date('M j, Y', strtotime($r['next_due_date'])) : 'Complete',
                    $r['notes'] ?: '-',
                ];
            }
        }
        break;
}

$conn->close();

/*
 * â”€â”€ Minimal pure-PHP multi-page PDF writer (no external library) â”€â”€
 */

$pageW = 595;
$pageH = 842;
$M  = 40;
$pad = 6;

function pdc($hex) {
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
}

function E($s) {
    $s = (string)$s;
    $s = str_replace(["\r", "\n"], ' ', $s);
    $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    if ($conv !== false) $s = $conv;
    $s = preg_replace('/[^\x20-\x7E\x{A0}-\x{FF}]/u', '', $s);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function rectfill(&$s, $x, $yTop, $w, $h, $c) {
    $s .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
        $c[0], $c[1], $c[2], $x, pageH_i() - $yTop - $h, $w, $h);
}

function pdftxt(&$s, $x, $yTop, $str, $font, $size, $c) {
    $s .= sprintf("BT /F%s %s Tf %.2f %.2f Td %.3f %.3f %.3f rg (%s) Tj ET\n",
        $font, $size, $x, pageH_i() - $yTop, $c[0], $c[1], $c[2], E((string)$str));
}

// Right-aligned text: PDF text is drawn leftward from the given x, so a
// string placed at the right margin would run off the page edge. Estimate
// the rendered width (over-estimate so the text never overflows the edge)
// and start it far enough to the left instead.
function pdftxt_r(&$s, $rightX, $yTop, $str, $font, $size, $c) {
    $w   = strlen((string)$str) * $size * 0.70;
    $x   = max($GLOBALS['M'], $rightX - $w);
    pdftxt($s, $x, $yTop, $str, $font, $size, $c);
}

function pageH_i() { global $pageH; return $pageH; }

function wrapLines($str, $size, $maxW) {
    $str = (string)$str;
    $words = explode(' ', $str);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $test = $line ? $line . ' ' . $word : $word;
        if (strlen($test) * $size * 0.55 > $maxW && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $test;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines ?: [''];
}

$green     = pdc('#16a34a');
$green_dark= pdc('#15803d');
$green_deep= pdc('#14532d');
$head_bg   = pdc('#f0fdf4');
$line_gray = pdc('#e2e8f0');
$gray_mid  = pdc('#475569');
$gray_soft = pdc('#94a3b8');
$night     = pdc('#0f172a');

$pages  = [];
$stream = '';
$Y = 0;

function newPage(&$pages, &$stream, $cols, $title, $dayLabel) {
    global $pageH, $M, $pad, $rows, $Y;
    global $green_dark, $green_deep, $gray_mid, $gray_soft, $night, $line_gray, $head_bg;
    if (trim($stream) !== '') $pages[] = $stream;
    $stream = '';
    $Y = 0;

    $headerH = 52;
    rectfill($stream, 0, 0, 595, $headerH, $green_dark);
    pdftxt($stream, $M, 32, 'LUSTRE MDC CLINICS & DIAGNOSTICS', 'B', 12, [0.9, 0.97, 0.93]);
    pdftxt($stream, $M, 20, 'ARCHIVE ' . strtoupper($title) . '  Â·  ' . $dayLabel, 'B', 8.5, [0.85, 0.94, 0.9]);
    pdftxt_r($stream, 595 - $M, 32, 'DAILY RECORD', 'B', 12, [0.9, 0.97, 0.93]);
    pdftxt_r($stream, 595 - $M, 20, 'GENERATED ' . strtoupper(date('M j, Y g:i A')), 'R', 7, [0.85, 0.94, 0.9]);
    $Y = $headerH + 24;

    pdftxt($stream, $M, $Y, strtoupper($title) . ' RECORDS FOR ' . $dayLabel, 'B', 11, $green_deep);
    $Y += 18;
    pdftxt($stream, $M, $Y, 'Record count: ' . rowCount() . '  Â·  Download provided by LustreMDC Admin Archives', 'R', 8, $gray_mid);
    $Y += 16;

    renderColHeader($stream, $cols, $Y);
    $Y += 22;
}

function rowCount() { global $rows; return count($rows); }

function renderColHeader(&$stream, $cols, $Y) {
    global $M, $pad, $green_deep;
    $x = $M;
    rectfill($stream, $M, $Y - 10, 595 - 2 * $M, 20, pdc('#f0fdf4'));
    foreach ($cols as $c) {
        pdftxt($stream, $x + $pad, $Y, strtoupper($c['label']), 'B', 7.5, $green_deep);
        $x += $c['w'];
    }
}

function cellLines($val, $w) {
    global $pad;
    return wrapLines($val, 8.5, $w - 2 * $pad);
}

$dayLabel = date('F j, Y', strtotime($day));
newPage($pages, $stream, $cols, $titles[$tab], $dayLabel);

if (!$rows) {
    pdftxt($stream, $M, $Y + 6, 'No archived records were found for ' . $titles[$tab] . ' on ' . $dayLabel . '.', 'R', 9.5, $gray_mid);
    $Y += 22;
}

$lastBottom = $pageH - $M;
$rowIdx = 0;

foreach ($rows as $row) {
    $maxLines = 1;
    foreach ($row as $i => $val) {
        $n = count(cellLines($val, $cols[$i]['w']));
        if ($n > $maxLines) $maxLines = $n;
    }
    $rowH = $maxLines * 12 + 10;

    if ($Y + $rowH > $lastBottom) {
        newPage($pages, $stream, $cols, $titles[$tab], $dayLabel);
    }

    if ($rowIdx % 2 === 0) rectfill($stream, $M, $Y - 10, 595 - 2 * $M, $rowH, $head_bg);

    $x = $M;
    foreach ($row as $i => $val) {
        $w = $cols[$i]['w'];
        $lines = cellLines($val, $w);
        $ly = $Y;
        foreach ($lines as $ln) {
            pdftxt($stream, $x + $pad, $ly, $ln, 'R', 8.5, $night);
            $ly += 12;
        }
        $x += $w;
    }

    $yBottom = $Y + $rowH - 10;
    $x = $M;
    foreach ($cols as $c) {
        rectfill($stream, $x, $yBottom, $c['w'], 0.6, $line_gray);
        $x += $c['w'];
    }

    $Y += $rowH + 2;
    $rowIdx++;
}

if ($Y + 44 > $lastBottom) {
    newPage($pages, $stream, $cols, $titles[$tab], $dayLabel);
}

rectfill($stream, $M, $Y, 595 - 2 * $M, 0.8, $line_gray);
$Y += 16;
pdftxt($stream, $M, $Y, 'This document was generated automatically from the LustreMDC Admin Archives.', 'R', 8, $gray_soft);
$Y += 12;
pdftxt($stream, $M, $Y, 'LustreMDC Clinics & Diagnostics  -  Generated on ' . date('F j, Y g:i A'), 'B', 8, $gray_mid);

$pages[] = $stream;

/*
 * â”€â”€ Assemble PDF (catalog + pages + fonts + per-page content) â”€â”€
 */
$pdf = "%PDF-1.4\n";
$objs = [];

$objs[] = "<< /Type /Catalog /Pages 2 0 R >>";

$kids = '';
$nKids = count($pages);
for ($i = 0; $i < $nKids; $i++) {
    $kids .= ($i + 3) . ' 0 R ';
}
$objs[] = "<< /Type /Pages /Kids [" . trim($kids) . "] /Count $nKids >>";

$fontF1 = $nKids + 3;
$fontF2 = $nKids + 4;
foreach ($pages as $i => $stream) {
    $objs[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 $fontF1 0 R /F2 $fontF2 0 R >> >> /Contents " . ($nKids + 5 + $i) . " 0 R >>";
}

$objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

foreach ($pages as $stream) {
    $objs[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
}

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

$fname = 'Archive_' . $titles[$tab] . '_' . str_replace('-', '_', $day) . '.pdf';

while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;