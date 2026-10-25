<?php
require __DIR__ . '/../src/session.php';
// If already logged in, go to the patient dashboard
if (isset($_SESSION["patient_id"])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0d9488">
<title>LustreMDC &mdash; Clinics & Diagnostics</title>
<script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
<link rel="stylesheet" href="landing.css?v=<?= filemtime(__DIR__ . '/../../landing.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="icon" href="images/Lustre.png" type="image/png">
</head>
<body>

<!-- == NAVBAR == -->
<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="#" class="nav-brand">
      <div class="nav-logo-circle"><img src="images/Lustre.png" alt="Logo" class="logo-img" width="40" height="40"></div>
      <span class="nav-clinic-name">LUSTRE MEDICAL DIAGNOSTIC CLINIC</span>
    </a>
    <div class="nav-links">
      <a href="about.php">About</a>
      <a href="#contact">Contact</a>
    </div>
    <div class="nav-actions">
      <button class="theme-toggle" type="button" aria-label="Toggle light/dark mode" title="Toggle dark mode">
        <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
      </button>
      <button class="nav-btn nav-btn-ghost" onclick="openModal('loginModal')">Sign In</button>
      <button class="nav-btn nav-btn-solid" onclick="openModal('registerModal')">Register Free</button>
    </div>
  </div>
</nav>

<!-- == HERO (slideshow) == -->
<section class="hero" id="hero">
  <div class="slide-layer" data-img="images/1.jpg?v=<?= filemtime(__DIR__ . '/../../images/1.jpg') ?>" style="opacity:1"></div>
  <div class="slide-layer" data-img="images/2.jpg?v=<?= filemtime(__DIR__ . '/../../images/2.jpg') ?>"></div>
  <div class="slide-layer" data-img="images/3.jpg?v=<?= filemtime(__DIR__ . '/../../images/3.jpg') ?>"></div>
  <div class="slide-layer" data-img="images/4.jpg?v=<?= filemtime(__DIR__ . '/../../images/4.jpg') ?>"></div>
  <div class="slide-layer" data-img="images/5.jpg?v=<?= filemtime(__DIR__ . '/../../images/5.jpg') ?>"></div>
  <div class="hero-img-overlay"></div>
  <div class="hero-blob blob-1"></div>
  <div class="hero-blob blob-2"></div>
  <div class="hero-blob blob-3"></div>
  <div class="hero-grid"></div>

  <div class="hero-inner">
    <div class="hero-eyebrow">
      <span class="eyebrow-dot"></span>
      <span>Modern Healthcare, Simplified</span>
    </div>

    <h1 class="hero-title">
      <span>Your Health,</span><br>
      <span class="hero-title-accent">Expertly Cared For</span>
    </h1>

    <p class="hero-subtitle">
      Comprehensive diagnostics, obstetrics &amp; gynecology care, and laboratory services &mdash; all under one roof. Book your appointment in minutes.
    </p>

    <div class="hero-cta">
      <button class="hero-btn hero-btn-primary" onclick="openModal('registerModal')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        <span>Create Account</span>
      </button>
      <button class="hero-btn hero-btn-secondary" onclick="openModal('loginModal')"><span>Sign In</span></button>
    </div>

    <div class="slide-dots" id="slideDots">
      <button class="dot active"></button>
      <button class="dot"></button>
      <button class="dot"></button>
      <button class="dot"></button>
      <button class="dot"></button>
    </div>

    <div class="hero-stat-row">
      <div class="hero-stat"><div class="hero-stat-num">20+</div><div class="hero-stat-label">Lab Tests</div></div>
      <div class="hero-stat"><div class="hero-stat-num">4</div><div class="hero-stat-label">Specialists</div></div>
      <div class="hero-stat"><div class="hero-stat-num">24h</div><div class="hero-stat-label">Result Turnaround</div></div>
      <div class="hero-stat"><div class="hero-stat-num">100%</div><div class="hero-stat-label">Online Booking</div></div>
    </div>
  </div>

  <div class="scroll-hint"><span>Explore Services</span><div class="chevron"></div></div>
</section>

<!-- == TRUST STRIP == -->
<div class="trust">
  <div class="trust-inner">
    <div class="trust-label">Trusted Diagnostic Services</div>
    <div class="trust-items">
      <div class="trust-item"><div class="trust-num">CBC</div><div class="trust-txt">Blood Count</div></div>
      <div class="trust-item"><div class="trust-num">OB-GYN</div><div class="trust-txt">Specialist Care</div></div>
      <div class="trust-item"><div class="trust-num">ECG</div><div class="trust-txt">Heart Screening</div></div>
      <div class="trust-item"><div class="trust-num">X-Ray</div><div class="trust-txt">Imaging</div></div>
      <div class="trust-item"><div class="trust-num">UTZ</div><div class="trust-txt">Ultrasound</div></div>
    </div>
  </div>
</div>

<!-- == SERVICES == -->
<section class="services" id="services">
  <div class="section-header">
    <div class="section-tag">Clinic Services</div>
    <h2 class="section-title">Everything You Need, Right Here</h2>
    <p class="section-sub">Consultations, vaccinations, and medical certificates &mdash; all in one place.</p>
  </div>

  <div class="services-grid">
    <div class="service-card">
      <div class="service-icon icon-blue"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/></svg></div>
      <div class="service-title">Pediatric Consultation</div>
      <div class="service-desc">Checkups, growth monitoring, and treatment for infants, children, and teens.</div>
    </div>

    <div class="service-card">
      <div class="service-icon icon-cyan"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
      <div class="service-title">Adult Consultation</div>
      <div class="service-desc">Consultation for common illnesses, maintenance care, and everyday health concerns.</div>
    </div>

    <div class="service-card">
      <div class="service-icon icon-rose"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M12 14v7"/><path d="M9 18h6"/></svg></div>
      <div class="service-title">OB-Gyn Consultation (by appointment)</div>
      <div class="service-desc">Women's reproductive health, prenatal care, and family planning with our specialists.</div>
    </div>

    <div class="service-card">
      <div class="service-icon icon-purple"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/><path d="m14 4 6 6"/></svg></div>
      <div class="service-title">Pediatric and Adult Vaccination</div>
      <div class="service-desc">Complete immunization schedules for children and routine vaccines for adults.</div>
    </div>

    <div class="service-card">
      <div class="service-icon icon-amber"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg></div>
      <div class="service-title">Medical Certification</div>
      <div class="service-desc">Official medical certificates for school, work, and other official requirements.</div>
    </div>

    <div class="service-card">
      <div class="service-icon icon-green"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg></div>
      <div class="service-title">Physical Examination / Fit to Work Certification</div>
      <div class="service-desc">Pre-employment, annual, and school physical exams with a fit-to-work certificate.</div>
    </div>
  </div>
</section>

<!-- == CONTACT == -->
<section class="page-section" id="contact">
  <div class="section-header">
    <div class="section-tag">Contact</div>
    <h2 class="section-title">Get In Touch</h2>
    <p class="section-sub">Visit or call us &mdash; we are happy to help.</p>
  </div>
  <div class="contact-grid">
    <div class="contact-card">
      <span class="contact-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M12 7.5v5M9.5 10h5"/></svg></span>
      <div class="contact-label">Clinic</div>
      <div class="contact-name">Lustre Medical and Diagnostic Clinic</div>
    </div>
    <div class="contact-card">
      <span class="contact-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></span>
      <div class="contact-label">Address</div>
      <div class="contact-detail">111 Urbano Velasco Avenue, Brgy. Pinagbuhatan, Pasig City</div>
    </div>
    <div class="contact-card">
      <span class="contact-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 16.9v2.8a2 2 0 0 1-2.2 2 19.6 19.6 0 0 1-8.5-3 19.3 19.3 0 0 1-6-6 19.6 19.6 0 0 1-3-8.6 2 2 0 0 1 2-2.2h2.8a2 2 0 0 1 2 1.7c.13 1 .37 1.9.7 2.8a2 2 0 0 1-.49 2.1L8 9.9a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 2.1-.5c.9.33 1.83.57 2.8.7a2 2 0 0 1 1.7 2.1Z"/></svg></span>
      <div class="contact-label">Phone</div>
      <div class="contact-detail"><a href="tel:+639285996038">0928 599 6038</a></div>
    </div>
  </div>
</section>

<!-- == FOOTER == -->
<footer>
  <div class="footer-inner">
    <p class="footer-copy">&copy; <?php echo date('Y'); ?> LustreMDC Clinics &amp; Diagnostics. <span>All rights reserved.</span></p>
  </div>
</footer>

<!-- == LOGIN MODAL == -->
<div class="modal-overlay" id="loginModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
    <div class="modal-title">Welcome</div>
    <div class="modal-sub">Sign in to manage your appointments</div>
    <div class="form-error" id="loginError"></div>
    <form id="loginForm">
      <div class="field"><label>Email or username</label><input type="text" id="loginId" name="email" placeholder="you@example.com or username" required autocomplete="username"><div class="field-hint">Patients enter your email · Staff enter your username</div></div>
      <div class="field"><label>Password</label><div class="pw-field"><input type="password" name="password" id="loginPassword" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="current-password"><button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password" tabindex="-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <button type="submit" class="submit-btn"><span>Sign In</span> &rarr;</button>
    </form>
    <a href="#" class="forgot-link" onclick="openForgotPassword()">Forgot password?</a>
    <div class="modal-switch"><span>Don't have an account?</span> <a href="#" onclick="switchModal('loginModal','registerModal')">Register here</a></div>
  </div>
</div>

<!-- == FORGOT PASSWORD MODAL == -->
<div class="modal-overlay" id="forgotPasswordModal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <button class="modal-close" onclick="closeModal('forgotPasswordModal')">&times;</button>

    <!-- Step 1: email -->
    <div id="fpStepEmail">
      <div class="modal-title">Forgot Password</div>
      <div class="modal-sub">Enter your registered email to receive a verification code.</div>
      <div class="form-error" id="fpEmailError"></div>
      <div class="field"><label>Email address</label><input type="email" id="fpEmailInput" placeholder="you@example.com" required autocomplete="email"></div>
      <button type="button" class="submit-btn" id="fpSendOtpBtn"><span>Send Code</span> &rarr;</button>
      <div class="modal-switch"><span>Remembered it?</span> <a href="#" onclick="switchModal('forgotPasswordModal','loginModal')">Back to login</a></div>
    </div>

    <!-- Step 2: OTP -->
    <div id="fpStepOtp" style="display:none;">
      <div class="modal-title">Enter Verification Code</div>
      <div class="modal-sub">We sent a 6-digit code to your email.</div>
      <div class="otp-hint" id="fpOtpHint"></div>
      <div class="field"><label>Verification code</label><input type="text" id="fpOtpInput" placeholder="Enter 6-digit code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
      <div class="form-error" id="fpOtpError"></div>
      <button type="button" class="submit-btn" id="fpVerifyOtpBtn"><span>Verify</span> &rarr;</button>
      <div class="otp-resend">
        <a href="#" id="fpResendLink">Resend code</a>
        <span class="otp-timer" id="fpOtpTimer"></span>
      </div>
    </div>

    <!-- Step 3: new password -->
    <div id="fpStepReset" style="display:none;">
      <div class="modal-title">Set New Password</div>
      <div class="modal-sub">Choose a strong password for your account.</div>
      <div class="form-error" id="fpResetError"></div>
      <div class="field"><label>New password</label><div class="pw-field"><input type="password" id="fpNewPassword" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="new-password" oninput="checkFpPasswordStrength(this.value)"><button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password" tabindex="-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <div id="fpPwStrengthBox" class="pw-strength-box">
        <div class="pw-strength-info">
          <div id="fpPwStrengthBar" class="pw-strength-bar">
            <div id="fpPwStrengthFill" class="pw-strength-fill"></div>
          </div>
          <span id="fpPwStrengthLabel" class="pw-strength-label"></span>
        </div>
        <div id="fpPwReq1" class="pw-req" data-text="At least 8 characters"><span class="pw-ic" aria-hidden="true">&#10007;</span> At least 8 characters</div>
        <div id="fpPwReq2" class="pw-req" data-text="One uppercase letter (A-Z)"><span class="pw-ic" aria-hidden="true">&#10007;</span> One uppercase letter (A-Z)</div>
        <div id="fpPwReq3" class="pw-req" data-text="One lowercase letter (a-z)"><span class="pw-ic" aria-hidden="true">&#10007;</span> One lowercase letter (a-z)</div>
        <div id="fpPwReq4" class="pw-req" data-text="One number (0-9)"><span class="pw-ic" aria-hidden="true">&#10007;</span> One number (0-9)</div>
      </div>
      <div class="field"><label>Confirm new password</label><div class="pw-field"><input type="password" id="fpConfirmPassword" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required autocomplete="new-password"><button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password" tabindex="-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <button type="button" class="submit-btn" id="fpUpdateBtn">Reset Password</button>
      <div class="modal-switch"><a href="#" onclick="switchModal('forgotPasswordModal','loginModal')">Back to login</a></div>
    </div>
  </div>
</div>

<!-- == REGISTER MODAL == -->
<div id="registerModal" class="register-modal">
    <div class="register-sheet">
        <span class="modal-close" onclick="closeModal('registerModal')">&times;</span>
        <h2>Create New Account</h2>
        <form id="registerForm">
            <input type="text" name="name" placeholder="Full Name" required>
            <select name="gender" class="form-dropdown" id="regGender" required>
                <option value="" disabled selected>Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
            <div class="reg-datepicker" id="regDatepicker">
                <div class="reg-dp-field">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="3" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></svg>
                    <input type="text" class="reg-dp-input" id="regDpInput" placeholder="MM / DD / YYYY" inputmode="numeric" autocomplete="off" aria-label="Birthday">
                    <button type="button" class="reg-dp-trigger" id="regDpTrigger" aria-haspopup="dialog" aria-expanded="false" tabindex="-1" aria-label="Open calendar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="reg-dp-chev"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                </div>
                <input type="hidden" name="birthday" id="regBirthday" required>
            </div>
            <input type="number" name="age" id="regAge" placeholder="Age (auto-filled)" min="0" max="150" required readonly style="background:#f1f5f9;cursor:not-allowed;">

            <!-- -- Philippine Address Fields -- -->
            <div class="address-group">
                <div class="address-group-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> <span>Address</span></div>

                <span class="address-label">Region</span>
<<<<<<< HEAD
                <select name="region_name" id="regRegion" class="form-dropdown" data-searchable required onchange="loadProvinces()">
                    <option value="">Select Region</option>
=======
                <select name="region_name" id="regRegion" class="form-dropdown" data-searchable required>
                    <option value="NCR">National Capital Region (NCR)</option>
                    <option value="CALABARZON">CALABARZON (Region IV-A)</option>
>>>>>>> refinement
                </select>

                <span class="address-label">Province</span>
                <select name="province_name" id="regProvince" class="form-dropdown" data-searchable required onchange="loadMunicipalities()" disabled>
                    <option value="">Select Province</option>
                </select>

                <span class="address-label">Municipality / City</span>
                <select name="municipality_name" id="regMunicipality" class="form-dropdown" required onchange="loadBarangays()" disabled>
                    <option value="">Select Municipality / City</option>
                </select>

                <span class="address-label">Barangay</span>
                <select name="barangay_name" id="regBarangay" class="form-dropdown" required disabled onchange="updateFullAddress()">
                    <option value="">Select Barangay</option>
                </select>

                <span class="address-label">House No. / Street / Subdivision</span>
                <input type="text" name="street" id="regStreet" placeholder="e.g. 123 Rizal St., Sunset Subd." required oninput="updateFullAddress()">
            </div>

            <!-- Hidden field that holds the combined address sent to register.php -->
            <input type="hidden" name="address" id="regFullAddress">
            <!-- -- End Address Fields -- -->

            <input type="email" name="email" id="regEmail" placeholder="Email Address" required>
            <input type="text" name="contact" maxlength="11" placeholder="Contact Number" required>
            <div class="pw-field"><input type="password" name="password" id="regPassword" placeholder="Password" required oninput="checkPasswordStrength(this.value)"><button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password" tabindex="-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
            <div id="pwStrengthBox" class="pw-strength-box">
                <div class="pw-strength-info">
                    <div id="pwStrengthBar" class="pw-strength-bar">
                        <div id="pwStrengthFill" class="pw-strength-fill"></div>
                    </div>
                    <span id="pwStrengthLabel" class="pw-strength-label"></span>
                </div>
                <div id="pwReq1" class="pw-req" data-text="At least 8 characters"><span class="pw-ic" aria-hidden="true">&#10007;</span> At least 8 characters</div>
                <div id="pwReq2" class="pw-req" data-text="One uppercase letter (A-Z)"><span class="pw-ic" aria-hidden="true">&#10007;</span> One uppercase letter (A-Z)</div>
                <div id="pwReq3" class="pw-req" data-text="One lowercase letter (a-z)"><span class="pw-ic" aria-hidden="true">&#10007;</span> One lowercase letter (a-z)</div>
                <div id="pwReq4" class="pw-req" data-text="One number (0-9)"><span class="pw-ic" aria-hidden="true">&#10007;</span> One number (0-9)</div>
            </div>
            <div class="pw-field"><input type="password" name="confirm_password" id="regConfirmPassword" placeholder="Confirm Password" required><button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password" tabindex="-1"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
            <button type="submit" class="btn-primary btn-full">Register</button>
        </form>

        <!-- -- OTP verification step (shown after email is sent) -- -->
        <div id="regOtpPanel" style="display:none;">
            <div class="otp-hint" id="regOtpHint"></div>
            <div class="pw-field">
                <input type="text" id="regOtpInput" placeholder="Enter 6-digit code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
            </div>
            <button type="button" id="regOtpVerify" class="btn-primary btn-full">Verify &amp; Create Account</button>
            <div class="otp-resend">
                <a href="#" id="regResendLink">Resend code</a>
                <span class="otp-timer" id="regOtpTimer"></span>
            </div>
            <div class="form-error" id="regOtpError" style="display:none;margin-top:.6rem;"></div>
        </div>
        <button type="button" id="regBackToForm" class="reg-back-link" style="display:none;">&larr; Back to form</button>
        <p class="modal-footer"><span>Already have an account?</span> <a href="#" onclick="switchModal('registerModal', 'loginModal')">Login here</a></p>
    </div>
</div>

<!-- == CHATBOT WIDGET == -->
<div id="chatbotWidget">
  <div id="chatbotButton">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
  </div>
  <div id="chatbotBox">
    <div class="chatbot-header">
      <div class="chatbot-header-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
      <div class="chatbot-header-info">
        <h3>MedBot &ndash; LustreMDC AI</h3>
        <span>Powered by <span class="groq-badge">Groq AI</span></span>
      </div>
      <span class="chatbot-close">&times;</span>
    </div>
<div class="chatbot-chips">
<span class="chip" data-msg="How do I book an appointment?">Book an appointment</span>
<span class="chip" data-msg="What services does the clinic offer?">Clinic services</span>
<span class="chip" data-msg="Do you accept walk-ins?">Walk-ins?</span>
<span class="chip" data-msg="How do I prepare for a lab test?">Lab test prep</span>
<span class="chip" data-msg="How can I get my lab results?">Get my results</span>
</div>
    <div class="chatbot-messages" id="chatbotMessages">
      <div class="chatbot-message bot-message">
        <div class="msg-avatar"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <p>Hello! I'm MedBot. How can I help you today?</p>
      </div>
    </div>
    <div class="chatbot-input-area">
      <input type="text" id="chatbotInput" placeholder="Type your message...">
      <button id="chatbotSend">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
      </button>
    </div>
    <div class="disclaimer">MedBot provides general info only &mdash; not a substitute for professional medical advice.</div>
  </div>
</div>

<div class="loading-screen" id="loadingScreen"><div class="spinner"></div><p>Processing&#8230;</p></div>
<div id="toast"></div>

<!-- == MOBILE BOTTOM NAV == -->
<nav class="bottom-nav" aria-label="Primary">
  <a class="bottom-nav-item bottom-nav-home active" href="#hero" aria-current="page">
    <span class="bottom-nav-icon">
      <svg class="ic-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.7V21h14V9.7"/><path d="M9.5 21v-6h5v6"/></svg>
      <svg class="ic-fill" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3 3 10.6V21h6v-6h6v6h6V10.6Z"/></svg>
    </span>
    <span>Home</span>
  </a>
  <a class="bottom-nav-item" href="about.php">
    <span class="bottom-nav-icon">
      <svg class="ic-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.2v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg>
      
    </span>
    <span>About</span>
  </a>
  <a class="bottom-nav-item" href="#contact">
    <span class="bottom-nav-icon">
      <svg class="ic-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 16.9v2.8a2 2 0 0 1-2.2 2 19.6 19.6 0 0 1-8.5-3 19.3 19.3 0 0 1-6-6 19.6 19.6 0 0 1-3-8.6 2 2 0 0 1 2-2.2h2.8a2 2 0 0 1 2 1.7c.13 1 .37 1.9.7 2.8a2 2 0 0 1-.49 2.1L8 9.9a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 2.1-.5c.9.33 1.83.57 2.8.7a2 2 0 0 1 1.7 2.1Z"/></svg>
      </span>
    <span>Contact</span>
  </a>
  <button class="bottom-nav-cta" type="button" onclick="openModal('loginModal')">Book Now</button>
</nav>

<script>
/* -- Navbar scroll -- */
window.addEventListener('scroll',()=>document.getElementById('navbar').classList.toggle('scrolled',scrollY>40),{passive:true});

/* -- Slideshow (GPU-friendly crossfade with preloaded images) -- */
const layers=Array.from(document.querySelectorAll('.slide-layer'));
const dots=document.querySelectorAll('.dot');
let slide=0,slideTimer=null;

// Retain precomputed background styles on each layer (cover + center)
layers.forEach(l=>{
  l.style.backgroundImage='url('+l.getAttribute('data-img')+')';
  l.style.backgroundSize='cover';
  l.style.backgroundPosition='center';
});
// Preload slide 1 right away so the first paint has an image;
// defer slides 2-5 until the page is done loading.
const pre1=new Image();pre1.src=layers[0].getAttribute('data-img');
window.addEventListener('load',()=>{
  layers.slice(1).forEach(l=>{const pre=new Image();pre.src=l.getAttribute('data-img');});
});

function goSlide(n){
  layers[slide].classList.remove('active');
  slide=n;
  layers[slide].classList.add('active');
  dots.forEach(d=>d.classList.remove('active'));
  dots[slide].classList.add('active');
}
function nextSlide(){goSlide((slide+1)%layers.length)}

function startSlideshow(){if(!slideTimer)slideTimer=setInterval(nextSlide,4000);}
function stopSlideshow(){clearInterval(slideTimer);slideTimer=null;}

// Don't run full-bleed crossfades nobody is looking at: pause on a hidden tab
// and whenever the hero has scrolled out of view.
document.addEventListener('visibilitychange',()=>{document.hidden?stopSlideshow():startSlideshow();});
if('IntersectionObserver' in window){
  const heroEl=document.querySelector('.hero');
  if(heroEl){
    new IntersectionObserver(entries=>{
      entries[0].isIntersecting?startSlideshow():stopSlideshow();
    },{threshold:0.05}).observe(heroEl);
  }
}
startSlideshow();

dots.forEach((d,i)=>d.addEventListener('click',()=>{stopSlideshow();goSlide(i);startSlideshow();}));

/* -- Modals -- */
function openModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden'}
function closeModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow=''}
function switchModal(a,b){closeModal(a);setTimeout(()=>openModal(b),200)}
document.querySelectorAll('.modal-overlay, .register-modal').forEach(o=>o.addEventListener('click',e=>{if(e.target===o){closeModal(o.id);if(o.id==='registerModal')resetRegisterOtp();if(o.id==='forgotPasswordModal')resetForgotPassword();}}));

