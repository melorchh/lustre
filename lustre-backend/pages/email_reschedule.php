<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/appointment_reschedule_tokens.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$token_row = find_appointment_reschedule_token($conn, $token);
$appointment = null;
if ($token_row) {
    $stmt = $conn->prepare("
        SELECT a.id, a.doctor_id, a.appointment_date, a.appointment_time, a.status,
               p.name AS patient_name, d.name AS doctor_name, d.specialty
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN doctors d ON d.id = a.doctor_id
        WHERE a.id = ? AND a.status IN ('pending', 'confirmed', 'cancelled')
    ");
    $stmt->bind_param('i', $token_row['appointment_id']);
    if (!$stmt->execute()) {
        error_log('Unable to load appointment for reschedule link: ' . $stmt->error);
        http_response_code(500);
        $stmt->close();
        $conn->close();
        exit('We could not load this appointment right now. Please try again later.');
    }
    $res = $stmt->get_result();
    $appointment = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
$conn->close();

if (!$appointment) {
    http_response_code(410);
}
$patient_name = $appointment ? $appointment['patient_name'] : '';
$doctor_name = $appointment ? 'Dr. ' . $appointment['doctor_name'] . ' (' . $appointment['specialty'] . ')' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#16a34a">
  <title>Choose a New Appointment Time — LustreMDC</title>
  <style>
    *{box-sizing:border-box}body{margin:0;padding:24px;background:#f3f7f4;color:#17231b;font:16px/1.5 Arial,sans-serif}
    main{max-width:560px;margin:6vh auto;padding:32px;background:#fff;border:1px solid #dce8df;border-radius:16px;box-shadow:0 12px 36px #173a2414}
    h1{margin:0 0 8px;font-size:1.6rem}p{color:#536158}.field{margin:20px 0}
    label{display:block;margin-bottom:7px;font-weight:700}select,button{width:100%;min-height:48px;padding:10px 12px;border:1px solid #c8d6cc;border-radius:8px;font:inherit;background:#fff}
    button{border:0;background:#16834a;color:#fff;font-weight:700;cursor:pointer}button:disabled,select:disabled{opacity:.6;cursor:not-allowed}
    #message{min-height:24px;color:#a52a2a}.details{padding:14px;background:#f3f7f4;border-radius:8px}
  </style>
</head>
<body>
<main>
  <?php if (!$appointment): ?>
    <h1>This rescheduling link is no longer available</h1>
    <p>It may have expired or already been used. Please contact the clinic for help choosing a new appointment time.</p>
  <?php else: ?>
    <h1>Choose a new appointment time</h1>
    <p>Hello <?= htmlspecialchars($patient_name, ENT_QUOTES, 'UTF-8') ?>. Choose an available time below. Your appointment will be sent for clinic confirmation.</p>
    <div class="details">
      <strong><?= htmlspecialchars($doctor_name, ENT_QUOTES, 'UTF-8') ?></strong><br>
      Current appointment: <?= htmlspecialchars(date('F j, Y', strtotime($appointment['appointment_date'])), ENT_QUOTES, 'UTF-8') ?>
      at <?= htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <form id="rescheduleForm">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <div class="field">
        <label for="date">Available date</label>
        <select id="date" name="date" required disabled><option value="">Loading dates…</option></select>
      </div>
      <div class="field">
        <label for="time">Available time</label>
        <select id="time" name="time" required disabled><option value="">Select a date first</option></select>
      </div>
      <p id="message" role="status" aria-live="polite"></p>
      <button id="submitButton" type="submit" disabled>Request this appointment time</button>
    </form>
    <script>
      (function () {
        var token = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var dateSelect = document.getElementById('date');
        var timeSelect = document.getElementById('time');
        var message = document.getElementById('message');
        var submitButton = document.getElementById('submitButton');
        var form = document.getElementById('rescheduleForm');
        function setMessage(text) { message.textContent = text; }
        function loadTimes() {
          var date = dateSelect.value;
          timeSelect.disabled = true;
          timeSelect.innerHTML = '<option value="">Loading times…</option>';
          submitButton.disabled = true;
          if (!date) return;
          fetch('get_available_times.php?email_token=' + encodeURIComponent(token) + '&date=' + encodeURIComponent(date), {cache:'no-store'})
            .then(function (response) {
              if (!response.ok) throw new Error('Could not load available times.');
              return response.json();
            })
            .then(function (times) {
              timeSelect.innerHTML = '<option value="">Select a time</option>';
              times.forEach(function (time) {
                var option = document.createElement('option');
                option.value = time;
                option.textContent = time;
                timeSelect.appendChild(option);
              });
              timeSelect.disabled = false;
              if (!times.length) setMessage('No times remain for that date. Please choose another date.');
            })
            .catch(function (error) { setMessage(error.message); });
        }
        fetch('get_available_dates.php?email_token=' + encodeURIComponent(token), {cache:'no-store'})
          .then(function (response) {
            if (!response.ok) throw new Error('This link is no longer valid. Please contact the clinic.');
            return response.json();
          })
          .then(function (dates) {
            dateSelect.innerHTML = '<option value="">Select a date</option>';
            dates.forEach(function (item) {
              var option = document.createElement('option');
              option.value = item.date;
              option.textContent = item.display;
              dateSelect.appendChild(option);
            });
            dateSelect.disabled = false;
            if (!dates.length) setMessage('No available dates were found. Please contact the clinic.');
          })
          .catch(function (error) { setMessage(error.message); });
        dateSelect.addEventListener('change', loadTimes);
        timeSelect.addEventListener('change', function () { submitButton.disabled = !timeSelect.value; });
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          submitButton.disabled = true;
          setMessage('Submitting your request…');
          fetch('reschedule_appointment.php', {method:'POST', body:new FormData(form)})
            .then(function (response) { return response.text(); })
            .then(function (text) {
              var result;
              try { result = JSON.parse(text); } catch (error) { throw new Error(text || 'Could not submit your request.'); }
              if (!result.success) throw new Error(result.message || 'Could not submit your request.');
              form.innerHTML = '<h2>Reschedule requested</h2><p>Your request is pending clinic confirmation. A confirmation email will be sent when it is approved.</p>';
            })
            .catch(function (error) { submitButton.disabled = !timeSelect.value; setMessage(error.message); });
        });
      })();
    </script>
  <?php endif; ?>
</main>
</body>
</html>
