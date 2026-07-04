# ============================================================
# AI-AA LMS — Sync Theme ke Docker Container
# Jalankan setiap kali selesai edit file di folder moodle/
# ============================================================
#
# CARA PAKAI:
#   1. Buka PowerShell di folder project ini
#   2. Ketik: .\sync-theme.ps1
#   3. Tunggu selesai, lalu Ctrl+Shift+R di browser
#
# ============================================================

Write-Host "==> Menyalin file tema ke container..." -ForegroundColor Cyan
docker cp moodle/theme/teluai/. aaalms-moodle:/opt/bitnami/moodle/theme/teluai/

Write-Host "==> Memperbaiki permissions..." -ForegroundColor Cyan
docker exec aaalms-moodle chown -R daemon:daemon /opt/bitnami/moodle/theme/teluai

Write-Host "==> Membersihkan cache Moodle..." -ForegroundColor Cyan
docker exec aaalms-moodle gosu daemon php /opt/bitnami/moodle/admin/cli/purge_caches.php

Write-Host ""
Write-Host "✅ Selesai! Refresh browser dengan Ctrl+Shift+R" -ForegroundColor Green