/* -- Toast / Loading -- */
function showToast(msg,type=''){const t=document.getElementById('toast');t.textContent=msg;t.className='show'+(type?' '+type:'');setTimeout(()=>t.className='',3200);}
function showLoading(){document.getElementById('loadingScreen').classList.add('active')}
function hideLoading(){document.getElementById('loadingScreen').classList.remove('active')}

/* -- Login (patients by email; admins and doctors by username) -- */
document.getElementById('loginForm').addEventListener('submit',function(e){
  e.preventDefault();const err=document.getElementById('loginError');err.classList.remove('show');showLoading();
  const ident=document.getElementById('loginId').value.trim();
  if(ident.indexOf('@')>=0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ident)){hideLoading();err.textContent = 'That does not look like a valid email address.';err.classList.add('show');return;}
  fetch('login.php',{method:'POST',body:new FormData(this)}).then(r=>r.text()).then(res=>{hideLoading();
    res=res.trim();
    if(res==='success:new'){showToast('Welcome to LustreMDC!','success');setTimeout(()=>location.href='dashboard.php?tour=1',900);}
    else if(res==='success'){showToast('Welcome to LustreMDC!','success');setTimeout(()=>location.href='dashboard.php',900);}
    else if(res==='success:doctor'){showToast('Welcome back, Doctor!','success');setTimeout(()=>location.href='doctor_dashboard.php',900);}
    else if(res==='success:admin'){showToast('Welcome back!','success');setTimeout(()=>location.href='admin_dashboard.php',900);}
    else if(res==='success:admin:change'){showToast('Please set a new password','success');setTimeout(()=>location.href='admin_change_password.php',900);}
    else{err.textContent=res;err.classList.add('show');}
  }).catch(()=>{hideLoading();showToast('Network error','error');});
});

