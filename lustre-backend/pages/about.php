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
<title>About &mdash; LustreMDC</title>
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
    <a href="landing.php" class="nav-brand">
      <div class="nav-logo-circle"><img src="images/Lustre.png" alt="Logo" class="logo-img" width="40" height="40"></div>
      <span class="nav-clinic-name">LUSTRE MEDICAL DIAGNOSTIC CLINIC</span>
    </a>
    <div class="nav-links">
      <a href="about.php" class="active" aria-current="page">About</a>
      <a href="landing.php#contact">Contact</a>
    </div>
    <div class="nav-actions">
      <button class="theme-toggle" type="button" aria-label="Toggle light/dark mode" title="Toggle dark mode">
        <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
      </button>
      <a class="nav-btn nav-btn-ghost" href="landing.php?signin=1">Sign In</a>
      <a class="nav-btn nav-btn-solid" href="register.php">Register Free</a>
    </div>
  </div>
</nav>

<!-- == ABOUT == -->
<section class="page-section about-page">
  <div class="section-header">
    <div class="section-tag">About</div>
    <h1 class="section-title">About LustreMDC</h1>
    <p class="section-sub">Content coming soon.</p>
  </div>
  <div class="about-actions">
    <a class="back-home-btn" href="landing.php">&larr; Back to home</a>
  </div>
</section>

<!-- == MISSION & VISION == -->
<section class="page-section mv-section">
  <div class="section-header">
    <div class="section-tag">Mission &amp; Vision</div>
    <h2 class="section-title">What Guides Us</h2>
    <p class="section-sub">The purpose and promise behind everything we do.</p>
  </div>

  <div class="mv-grid">
    <div class="mv-card">
      <div class="mv-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></div>
      <div class="mv-label">Mission</div>
      <p class="mv-text">To provide safe, efficient, and affordable healthcare services with professionalism and integrity. We are committed to meeting the medical needs of our patients through skilled staff, and compassionate service.</p>
    </div>

    <div class="mv-card">
      <div class="mv-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></div>
      <div class="mv-label">Vision</div>
      <p class="mv-text">To be a trusted healthcare provider that delivers compassionate, high-quality and accessible medical services to the community. We aim to promote healthier lives through patient-centered care and continuous improvement.</p>
    </div>
  </div>
</section>

<!-- == CLINIC RULES AND REGULATIONS == -->
<section class="page-section rules-section" id="rules">
  <div class="section-header">
    <div class="section-tag">Clinic Rules</div>
    <h2 class="section-title">Clinic Rules and Regulations</h2>
    <p class="section-sub">Please follow these guidelines for a safe and comfortable visit.</p>
  </div>
  <div class="rules-card">

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">1</span><h3 class="rule-title">Clinic Hours</h3></div>
      <ul class="service-items">
        <li>The clinic operates during designated hours. Patients are encouraged to arrive on time for their scheduled appointments.</li>
        <li>Late arrivals may be rescheduled depending on availability.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">2</span><h3 class="rule-title">Appointment Policy</h3></div>
      <ul class="service-items">
        <li>Patients must secure an appointment prior to consultation, except in emergency cases.</li>
        <li>Cancellations must be made at least 24 hours before the scheduled appointment.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">3</span><h3 class="rule-title">Patient Conduct</h3></div>
      <ul class="service-items">
        <li>Patients and companions must behave respectfully toward clinic staff and other patients.</li>
        <li>Any form of harassment, violence, or disruptive behavior will not be tolerated.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">4</span><h3 class="rule-title">Cleanliness and Safety</h3></div>
      <ul class="service-items">
        <li>Maintain cleanliness within the clinic premises.</li>
        <li>Smoking, vaping, and bringing hazardous materials are strictly prohibited.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">5</span><h3 class="rule-title">Confidentiality</h3></div>
      <ul class="service-items">
        <li>All patient information and medical records are strictly confidential.</li>
        <li>Patients must provide accurate and complete medical information.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">6</span><h3 class="rule-title">Payment Policy</h3></div>
      <ul class="service-items">
        <li>Payments must be settled after consultation or treatment unless prior arrangements have been made.</li>
        <li>The clinic reserves the right to refuse service for unpaid balances.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">7</span><h3 class="rule-title">Infection Control</h3></div>
      <ul class="service-items">
        <li>Patients with contagious symptoms must inform the clinic before arrival.</li>
        <li>Wearing masks may be required when necessary to ensure safety.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">8</span><h3 class="rule-title">Companion Policy</h3></div>
      <ul class="service-items">
        <li>Only one companion per patient is allowed unless special assistance is required.</li>
        <li>Children must be supervised at all times.</li>
      </ul>
    </div>

    <div class="rule-item">
      <div class="rule-head"><span class="rule-num">9</span><h3 class="rule-title">Emergency Cases</h3></div>
      <ul class="service-items">
        <li>Emergency cases will be prioritized.</li>
        <li>The clinic may refer patients to a hospital if the condition requires advanced care.</li>
      </ul>
    </div>

  </div>
</section>

<!-- == FOOTER == -->
<footer>
  <div class="footer-inner">
    <p class="footer-copy">&copy; <?php echo date('Y'); ?> LustreMDC Clinics &amp; Diagnostics. <span>All rights reserved.</span></p>
  </div>
</footer>

<!-- == MOBILE BOTTOM NAV == -->
<nav class="bottom-nav" aria-label="Primary">
  <a class="bottom-nav-item bottom-nav-home" href="landing.php">
    <span class="bottom-nav-icon">
      <svg class="ic-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.7V21h14V9.7"/><path d="M9.5 21v-6h5v6"/></svg>
      <svg class="ic-fill" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3 3 10.6V21h6v-6h6v6h6V10.6Z"/></svg>
    </span>
    <span>Home</span>
  </a>
  <a class="bottom-nav-item active" href="about.php" aria-current="page">
    <span class="bottom-nav-icon">
      <svg class="ic-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.2v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg>
      
    </span>
    <span>About</span>
  </a>
  <a class="bottom-nav-item" href="landing.php#contact">
    <span class="bottom-nav-icon">
      <svg class="ic-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 16.9v2.8a2 2 0 0 1-2.2 2 19.6 19.6 0 0 1-8.5-3 19.3 19.3 0 0 1-6-6 19.6 19.6 0 0 1-3-8.6 2 2 0 0 1 2-2.2h2.8a2 2 0 0 1 2 1.7c.13 1 .37 1.9.7 2.8a2 2 0 0 1-.49 2.1L8 9.9a16 16 0 0 0 6 6l1.4-1.4a2 2 0 0 1 2.1-.5c.9.33 1.83.57 2.8.7a2 2 0 0 1 1.7 2.1Z"/></svg>
      </span>
    <span>Contact</span>
  </a>
  <a class="bottom-nav-cta" href="landing.php?signin=1">Book Now</a>
</nav>

<script>
/* -- Navbar scroll -- */
window.addEventListener('scroll',()=>document.getElementById('navbar').classList.toggle('scrolled',scrollY>40),{passive:true});
</script>
<script src="admin_theme.js?v=<?= filemtime(__DIR__ . '/../../admin_theme.js') ?>"></script>
</body>
</html>
