// Global loading-screen helpers matching the PHP pages' showLoading/hideLoading.

export function showLoading(): void {
  document.getElementById('loadingScreen')?.classList.add('active');
}

export function hideLoading(): void {
  document.getElementById('loadingScreen')?.classList.remove('active');
}

export function escapeHtml(s: unknown): string {
  return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string),
  );
}