/* -- Deep link: landing.php?signin=1 opens the Sign In sheet -- */
if(new URLSearchParams(location.search).has('signin')){setTimeout(()=>openModal('loginModal'),350);}

/* -- Forgot Password -- */
let fpEmail = '';
let fpTimer = null;

function openForgotPassword(){
  resetForgotPassword();
  closeModal('loginModal');
  setTimeout(()=>openModal('forgotPasswordModal'),200);
}

function resetForgotPassword(){
  const steps=['fpStepEmail','fpStepOtp','fpStepReset'];
  steps.forEach(id=>{const el=document.getElementById(id);if(el)el.style.display='none';});
  const e1=document.getElementById('fpStepEmail');if(e1)e1.style.display='';
  ['fpEmailError','fpOtpError','fpResetError'].forEach(id=>{const el=document.getElementById(id);if(el){el.textContent='';el.classList.remove('show');}});
  const em=document.getElementById('fpEmailInput');if(em)em.value='';
  const oi=document.getElementById('fpOtpInput');if(oi)oi.value='';
  const nw=document.getElementById('fpNewPassword');if(nw)nw.value='';
  const cf=document.getElementById('fpConfirmPassword');if(cf)cf.value='';
  const lb=document.getElementById('fpPwStrengthLabel');if(lb)lb.textContent='';
  const fl=document.getElementById('fpPwStrengthFill');if(fl)fl.style.width='0%';
  [1,2,3,4].forEach(i=>{const r=document.getElementById('fpPwReq'+i);if(r){const base=r.getAttribute('data-text')||r.textContent.trim();r.innerHTML='<span class="pw-ic" aria-hidden="true">&#10007;</span> '+base;r.classList.remove('met');}});
  if(fpTimer)clearInterval(fpTimer);
  const tm=document.getElementById('fpOtpTimer');if(tm)tm.textContent='';
  const rl=document.getElementById('fpResendLink');if(rl){rl.style.pointerEvents='auto';rl.style.opacity='1';}
  fpEmail='';
}

