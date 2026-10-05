<?php
require __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION["doctor_account_id"])) { header("Location: doctor_login.php"); exit; }

$account_id  = $_SESSION["doctor_account_id"];
$doctor_name = $_SESSION["doctor_name"] ?? '';

// Handle password update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: text/plain; charset=utf-8');

    $current   = $_POST['current_password'] ?? '';
    $new       = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        echo 'All fields are required.';
        $conn->close();
        exit;
    }

    // Pull the current stored password
    $stmt = $conn->prepare("SELECT password FROM doctor_accounts WHERE id = ?");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        echo 'Account not found. Please log in again.';
        $stmt->close(); $conn->close(); exit;
    }
    $stmt->bind_result($stored);
    $stmt->fetch();
    $stmt->close();

    $isHash = preg_match('/^\$2[aby]\$/', $stored) === 1;
    $okCurrent = $isHash ? password_verify($current, $stored) : hash_equals($stored, $current);

    if (!$okCurrent) {
        echo 'Current password is incorrect.';
        $conn->close();
        exit;
    }

    if ($new !== $confirm) {
        echo 'New password and confirmation do not match.';
        $conn->close();
        exit;
    }
    if (strlen($new) < 8) {
        echo 'New password must be at least 8 characters long.';
        $conn->close();
        exit;
    }
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        echo 'New password must contain both letters and numbers.';
        $conn->close();
        exit;
    }
    if (($isHash && password_verify($new, $stored)) || (!$isHash && hash_equals($stored, $new))) {
        echo 'New password must be different from the current password.';
        $conn->close();
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $conn->prepare("UPDATE doctor_accounts SET password = ? WHERE id = ?");
    $upd->bind_param("si", $hash, $account_id);
    if ($upd->execute()) {
        echo 'success';
    } else {
        echo 'Failed to update password. Please try again.';
    }
    $upd->close();
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#16a34a">
    <link rel="icon" href="images/Lustre.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <title>Change Password - LustreMDC Doctor</title>
</head>
<body>
    <div class="admin-login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h1>Change Password</h1>
                <p>Update your account password.</p>
            </div>

            <div id="errorMessage" class="error-message"></div>

            <form id="changePwForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                    <small style="display:block;color:#94a3b8;margin-top:.3rem;font-size:.78rem">At least 8 characters with letters and numbers.</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" minlength="8">
                </div>

                <button type="submit" class="btn-primary btn-full">Update Password</button>
            </form>

            <div class="back-link">
                <a href="doctor_dashboard.php">&larr; Back to Dashboard</a>
            </div>
        </div>
    </div>

    <div id="loadingScreen" class="loading-screen">
        <div class="spinner"></div>
        <p>Processing...</p>
    </div>
    <div id="toast" class="toast"></div>

    <script>
        function showLoading() { document.getElementById('loadingScreen').classList.add('active'); }
        function hideLoading() { document.getElementById('loadingScreen').classList.remove('active'); }
        function showToast(msg, type) {
            var t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'toast show' + (type ? ' ' + type : '');
            setTimeout(function(){ t.className = 'toast'; }, 3200);
        }
        function showError(message) {
            var e = document.getElementById('errorMessage');
            e.textContent = message;
            e.classList.add('show');
        }
        function hideError() { document.getElementById('errorMessage').classList.remove('show'); }

        /* -- Smooth Transitions -- */
        (function(){
            const s = document.createElement('style');
            s.textContent = `
                @keyframes __meIn  {from{opacity:0;transform:translateY(12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
                @keyframes __meFade {from{opacity:0}to{opacity:1}}
                @keyframes __meFadeOut {from{opacity:1}to{opacity:0}}
                body{animation:__meFade .4s ease both}
                body.__leaving{animation:__meFadeOut .22s ease forwards;pointer-events:none}
                .login-card{animation:__meIn .5s cubic-bezier(.34,1.2,.64,1) both}
                button:not(:disabled):active{transform:scale(.965) !important}
            `;
            document.head.appendChild(s);
            document.addEventListener('click', function(e){
                const link = e.target.closest('a[href]');
                if(!link) return;
                const href = link.getAttribute('href');
                if(!href || href.startsWith('#') || href.startsWith('javascript') || href.startsWith('http')) return;
                e.preventDefault();
                document.body.classList.add('__leaving');
                setTimeout(()=>{ window.location.href = href; }, 215);
            });
        })();

        document.getElementById('changePwForm').addEventListener('submit', function(e){
            e.preventDefault();
            hideError();
            var fd = new FormData(this);
            var np = document.getElementById('new_password').value;
            var cp = document.getElementById('confirm_password').value;
            if (np !== cp) { showError('New password and confirmation do not match.'); return; }
            if (np.length < 8) { showError('New password must be at least 8 characters long.'); return; }
            showLoading();
            fetch('doctor_change_password.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(result => {
                hideLoading();
                if (result.trim() === 'success') {
                    showToast('Password updated successfully!', 'success');
                    document.body.classList.add('__leaving');
                    setTimeout(() => { window.location.href = 'doctor_dashboard.php'; }, 500);
                } else {
                    showError(result.trim());
                }
            })
            .catch(err => { hideLoading(); showError('Error: ' + err.message); });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
