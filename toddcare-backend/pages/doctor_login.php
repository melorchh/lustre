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
    <meta name="theme-color" content="#16a34a">
    <link rel="icon" href="images/Lustre.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/../../admin.css') ?>">
    <link rel="stylesheet" href="doctor.css?v=<?= filemtime(__DIR__ . '/../../doctor.css') ?>">
    <title>Doctor Login - LustreMDC</title>
</head>
<body class="doctor-page">
    <div class="admin-login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>
                <h1>Doctor Portal</h1>
                <p>LustreMDC Clinics &amp; Diagnostics</p>
            </div>

            <div id="errorMessage" class="error-message"></div>

            <form id="doctorLoginForm">
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

                <button type="submit" class="btn-primary btn-full">Login to Dashboard</button>
            </form>

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
            .form-group input:focus{box-shadow:0 0 0 4px rgba(22,163,74,.15)}
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

        document.getElementById('doctorLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideError();
            showLoading();

            const formData = new FormData(this);

            fetch('doctor_login_process.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(result => {
                hideLoading();
                if (result.trim() === 'success') {
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
    </script>
</body>
</html>