function showFpStep(id){
  ['fpStepEmail','fpStepOtp','fpStepReset'].forEach(s=>{
    document.getElementById(s).style.display = s===id ? '' : 'none';
  });
}

document.getElementById('fpSendOtpBtn').addEventListener('click',function(){
  const btn=this,err=document.getElementById('fpEmailError');
  err.classList.remove('show');
  const email=document.getElementById('fpEmailInput').value.trim();
  if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){err.textContent='Please enter a valid email address';err.classList.add('show');return;}
  btn.disabled=true;showLoading();
  const fd=new FormData();fd.append('email',email);
  fetch('forgot_password_send_otp.php',{method:'POST',body:fd})
    .then(r=>r.text())
    .then(res=>{
      hideLoading();btn.disabled=false;
      if(res.trim()==='success'){
        fpEmail=email;
        document.getElementById('fpOtpHint').textContent='A 6-digit verification code was sent to '+email+'. Enter it below to reset your password.';
        document.getElementById('fpOtpInput').value='';
        document.getElementById('fpOtpError').classList.remove('show');
        showFpStep('fpStepOtp');
        document.getElementById('fpOtpInput').focus();
        startFpTimer(60);
      }else{err.textContent=res;err.classList.add('show');}
    })
    .catch(()=>{hideLoading();btn.disabled=false;showToast('Network error','error');});
});

function startFpTimer(seconds){
  const el=document.getElementById('fpOtpTimer');
  const link=document.getElementById('fpResendLink');
  if(fpTimer)clearInterval(fpTimer);
  const tick=()=>{
    el.textContent=seconds>0?'(resend in '+seconds+'s)':'';
    link.style.pointerEvents=seconds>0?'none':'auto';
    link.style.opacity=seconds>0?'.5':'1';
    if(seconds>0)seconds--;else clearInterval(fpTimer);
  };
  tick();
  fpTimer=setInterval(tick,1000);
}

document.getElementById('fpResendLink').addEventListener('click',function(e){
  e.preventDefault();
  const email=fpEmail||document.getElementById('fpEmailInput').value.trim();
  if(!email){showToast('Please enter your email first','error');return;}
  showLoading();
  const fd=new FormData();fd.append('email',email);
  fetch('forgot_password_send_otp.php',{method:'POST',body:fd})
    .then(r=>r.text())
    .then(res=>{
      hideLoading();
      if(res.trim()==='success'){showToast('New code sent','success');startFpTimer(60);}
      else showToast(res,'error');
    })
    .catch(()=>{hideLoading();showToast('Network error','error');});
});

document.getElementById('fpVerifyOtpBtn').addEventListener('click',function(){
  const btn=this,err=document.getElementById('fpOtpError');
  err.classList.remove('show');
  const otp=document.getElementById('fpOtpInput').value.trim();
  if(!/^\d{6}$/.test(otp)){err.textContent='Please enter the 6-digit code';err.classList.add('show');return;}
  btn.disabled=true;showLoading();
  const fd=new FormData();fd.append('otp',otp);
  fetch('forgot_password_verify_otp.php',{method:'POST',body:fd})
    .then(r=>r.text())
    .then(res=>{
      hideLoading();btn.disabled=false;
      if(res.trim()==='success'){
        if(fpTimer)clearInterval(fpTimer);
        document.getElementById('fpResetError').classList.remove('show');
        showFpStep('fpStepReset');
        document.getElementById('fpNewPassword').focus();
      }else{err.textContent=res;err.classList.add('show');}
    })
    .catch(()=>{hideLoading();btn.disabled=false;showToast('Network error','error');});
});

document.getElementById('fpUpdateBtn').addEventListener('click',function(){
  const btn=this,err=document.getElementById('fpResetError');
  err.classList.remove('show');
  const pw=document.getElementById('fpNewPassword').value;
  const confirm=document.getElementById('fpConfirmPassword').value;
  if(pw.length<8||!/[A-Z]/.test(pw)||!/[a-z]/.test(pw)||!/[0-9]/.test(pw)){
    err.textContent='Password must be at least 8 characters and include uppercase, lowercase, and a number';
    err.classList.add('show');return;
  }
  if(pw!==confirm){err.textContent='Passwords do not match';err.classList.add('show');return;}
  btn.disabled=true;showLoading();
  const fd=new FormData();fd.append('password',pw);
  fetch('forgot_password_update.php',{method:'POST',body:fd})
    .then(r=>r.text())
    .then(res=>{
      hideLoading();btn.disabled=false;
      if(res.trim()==='success'){
        resetForgotPassword();
        showToast('Password updated! Please login with your new password.','success');
        switchModal('forgotPasswordModal','loginModal');
      }else{err.textContent=res;err.classList.add('show');}
    })
    .catch(()=>{hideLoading();btn.disabled=false;showToast('Network error','error');});
});

function checkFpPasswordStrength(pw){
  const fill=document.getElementById('fpPwStrengthFill');
  const label=document.getElementById('fpPwStrengthLabel');
  const r1=pw.length>=8, r2=/[A-Z]/.test(pw), r3=/[a-z]/.test(pw), r4=/[0-9]/.test(pw);
  const rules=[[1,r1],[2,r2],[3,r3],[4,r4]];
  rules.forEach(([i,met])=>{
    const el=document.getElementById('fpPwReq'+i);
    const base=el.getAttribute('data-text')||el.textContent.trim();
    el.innerHTML='<span class="pw-ic" aria-hidden="true">'+(met?'&#10003;':'&#10007;')+'</span> '+base;
    el.classList.toggle('met',met);
  });
  const score=[r1,r2,r3,r4].filter(Boolean).length;
  const levels=[
    {pct:'25%',color:'#ef4444',text:'Weak'},
    {pct:'50%',color:'#f59e0b',text:'Fair'},
    {pct:'75%',color:'#3b82f6',text:'Good'},
    {pct:'100%',color:'#10b981',text:'Strong'}
  ];
  const lvl=levels[score-1]||levels[0];
  fill.style.width=score>0?lvl.pct:'0%';
  fill.style.background=lvl.color;
  label.textContent=score>0?lvl.text:'';
  label.style.color=score>0?lvl.color:'';
}

