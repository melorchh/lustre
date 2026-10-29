<?php
require __DIR__ . '/../src/session.php';

// If already logged in, redirect to the doctor dashboard
if (isset($_SESSION["doctor_id"])) {
    header("Location: doctor_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d9488">
    <link rel="icon" href="images/Lustre.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <link rel="stylesheet" href="doctor.css?v=<?= filemtime(__DIR__ . '/../../doctor.css') ?>">
    <link rel="stylesheet" href="doctor_portal.css?v=<?= filemtime(__DIR__ . '/../../doctor_portal.css') ?>">
    <title>Doctor Login - LustreMDC</title>
</head>
<body class="doctor-page">
    <div class="admin-login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>
                <h1>Doctor Portal</h1>
                <p>LustreMDC Clinics &amp; Diagnostics</p>
            </div>

            <div id="errorMessage" class="error-message"></div>

            <form id="doctorLoginForm" novalidate>
                <div id="loginStep1">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input type="password" id="password" name="password" required autocomplete="current-password">
                            <button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password" tabindex="-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                    </div>

                    <label style="display:flex;align-items:flex-start;gap:0.5rem;margin:0.25rem 0 1rem;font-size:0.9rem;color:#475569;line-height:1.4;">
                        <input type="checkbox" id="tncCheck" style="margin-top:0.25rem;">
                        <span>I agree to the Terms &amp; Conditions</span>
                    </label>

                    <button type="submit" class="btn-primary btn-full">Send OTP</button>
                </div>
            </form>

            <div id="otpStep" style="display:none;">
                <p style="color:#475569;font-size:0.93rem;margin-bottom:1rem;">An OTP has been sent to the email associated with this account. Please enter the 6-digit code to continue.</p>
                <div class="form-group">
                    <label for="doctorOtp">OTP Code</label>
                    <input type="text" id="doctorOtp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit code" required>
                </div>
                <button type="button" id="verifyOtpBtn" class="btn-primary btn-full">Verify &amp; Login</button>
                <button type="button" id="resendOtpBtn" class="btn-secondary btn-full" style="margin-top:0.6rem;">Resend OTP</button>
            </div>

            <div class="back-link">
                <a href="index.php">&larr; Back to Patient Portal</a>
            </div>
        </div>
    </div>

    <div id="loadingScreen" class="loading-screen">
        <div class="spinner"></div>
        <p>Processing...</p>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loadingScreen').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingScreen').classList.remove('active');
        }

        /* -- Smooth Transitions -- */
        (function(){
          const s=document.createElement('style');
          s.textContent=`
            @keyframes __meIn  {from{opacity:0;transform:translateY(12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
            @keyframes __meOut {from{opacity:1}to{opacity:0;transform:translateY(-6px)}}
            @keyframes __meFade {from{opacity:0}to{opacity:1}}
            @keyframes __meFadeOut {from{opacity:1}to{opacity:0}}
            body{animation:__meFade .4s ease both}
            body.__leaving{animation:__meFadeOut .22s ease forwards;pointer-events:none}
            .login-card{animation:__meIn .5s cubic-bezier(.34,1.2,.64,1) both}
            button:not(:disabled):active{transform:scale(.965) !important}
            .form-group input{transition:border-color .2s,box-shadow .2s}
            .form-group input:focus{box-shadow:0 0 0 4px rgba(13,148,136,.16)}
          `;
          document.head.appendChild(s);
          document.addEventListener('click',function(e){
            const link=e.target.closest('a[href]');
            if(!link)return;
            const href=link.getAttribute('href');
            if(!href||href.startsWith('#')||href.startsWith('javascript')||href.startsWith('http'))return;
            e.preventDefault();
            document.body.classList.add('__leaving');
            setTimeout(()=>{window.location.href=href;},215);
          });
        })();

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.classList.add('show');
        }

        function hideError() {
            document.getElementById('errorMessage').classList.remove('show');
        }

        function togglePw(btn) {
            const input = btn.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            const eye = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
            const eyeOff = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>';
            btn.innerHTML = show ? eyeOff : eye;
        }

        let pendingDoctorLogin = { username: '', password: '' };

        document.getElementById('doctorLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideError();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const tnc = document.getElementById('tncCheck').checked;

            if (!username || !password) {
                showError('Username and password are required');
                return;
            }
            if (!tnc) {
                showError('Please agree to the Terms & Conditions');
                return;
            }

            pendingDoctorLogin.username = username;
            pendingDoctorLogin.password = password;

            showLoading();

            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            formData.append('action', 'request_otp');

            fetch('doctor_login_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(result => {
                hideLoading();
                if (result.trim() === 'otp_sent') {
                    document.getElementById('loginStep1').style.display = 'none';
                    document.getElementById('otpStep').style.display = 'block';
                    document.getElementById('doctorOtp').value = '';
                    document.getElementById('doctorOtp').focus();
                } else {
                    showError(result);
                }
            })
            .catch(err => {
                hideLoading();
                showError('Error: ' + err.message);
            });
        });

        document.getElementById('verifyOtpBtn').addEventListener('click', function() {
            hideError();
            const otp = document.getElementById('doctorOtp').value.trim();
            if (!/^\d{6}$/.test(otp)) {
                showError('Please enter the 6-digit OTP code');
                return;
            }
            showLoading();

            const formData = new FormData();
            formData.append('username', pendingDoctorLogin.username);
            formData.append('password', pendingDoctorLogin.password);
            formData.append('action', 'verify_otp');
            formData.append('otp', otp);

            fetch('doctor_login_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(result => {
                hideLoading();
                if (result.trim() === 'success') {
                    try{localStorage.setItem('lustreAdminFlash',JSON.stringify({m:'Welcome back!',t:'success'}));}catch(e){}
                    document.body.classList.add('__leaving');
                    setTimeout(() => { window.location.href = 'doctor_dashboard.php'; }, 400);
                } else {
                    showError(result);
                }
            })
            .catch(err => {
                hideLoading();
                showError('Error: ' + err.message);
            });
        });

        document.getElementById('resendOtpBtn').addEventListener('click', function() {
            hideError();
            showLoading();

            const formData = new FormData();
            formData.append('username', pendingDoctorLogin.username);
            formData.append('password', pendingDoctorLogin.password);
            formData.append('action', 'request_otp');

            fetch('doctor_login_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(result => {
                hideLoading();
                if (result.trim() === 'otp_sent') {
                    showError('A new OTP has been sent.');
                    document.getElementById('errorMessage').style.background = '#f0fdf4';
                    document.getElementById('errorMessage').style.color = '#16a34a';
                    document.getElementById('errorMessage').style.borderColor = '#86efac';
                    document.getElementById('errorMessage').classList.add('show');
                } else {
                    showError(result);
                }
            })
            .catch(err => {
                hideLoading();
                showError('Error: ' + err.message);
            });
        });
    </script>
</body>
</html>