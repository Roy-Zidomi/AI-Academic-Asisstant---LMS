# Tel-U AI LMS Theme (`theme_teluai`)

Custom Moodle child theme dari Boost untuk AI Academic Assistant LMS.

## Public Landing Page

Frontpage publik dirender melalui:

- `layout/frontpage.php`
- `templates/landing.mustache`
- partial template `templates/landing_*.mustache`
- SCSS modular: `scss/navbar.scss`, `scss/components.scss`, `scss/landing.scss`, `scss/responsive.scss`

Landing page tidak mengambil atau menampilkan data course Moodle. Tidak ada query enrolled courses, available courses, atau course publik. Course hanya muncul setelah user login sesuai role dan enrollment.

## Logo

Logo Telkom University resmi digunakan dari:

`pix/logo-telkom-university.png`

File ini dipakai langsung oleh landing navbar, hero/final CTA badge, dan footer.

## Cache

Setelah mengubah theme:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Pada environment Docker project ini, jalankan melalui container Moodle:

```bash
docker compose exec -u daemon moodle bash -lc "cd /opt/bitnami/moodle && /opt/bitnami/php/bin/php admin/cli/upgrade.php --non-interactive"
docker compose exec -u daemon moodle bash -lc "cd /opt/bitnami/moodle && /opt/bitnami/php/bin/php admin/cli/purge_caches.php"
```