/* -- Register -- */
/* ---- Custom Birthday Calendar (matches "Book Appointment" picker) ---- */
(function(){
  const WEEKDAYS = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  const pad2 = n => String(n).padStart(2,'0');
  const field  = document.getElementById('regDatepicker');
  const trigger = document.getElementById('regDpTrigger');
  const input  = document.getElementById('regDpInput');
  const hidden = document.getElementById('regBirthday');
  const ageEl  = document.getElementById('regAge');
  let open = false;
  let viewY, viewM;
  let lastGood = '';   // last valid YYYY-MM-DD committed

  function formatLong(d){
    if (!d) return '';
    return d.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
  }

  // Parse a typed date into YYYY-MM-DD (empty string if invalid).
  // Accepts: MM/DD/YYYY, MM/DD/YY, M/D/YY, with - or / separators.
  function parseTyped(str){
    const s = (str||'').trim();
    if (!s) return '';
    const m = s.match(/^(\d{1,2})[\s\/\-\.](\d{1,2})[\s\/\-\.](\d{2,4})$/);
    if (!m) return '';
    let mo = parseInt(m[1],10), da = parseInt(m[2],10), yr = parseInt(m[3],10);
    if (yr < 100) yr += yr < 50 ? 2000 : 1900;
    if (!(mo >= 1 && mo <= 12 && da >= 1 && da <= 31)) return '';
    const dt = new Date(yr, mo-1, da);
    if (dt.getFullYear() !== yr || dt.getMonth() !== mo-1 || dt.getDate() !== da) return '';
    return yr + '-' + pad2(mo) + '-' + pad2(da);
  }

  function setValue(val){
    hidden.value = val;
    const pick = val ? new Date(val + 'T00:00:00') : null;
    input.value = pick ? formatLong(pick) : '';
    input.classList.toggle('placeholder', !pick);
    lastGood = val;
    if (pick && !isNaN(pick.getTime())){
      const today = new Date();
      let age = today.getFullYear() - pick.getFullYear();
      const md = today.getMonth() - pick.getMonth();
      if (md < 0 || (md === 0 && today.getDate() < pick.getDate())) age--;
      ageEl.value = age;
    } else {
      ageEl.value = '';
    }
  }

  function close(){ open = false; trigger.classList.remove('open'); trigger.setAttribute('aria-expanded','false'); const p = document.getElementById('regDpPopup'); if (p) p.remove(); }

  function openPopup(){
    open = true; trigger.classList.add('open'); trigger.setAttribute('aria-expanded','true');
    const base = hidden.value ? new Date(hidden.value + 'T00:00:00') : new Date();
    viewY = base.getFullYear(); viewM = base.getMonth();
    const wrap = document.getElementById('regDatepicker');
    const rect = wrap.getBoundingClientRect();
    const pop = document.createElement('div'); pop.id='regDpPopup'; pop.className='reg-dp-popup';
    pop.innerHTML = popupHTML();
    document.body.appendChild(pop);
    const popH = 400, gap = 8, vh = window.innerHeight || document.documentElement.clientHeight;
    const downRoom = vh - rect.bottom - gap;
    const flip = downRoom < popH && rect.top > downRoom;
    pop.style.width = Math.min(320, Math.max(295, rect.width)) + 'px';
    pop.style.left = Math.max(8, Math.min(rect.left, (window.innerWidth||document.documentElement.clientWidth) - 8 - pop.clientWidth)) + 'px';
    pop.style.top = flip ? 'auto' : (rect.bottom + gap) + 'px';
    pop.style.bottom = flip ? (vh - rect.top + gap) + 'px' : 'auto';
    bindNav();
  }

  const CAL_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  function dayCellsHTML(){
    const firstDow = new Date(viewY, viewM, 1).getDay();
    const daysInMonth = new Date(viewY, viewM + 1, 0).getDate();
    const t = new Date();
    const todayStr = t.getFullYear() + '-' + pad2(t.getMonth()+1) + '-' + pad2(t.getDate());
    let cells = '';
    for (let i=0;i<firstDow;i++) cells += '<span class="reg-cal-day reg-cal-day--blank"></span>';
    for (let d=1; d<=daysInMonth; d++){
      const ds = viewY + '-' + pad2(viewM+1) + '-' + pad2(d);
      const isSel = ds === hidden.value;
      const isToday = ds === todayStr;
      const dt = new Date(ds + 'T00:00:00');
      const future = dt > t;
      const cls = ['reg-cal-btn'].concat(isSel?'active':[], isToday?'cal-today':[], future?'cal-future':[]).join(' ');
      cells += '<span class="reg-cal-day"><button type="button" class="'+cls+'" data-date="'+ds+'">'+d+'</button></span>';
    }
    return cells;
  }

  // Year dropdown options: this decade back 100 years for birth dates,
  // widened automatically if the arrows navigated outside that range.
  function yearOptionsHTML(){
    const nowY = new Date().getFullYear();
    const max = Math.max(nowY, viewY);
    const min = Math.min(nowY - 100, viewY);
    let opts = '';
    for (let y = max; y >= min; y--){
      opts += '<option value="'+y+'"'+(y===viewY?' selected':'')+'>'+y+'</option>';
    }
    return opts;
  }

  function popupHTML(){
    return '<div class="reg-calendar">' +
      '<div class="reg-cal-head">' +
        '<button type="button" class="reg-cal-nav" data-nav="-1" aria-label="Previous month"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>' +
        '<div class="reg-cal-title">' +
          '<span class="reg-cal-month">'+CAL_MONTHS[viewM]+'</span>' +
          '<select class="reg-cal-year" aria-label="Year">'+yearOptionsHTML()+'</select>' +
        '</div>' +
        '<button type="button" class="reg-cal-nav" data-nav="1" aria-label="Next month"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>' +
      '</div>' +
      '<div class="reg-cal-weekdays">'+WEEKDAYS.map(w=>'<span>'+w+'</span>').join('')+'</div>' +
      '<div class="reg-cal-days">'+dayCellsHTML()+'</div>' +
      '<div class="reg-cal-legend"><span class="reg-cal-legend-dot"></span><span>'+(hidden.value?('Selected: '+input.value):'Pick your birth date')+'</span>' +
        (hidden.value?'<button type="button" class="reg-cal-clear">Clear</button>':'') +
      '</div>' +
    '</div>';
  }

  // Update the month/year label and day grid inside the SAME popup node.
  // Never swap the node itself: the inline position/width set in openPopup()
  // would be lost, and the document click handler would see a detached target
  // and close the calendar.
  function refreshPopup(){
    const pop = document.getElementById('regDpPopup'); if (!pop) return;
    const monthEl = pop.querySelector('.reg-cal-month');
    if (monthEl) monthEl.textContent = CAL_MONTHS[viewM];
    const yearEl = pop.querySelector('.reg-cal-year');
    if (yearEl){ yearEl.innerHTML = yearOptionsHTML(); yearEl.value = String(viewY); }
    const daysEl = pop.querySelector('.reg-cal-days');
    if (daysEl) daysEl.innerHTML = dayCellsHTML();
    bindDayButtons(pop);
  }

  function bindDayButtons(pop){
    pop.querySelectorAll('.reg-cal-btn').forEach(function(b){
      b.addEventListener('click', function(){ setValue(b.getAttribute('data-date')); close(); });
    });
    const clear = pop.querySelector('.reg-cal-clear');
    if (clear) clear.addEventListener('click', function(){ setValue(''); close(); });
  }

  function bindNav(){
    const pop = document.getElementById('regDpPopup'); if (!pop) return;
    pop.querySelectorAll('.reg-cal-nav').forEach(function(n){
      n.addEventListener('click', function(ev){
        ev.stopPropagation(); // without this the document click handler can treat the
                              // click as "outside" the popup and close the calendar
        const dir = parseInt(n.getAttribute('data-nav'),10);
        let m = viewM + dir;
        if (m<0){ m=11; viewY--; } else if (m>11){ m=0; viewY++; }
        viewM = m;
        refreshPopup();
      });
    });
    const yearSel = pop.querySelector('.reg-cal-year');
    if (yearSel) yearSel.addEventListener('change', function(ev){
      ev.stopPropagation();
      const y = parseInt(yearSel.value, 10);
      if (!isNaN(y)){ viewY = y; refreshPopup(); }
    });
    bindDayButtons(pop);
  }

  // Open the calendar from the trigger button or clicking the field
  function openFromField(){
    if (open){ close(); return; }
    openPopup();
  }
  trigger.addEventListener('click', function(ev){ ev.stopPropagation(); openFromField(); });
  input.addEventListener('focus', function(){ if (!open) openPopup(); });

  // Manual entry — live auto-format slashes while typing, but keep the
  // numeric MM/DD/YYYY in the field. Only switch to the long display on a
  // final commit (blur / Enter). This prevents mid-typing corruption.
  input.addEventListener('input', function(){
    const digits = input.value.replace(/[^\d]/g,'').slice(0,8);
    let formatted = digits;
    if (digits.length >= 3) formatted = digits.slice(0,2) + '/' + digits.slice(2);
    if (digits.length >= 5) formatted = digits.slice(0,2) + '/' + digits.slice(2,4) + '/' + digits.slice(4);
    input.value = formatted;

    // Live-update once the full 8-digit date (MM/DD/YYYY) is valid.
    if (digits.length >= 8) {
      const parsed = parseTyped(input.value);
      if (parsed) {
        hidden.value = parsed;
        const pick = new Date(parsed + 'T00:00:00');
        const today = new Date();
        let age = today.getFullYear() - pick.getFullYear();
        const md = today.getMonth() - pick.getMonth();
        if (md < 0 || (md === 0 && today.getDate() < pick.getDate())) age--;
        ageEl.value = age;
      }
    } else if (!input.value) {
      hidden.value = ''; ageEl.value = '';
    }
  });
  function commitOnBlur(){
    const parsed = parseTyped(input.value);
    if (parsed) setValue(parsed);
    else if (hidden.value) setValue(hidden.value); // keep a just-picked calendar date shown in long format
    else { input.value=''; hidden.value=''; ageEl.value=''; }
  }
  input.addEventListener('blur', function(){
    if (open) return; // let the calendar interaction finish
    commitOnBlur();
  });
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter'){ e.preventDefault(); commitOnBlur(); close(); }
  });

  document.addEventListener('click', function(e){
    const popEl = document.getElementById('regDpPopup');
    if (open && popEl && !popEl.contains(e.target) && !field.contains(e.target)) close();
  });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') close(); });
})();

