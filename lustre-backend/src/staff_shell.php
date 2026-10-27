<?php
// Shared thin-shell renderer for the React STAFF portal pages.
//
// Each admin_*.php / doctor_*.php page becomes a tiny wrapper that:
//   1. authenticates (admin or doctor session),
//   2. computes the initial data bundle (same SQL as api_*_data.php),
//   3. calls render_staff_shell() to emit the HTML frame + __LUSTRE__ blob.
//
// The React bundle then renders from window.__LUSTRE__ and performs actions
// against the existing action endpoints.

require_once __DIR__ . '/api.php';

/**
 * Emit the HTML document for a staff React page.
 *
 * @param array $staff  Identity block surfaced to React (role, name, urls...).
 * @param string $view  The logical view name (used by pages for refetches).
 * @param array $data   The initial data bundle for the page component.
 * @param string $entry Bundle entry filename (without extension).
 * @param string $title Document title.
 */
function render_staff_shell(array $staff, string $view, array $data, string $entry, string $title): void
{
    $flags   = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $payload = array('staff' => $staff, 'view' => $view, 'data' => $data);

    $asset_css = 'staff/dist/assets/app.css';
    $asset_js  = 'staff/dist/assets/' . $entry . '.js';
    $v_css = is_file(__DIR__ . '/../../' . $asset_css) ? filemtime(__DIR__ . '/../../' . $asset_css) : 0;
    $v_js  = is_file(__DIR__ . '/../../' . $asset_js)  ? filemtime(__DIR__ . '/../../' . $asset_js)  : 0;

    // The legacy portal stylesheets are reused verbatim by the React app.
    $shared_css = 'admin.css';
    $v_shared = is_file(__DIR__ . '/../../' . $shared_css) ? filemtime(__DIR__ . '/../../' . $shared_css) : 0;
    $doctor_css = 'doctor.css';
    $v_doctor = is_file(__DIR__ . '/../../' . $doctor_css) ? filemtime(__DIR__ . '/../../' . $doctor_css) : 0;
    $doctor_portal_css = 'doctor_portal.css';
    $v_doctor_portal = is_file(__DIR__ . '/../../' . $doctor_portal_css) ? filemtime(__DIR__ . '/../../' . $doctor_portal_css) : 0;

    $role = isset($staff['role']) ? $staff['role'] : 'admin';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0d9488">
  <script>try{if(localStorage.getItem('meTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}</script>
  <link rel="icon" href="images/Lustre.png" type="image/png">
  <?php if ($role === 'doctor'): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($shared_css, ENT_QUOTES); ?>?v=<?php echo $v_shared; ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($doctor_css, ENT_QUOTES); ?>?v=<?php echo $v_doctor; ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($asset_css, ENT_QUOTES); ?>?v=<?php echo $v_css; ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($doctor_portal_css, ENT_QUOTES); ?>?v=<?php echo $v_doctor_portal; ?>">
  <?php else: ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($asset_css, ENT_QUOTES); ?>?v=<?php echo $v_css; ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($shared_css, ENT_QUOTES); ?>?v=<?php echo $v_shared; ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES); ?> &mdash; LustreMDC</title>
  <style>
    @keyframes __staffFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes __staffFadeOut { from { opacity: 1; } to { opacity: 0; } }
    body { animation: __staffFadeIn .35s ease both; }
    body.__leaving { animation: __staffFadeOut .2s ease forwards; pointer-events: none; }
    @media (prefers-reduced-motion: reduce) {
      body, body.__leaving { animation: none; }
    }
  </style>
</head>
<body>
  <div id="root"></div>
  <noscript><div style="padding:32px;font-family:system-ui,sans-serif;">JavaScript is required for the LustreMDC staff portal.</div></noscript>
  <div id="staff-boot-error" style="display:none;padding:32px;font-family:system-ui,sans-serif;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;margin:40px auto;max-width:720px;">
    <h2 style="margin:0 0 8px;font-size:18px;">This page failed to load.</h2>
    <p style="margin:0 0 12px;color:#7f1d1d;">Please refresh. If it persists, the staff bundle may not be deployed.</p>
    <pre id="staff-boot-error-detail" style="white-space:pre-wrap;font-size:12px;color:#7f1d1d;margin:0;"></pre>
  </div>
  <script>
    window.__LUSTRE__ = <?php echo json_encode($payload, $flags); ?>;
    (function () {
      var shown = false;
      window.__staffBootError = function (detail) {
        if (shown) return; shown = true;
        var box = document.getElementById('staff-boot-error');
        if (!box) { box = document.createElement('div'); box.id = 'staff-boot-error'; document.body.appendChild(box); }
        box.style.display = 'block';
        var pre = document.getElementById('staff-boot-error-detail');
        if (pre && detail) pre.textContent = String(detail);
      };
      window.addEventListener('error', function (e) {
        var src = (e && e.filename) ? (' @ ' + e.filename + ':' + (e.lineno || 0)) : '';
        window.__staffBootError((e && e.message ? e.message : 'Script error') + src);
      });
      window.addEventListener('unhandledrejection', function (e) {
        window.__staffBootError('Unhandled rejection: ' + ((e && e.reason && (e.reason.message || e.reason)) || 'unknown'));
      });
      setTimeout(function () {
        var root = document.getElementById('root');
        if (root && root.childElementCount === 0) {
          window.__staffBootError('Bundle did not mount within 6s. Confirm the script URL loads (check the Network tab): <?php echo htmlspecialchars($asset_js, ENT_QUOTES); ?>?v=<?php echo (int)$v_js; ?>');
        }
      }, 6000);

      // Smooth page-to-page navigation: fade out before following same-tab links.
      var reduced = false;
      try { reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
      document.addEventListener('click', function (e) {
        if (reduced || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (a.target && a.target !== '' && a.target !== '_self') return;
        if (a.hasAttribute('download')) return;
        if (a.host && a.host !== window.location.host) return;
        var path = a.pathname || '';
        if (/\.pdf$/i.test(path) || /_pdf\.php$/i.test(path)) return;
        if (/^(mailto|tel):/i.test(href)) return;
        document.body.classList.add('__leaving');
        // Fall back to a normal navigation once the fade completes.
        setTimeout(function () { window.location.href = a.href; }, 220);
        e.preventDefault();
      }, true);
    })();
  </script>
  <script type="module" src="<?php echo htmlspecialchars($asset_js, ENT_QUOTES); ?>?v=<?php echo (int)$v_js; ?>"></script>
</body>
</html>
    <?php
}

/**
 * Build the identity block for an authenticated staff member.
 *
 * @param string $role 'admin' | 'doctor'
 */
function staff_identity(string $role): array
{
    if ($role === 'doctor') {
        return array(
            'role'     => 'doctor',
            'id'       => (int)($_SESSION['doctor_id'] ?? 0),
            'accountId'=> (int)($_SESSION['doctor_account_id'] ?? 0),
            'name'     => (string)($_SESSION['doctor_name'] ?? ''),
            'username' => (string)($_SESSION['doctor_username'] ?? ''),
            'mustChange'=> !empty($_SESSION['doctor_must_change']),
            'loginUrl' => 'doctor_login.php',
            'logoutUrl'=> 'doctor_logout.php',
            'changePasswordUrl' => 'doctor_change_password.php',
        );
    }
    return array(
        'role'     => 'admin',
        'id'       => (int)($_SESSION['admin_id'] ?? 0),
        'name'     => (string)($_SESSION['admin_name'] ?? ''),
        'username' => (string)($_SESSION['admin_username'] ?? ''),
        'mustChange'=> !empty($_SESSION['admin_must_change']),
        'loginUrl' => 'admin_login.php',
        'logoutUrl'=> 'admin_logout.php',
        'changePasswordUrl' => 'admin_change_password.php',
    );
}