// -- Philippine Address Cascading Dropdowns (PSGC API) ----------------
const PSGC = 'https://psgc.gitlab.io/api';

<<<<<<< HEAD
// Load all regions on page ready
fetch(`${PSGC}/regions/`)
    .then(r => r.json())
    .then(data => {
        const sel = document.getElementById('regRegion');
        data.sort((a, b) => a.name.localeCompare(b.name)).forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.code;
            opt.textContent = r.name;
            sel.appendChild(opt);
        });
        if (window.__fdResync) window.__fdResync(sel);
    })
    .catch(() => {
        const sel = document.getElementById('regRegion');
        sel.innerHTML = '<option value="">Failed to load regions</option>';
        if (window.__fdResync) window.__fdResync(sel);
    });
=======
// Load provinces for NCR and CALABARZON
function loadProvincesByRegion() {
    const regionSel = document.getElementById('regRegion');
    const provSel = document.getElementById('regProvince');
    const region = regionSel.value;
    
    provSel.innerHTML = '<option value="">Loading...</option>';
    provSel.disabled = true;
    
    // NCR has no provinces, load cities directly
    if (region === 'NCR') {
        fetch(`${PSGC}/regions/130000000/cities/`)
            .then(r => r.json())
            .then(cities => {
                provSel.innerHTML = '<option value="">NCR (Direct)</option>';
                provSel.disabled = false;
                const munSel = document.getElementById('regMunicipality');
                munSel.innerHTML = '<option value="">Select City/Municipality</option>';
                cities.sort((a, b) => a.name.localeCompare(b.name)).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.code;
                    opt.textContent = c.name;
                    munSel.appendChild(opt);
                });
                munSel.disabled = false;
                if (window.__fdResync) window.__fdResync(munSel);
                updateFullAddress();
            })
            .catch(() => {
                provSel.innerHTML = '<option value="">Select Province</option>';
                provSel.disabled = false;
            });
        return;
    }
    
    // CALABARZON
    if (region === 'CALABARZON') {
        fetch(`${PSGC}/regions/040000000/provinces/`)
            .then(r => r.json())
            .then(data => {
                provSel.innerHTML = '<option value="">Select Province</option>';
                data.sort((a, b) => a.name.localeCompare(b.name)).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.code;
                    opt.textContent = p.name;
                    provSel.appendChild(opt);
                });
                provSel.disabled = false;
                if (window.__fdResync) window.__fdResync(provSel);
                updateFullAddress();
            })
            .catch(() => {
                provSel.innerHTML = '<option value="">Failed to load provinces</option>';
                if (window.__fdResync) window.__fdResync(provSel);
            });
    }
}

document.getElementById('regRegion').addEventListener('change', loadProvincesByRegion);
loadProvincesByRegion();
>>>>>>> refinement

function loadProvinces() {
    const regionCode = document.getElementById('regRegion').value;
    const provSel = document.getElementById('regProvince');
    const munSel  = document.getElementById('regMunicipality');
    const brgySel = document.getElementById('regBarangay');

    provSel.innerHTML = '<option value="">Loading...</option>';
    provSel.disabled  = true;
    munSel.innerHTML  = '<option value="">Select Municipality / City</option>';
    munSel.disabled   = true;
    brgySel.innerHTML = '<option value="">Select Barangay</option>';
    brgySel.disabled  = true;
    if (window.__fdResync) { window.__fdResync(provSel); window.__fdResync(munSel); window.__fdResync(brgySel); }
    document.getElementById('regFullAddress').value = '';

    if (!regionCode) return;

    fetch(`${PSGC}/regions/${regionCode}/provinces/`)
        .then(r => r.ok ? r.json() : [])
        .then(data => {
            provSel.innerHTML = '<option value="">Select Province</option>';
            data.sort((a, b) => a.name.localeCompare(b.name)).forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.code;
                opt.textContent = p.name;
                provSel.appendChild(opt);
            });
            if (data.length === 0) {
                // NCR and some regions have no provinces - load cities directly
                return fetch(`${PSGC}/regions/${regionCode}/cities/`)
                    .then(r => r.ok ? r.json() : fetch(`${PSGC}/regions/${regionCode}/cities-municipalities/`).then(r2 => r2.ok ? r2.json() : []))
                    .then(cities => {
                        if (cities && cities.length) {
                            const munSel2  = document.getElementById('regMunicipality');
                            const brgySel2 = document.getElementById('regBarangay');
                            munSel2.innerHTML = '<option value="">Select Municipality / City</option>';
                            cities.sort((a, b) => a.name.localeCompare(b.name)).forEach(m => {
                                const opt = document.createElement('option');
                                opt.value = m.code;
                                opt.textContent = m.name;
                                munSel2.appendChild(opt);
                            });
                            munSel2.disabled = false;
                            if (window.__fdResync) window.__fdResync(munSel2);
                            brgySel2.innerHTML = '<option value="">Select Barangay</option>';
                            brgySel2.disabled = true;
                            if (window.__fdResync) window.__fdResync(brgySel2);
                        }
                    });
            }
            provSel.disabled = false;
            if (window.__fdResync) window.__fdResync(provSel);
            updateFullAddress();
        })
        .catch(() => {
            provSel.innerHTML = '<option value="">Error loading. Try again.</option>';
            if (window.__fdResync) window.__fdResync(provSel);
        });
}

function loadMunicipalities() {
    const provinceCode = document.getElementById('regProvince').value;
<<<<<<< HEAD
    const regionCode = document.getElementById('regRegion').value;
=======
    const region = document.getElementById('regRegion').value;
>>>>>>> refinement
    const munSel  = document.getElementById('regMunicipality');
    const brgySel = document.getElementById('regBarangay');

    munSel.innerHTML  = '<option value="">Loading...</option>';
    munSel.disabled   = true;
    brgySel.innerHTML = '<option value="">Select Barangay</option>';
    brgySel.disabled  = true;
    if (window.__fdResync) { window.__fdResync(munSel); window.__fdResync(brgySel); }
    document.getElementById('regFullAddress').value = '';

<<<<<<< HEAD
    if (!provinceCode && regionCode) {
        // Try to load cities from region (e.g. NCR)
        fetch(`${PSGC}/regions/${regionCode}/cities/`)
            .then(r => r.ok ? r.json() : fetch(`${PSGC}/regions/${regionCode}/cities-municipalities/`).then(r2 => r2.ok ? r2.json() : []))
            .then(cities => {
                if (cities && cities.length) {
                    munSel.innerHTML = '<option value="">Select Municipality / City</option>';
                    cities.sort((a, b) => a.name.localeCompare(b.name)).forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.code;
                        opt.textContent = m.name;
                        munSel.appendChild(opt);
                    });
                    munSel.disabled = false;
                    if (window.__fdResync) window.__fdResync(munSel);
                    updateFullAddress();
                    return;
                }
                munSel.innerHTML = '<option value="">Select Municipality / City</option>';
                if (window.__fdResync) window.__fdResync(munSel);
            })
            .catch(() => {
                munSel.innerHTML = '<option value="">Error loading. Try again.</option>';
                if (window.__fdResync) window.__fdResync(munSel);
            });
=======
    if (region === 'NCR') {
        // Already loaded in loadProvincesByRegion
        munSel.disabled = false;
        if (window.__fdResync) window.__fdResync(munSel);
        updateFullAddress();
>>>>>>> refinement
        return;
    }

    if (!provinceCode) return;

    Promise.all([
        fetch(`${PSGC}/provinces/${provinceCode}/municipalities/`).then(r => r.ok ? r.json() : []),
        fetch(`${PSGC}/provinces/${provinceCode}/cities/`).then(r => r.ok ? r.json() : [])
    ]).then(([municipalities, cities]) => {
        const combined = [...municipalities, ...cities]
            .sort((a, b) => a.name.localeCompare(b.name));
        munSel.innerHTML = '<option value="">Select Municipality / City</option>';
        combined.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.code;
            opt.textContent = m.name;
            munSel.appendChild(opt);
        });
        munSel.disabled = false;
        if (window.__fdResync) window.__fdResync(munSel);
        updateFullAddress();
    }).catch(() => {
        munSel.innerHTML = '<option value="">Error loading. Try again.</option>';
        if (window.__fdResync) window.__fdResync(munSel);
    });
}

function loadBarangays() {
    const munCode = document.getElementById('regMunicipality').value;
    const brgySel = document.getElementById('regBarangay');

    brgySel.innerHTML = '<option value="">Loading...</option>';
    brgySel.disabled  = true;
    if (window.__fdResync) window.__fdResync(brgySel);

    if (!munCode) return;

    // Try municipality endpoint first, fallback to city endpoint
    fetch(`${PSGC}/municipalities/${munCode}/barangays/`)
        .then(r => r.ok ? r.json() : fetch(`${PSGC}/cities/${munCode}/barangays/`).then(r2 => r2.json()))
        .then(data => {
            brgySel.innerHTML = '<option value="">Select Barangay</option>';
            data.sort((a, b) => a.name.localeCompare(b.name)).forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.code;
                opt.textContent = b.name;
                brgySel.appendChild(opt);
            });
            brgySel.disabled = false;
            if (window.__fdResync) window.__fdResync(brgySel);
            updateFullAddress();
        }).catch(() => {
            brgySel.innerHTML = '<option value="">Error loading barangays</option>';
            if (window.__fdResync) window.__fdResync(brgySel);
        });
}

function updateFullAddress() {
    const street   = document.getElementById('regStreet').value.trim();
    const brgySel  = document.getElementById('regBarangay');
    const munSel   = document.getElementById('regMunicipality');
    const provSel  = document.getElementById('regProvince');
<<<<<<< HEAD
    const regionSel= document.getElementById('regRegion');
=======
    const regSel   = document.getElementById('regRegion');
>>>>>>> refinement

    const brgyText   = brgySel.selectedIndex > 0 ? brgySel.options[brgySel.selectedIndex].text : '';
    const munText    = munSel.selectedIndex  > 0 ? munSel.options[munSel.selectedIndex].text   : '';
    const provText   = provSel.selectedIndex > 0 && provSel.options[provSel.selectedIndex].text !== 'Select Province' ? provSel.options[provSel.selectedIndex].text : '';
<<<<<<< HEAD
    const regionText = regionSel.selectedIndex > 0 && regionSel.options[regionSel.selectedIndex].text !== 'Select Region' ? regionSel.options[regionSel.selectedIndex].text : '';

    const parts = [street, brgyText, munText, provText, regionText].filter(p => p);
=======
    const regText    = regSel.options[regSel.selectedIndex] ? regSel.options[regSel.selectedIndex].text : '';

    const parts = [street, brgyText, munText, provText, regText].filter(p => p && p !== 'Select Region');
>>>>>>> refinement
    document.getElementById('regFullAddress').value = parts.join(', ');
}

// -- Password visibility toggle --------------------------------------
function togglePw(btn){
  const field = btn.closest('.pw-field');
  const input = field.querySelector('input');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  const eye = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeOff = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>';
  btn.innerHTML = show ? eyeOff : eye;
}
// -- Password Strength Checker -----------------------------------------
function checkPasswordStrength(pw) {
    const box   = document.getElementById('pwStrengthBox');
    const fill  = document.getElementById('pwStrengthFill');
    const label = document.getElementById('pwStrengthLabel');

    const r1 = pw.length >= 8;
    const r2 = /[A-Z]/.test(pw);
    const r3 = /[a-z]/.test(pw);
    const r4 = /[0-9]/.test(pw);

    function setReq(id, met) {
        const el = document.getElementById(id);
        const base = el.getAttribute('data-text') || el.textContent.trim();
        el.innerHTML = '<span class="pw-ic" aria-hidden="true">' + (met ? '&#10003;' : '&#10007;') + '</span> ' + base;
        el.classList.toggle('met', met);
    }
    setReq('pwReq1', r1);
    setReq('pwReq2', r2);
    setReq('pwReq3', r3);
    setReq('pwReq4', r4);

    const score = [r1, r2, r3, r4].filter(Boolean).length;
    box.style.display = '';

    const levels = [
        { pct: '25%', color: '#ef4444', text: 'Weak' },
        { pct: '50%', color: '#f59e0b', text: 'Fair' },
        { pct: '75%', color: '#3b82f6', text: 'Good' },
        { pct: '100%', color: '#10b981', text: 'Strong' },
    ];
    const lvl = levels[score - 1] || levels[0];
    fill.style.width    = score > 0 ? lvl.pct : '0%';
    fill.style.background = lvl.color;
    label.textContent   = score > 0 ? lvl.text : '';
    label.style.color   = score > 0 ? lvl.color : '';
}
// -- End Password Strength ---------------------------------------------

// Register form &mdash; validate address then submit
document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const password        = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;

    // Validate birthday was picked via the custom calendar
    if (!document.getElementById('regBirthday').value) {
        showToast('Please select your birthday', 'error');
        return;
    }

    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }

    // Strong password checks
    if (password.length < 8) {
        showToast('Password must be at least 8 characters', 'error'); return;
    }
    if (!/[A-Z]/.test(password)) {
        showToast('Password must contain at least one uppercase letter', 'error'); return;
    }
    if (!/[a-z]/.test(password)) {
        showToast('Password must contain at least one lowercase letter', 'error'); return;
    }
    if (!/[0-9]/.test(password)) {
        showToast('Password must contain at least one number', 'error'); return;
    }

    // Validate address selections
    if (!document.getElementById('regProvince').value) {
        showToast('Please select your province', 'error'); return;
    }
    if (!document.getElementById('regMunicipality').value) {
        showToast('Please select your municipality / city', 'error'); return;
    }
    if (!document.getElementById('regBarangay').value) {
        showToast('Please select your barangay', 'error'); return;
    }
    if (!document.getElementById('regStreet').value.trim()) {
        showToast('Please enter your house no. / street', 'error'); return;
    }

    updateFullAddress(); // make sure hidden field is current

    // Step 1: send the verification code (server validates + stages the data in session)
    showLoading();
    const formData = new FormData(this);
    fetch('register_otp_send.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(result => {
            hideLoading();
            if (result.trim() === 'success') {
                showRegisterOtp(formData.get('email'));
            } else {
                showToast(result, 'error');
            }
        })
        .catch(err => { hideLoading(); showToast('Something went wrong. Please check your connection and try again.', 'error'); });
});

// -- OTP verification step for registration -------------------------
let regOtpTimer = null;

function showRegisterOtp(email) {
    document.getElementById('registerForm').style.display = 'none';
    document.getElementById('regBackToForm').style.display = '';
    const panel = document.getElementById('regOtpPanel');
    panel.style.display = '';
    document.getElementById('regOtpHint').textContent = 'A 6-digit verification code was sent to ' + email + '. Enter it below to create your account.';
    document.getElementById('regOtpError').style.display = 'none';
    document.getElementById('regOtpInput').value = '';
    document.getElementById('regOtpInput').focus();
    startRegOtpTimer(60);
}

function startRegOtpTimer(seconds) {
    const el = document.getElementById('regOtpTimer');
    if (regOtpTimer) clearInterval(regOtpTimer);
    const tick = () => {
        el.textContent = seconds > 0 ? '(resend in ' + seconds + 's)' : '';
        document.getElementById('regResendLink').style.pointerEvents = seconds > 0 ? 'none' : 'auto';
        document.getElementById('regResendLink').style.opacity = seconds > 0 ? '.5' : '1';
        if (seconds > 0) seconds--; else clearInterval(regOtpTimer);
    };
    tick();
    regOtpTimer = setInterval(tick, 1000);
}

// Resend the registration code
document.getElementById('regResendLink').addEventListener('click', function(e) {
    e.preventDefault();
    const form = document.getElementById('registerForm');
    const fd = new FormData(form);
    // Re-send using the already-staged session data
    fetch('register_otp_send.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(res => {
            if (res.trim() === 'success') { showToast('New code sent', 'success'); startRegOtpTimer(60); }
            else { showToast(res, 'error'); }
        })
        .catch(() => showToast('Network error', 'error'));
});

// Verify the code, then create the account
document.getElementById('regOtpVerify').addEventListener('click', function() {
    const otp = document.getElementById('regOtpInput').value.trim();
    const errEl = document.getElementById('regOtpError');
    errEl.style.display = 'none';
    if (otp.length !== 6) {
        errEl.textContent = 'Please enter the 6-digit code';
        errEl.style.display = '';
        return;
    }
    showLoading();
    const form = document.getElementById('registerForm');
    const fd = new FormData(form);
    fd.append('otp', otp);
    fetch('register.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(result => {
            hideLoading();
            if (result.trim() === 'success') {
                if (regOtpTimer) clearInterval(regOtpTimer);
                resetRegisterOtp();
                showToast('Account created! Please login.', 'success');
                switchModal('registerModal', 'loginModal');
            } else {
                errEl.textContent = result;
                errEl.style.display = '';
            }
        })
        .catch(() => { hideLoading(); showToast('Something went wrong. Please check your connection and try again.', 'error'); });
});

// Back button: return to the main register form
document.getElementById('regBackToForm').addEventListener('click', function() {
    resetRegisterOtp();
    document.getElementById('registerForm').style.display = '';
});

function resetRegisterOtp() {
    document.getElementById('regOtpPanel').style.display = 'none';
    document.getElementById('regBackToForm').style.display = 'none';
    document.getElementById('registerForm').style.display = '';
    document.getElementById('regOtpError').style.display = 'none';
    if (regOtpTimer) clearInterval(regOtpTimer);
    document.getElementById('regOtpTimer').textContent = '';
}
// -- End Address Logic -------------------------------------------------


/* -- Chatbot -- */
(function(){
  const GROQ_MODEL='qwen/qwen3.8-27b';
  const SYSTEM_PROMPT=`You are MedBot, a compassionate AI medical assistant for LustreMDC Clinics & Diagnostics (Lustre Medical and Diagnostic Clinic).

Clinic facts you know:
- Address: 111 Urbano Velasco Avenue, Brgy. Pinagbuhatan, Pasig City
- Phone: 0928 599 6038
- Services: Pediatric Consultation, Adult Consultation, OB-Gyn Consultation (by appointment), Pediatric and Adult Vaccination, Medical Certification, Physical Examination / Fit to Work Certification, and 20+ lab tests (CBC, ECG, X-Ray, Ultrasound/UTZ, and more)
- Lab results are typically ready within 24 hours
- Appointments are booked online: create an account, pick a doctor/service, choose a date and time, and receive confirmation
- Walk-ins are accepted for consultations; OB-Gyn is by appointment only
- For clinic hours, pricing, and availability, politely advise the patient to call or text 0928 599 6038 for the most accurate and up-to-date information — never invent specific hours or prices

Help patients with questions about clinic services, lab tests, diagnostics, OB-GYN care, appointment booking, and how to get their results. Guide them on how to use the website (register, book, view results in their account). Be warm, concise, and always recommend consulting a doctor for serious concerns. Add: "This is general health information, not a medical diagnosis." when giving medical info.`;
  let history=[],typing=false;
  const btn=document.getElementById('chatbotButton');
  const box=document.getElementById('chatbotBox');
  const inp=document.getElementById('chatbotInput');
  const send=document.getElementById('chatbotSend');
  const msgs=document.getElementById('chatbotMessages');

  btn.addEventListener('click',()=>{box.classList.add('active');btn.style.display='none';inp.focus();});
  document.querySelector('.chatbot-close').addEventListener('click',()=>{box.classList.remove('active');btn.style.display='flex';});

  /* Keep the panel above the on-screen keyboard. On phones the keyboard
     overlays the layout viewport instead of resizing it, so a position:fixed
     panel anchored to its bottom edge sits underneath the keyboard and the
     input bar cannot be reached. visualViewport reports the area that is
     actually visible; --kb is the covered strip, --vvh/--vvw the visible
     size, and #chatbotBox consumes all three. */
  const vv=window.visualViewport;
  let vvPending=0;
  function syncViewport(){
    if(vvPending){return;}
    vvPending=requestAnimationFrame(()=>{
      vvPending=0;
      if(!vv){return;}
      const covered=window.innerHeight-vv.height-vv.offsetTop;
      /* below ~100px it is just browser chrome collapsing, not a keyboard */
      const kb=covered>100?Math.round(covered):0;
      const root=document.documentElement.style;
      root.setProperty('--kb',kb+'px');
      root.setProperty('--vvh',Math.round(vv.height)+'px');
      root.setProperty('--vvw',Math.round(vv.width)+'px');
      box.classList.toggle('kb-open',kb>0);
      /* shrinking the box squeezes the message list, so keep the newest
         message in view instead of letting it drop below the input */
      if(kb>0){msgs.scrollTop=msgs.scrollHeight;}
    });
  }
  if(vv){
    vv.addEventListener('resize',syncViewport);
    vv.addEventListener('scroll',syncViewport,{passive:true});
  }
  window.addEventListener('orientationchange',()=>{setTimeout(syncViewport,250);});
  inp.addEventListener('focus',syncViewport);
  inp.addEventListener('blur',syncViewport);
  syncViewport();

  document.querySelectorAll('.chip').forEach(chip=>{
    chip.addEventListener('click',()=>{inp.value=chip.getAttribute('data-msg');sendMsg();});
  });

  function addMsg(text,role){
    const isBot=role==='bot';
    const icon=isBot
      ?'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'
      :'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    const d=document.createElement('div');
    d.className='chatbot-message '+(isBot?'bot-message':'user-message');
    d.innerHTML='<div class="msg-avatar">'+icon+'</div><p>'+text.replace(/\n/g,'<br>')+'</p>';
    msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;
    return d;
  }
  function showTyping(){
    const d=document.createElement('div');
    d.className='chatbot-message bot-message typing-indicator';
    d.id='typingIndicator';
    d.innerHTML='<div class="msg-avatar"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><p><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></p>';
    msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;
  }
  function hideTyping(){const el=document.getElementById('typingIndicator');if(el)el.remove();}

  async function sendMsg(){
    const msg=inp.value.trim();if(!msg||typing)return;
    addMsg(msg,'user');inp.value='';typing=true;send.disabled=true;
    history.push({role:'user',content:msg});
    showTyping();
    try{
      const r=await fetch('medbot.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({model:GROQ_MODEL,messages:[{role:'system',content:SYSTEM_PROMPT},...history],max_tokens:500})});
      if(!r.ok)throw new Error('API error '+r.status);
      const data=await r.json();const reply=data.choices[0].message.content.trim();
      hideTyping();history.push({role:'assistant',content:reply});
      if(history.length>20)history=history.slice(-20);
      addMsg(reply,'bot');
    }catch(e){
      hideTyping();
      addMsg('Sorry, I\'m having trouble connecting right now. Please try again shortly.','bot');
    }
    typing=false;send.disabled=false;inp.focus();
  }
  send.addEventListener('click',sendMsg);
  inp.addEventListener('keypress',e=>{if(e.key==='Enter')sendMsg();});
})();
</script>
<script src="admin_components.js?v=<?= filemtime(__DIR__ . '/../../admin_components.js') ?>"></script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
</body>
</html>
