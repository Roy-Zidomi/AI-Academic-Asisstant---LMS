# Tel-U AI Login Theme (`theme_teluai`)

Custom Moodle theme for the AI-AA LMS login page and home/course landing page. It is a Boost child theme and does not modify Moodle core.

## Analisis Masalah

- Login page sebelumnya masih terasa seperti Moodle default: konten berurutan dari atas ke bawah, tanpa layout dua kolom.
- Input melebar penuh mengikuti viewport, bukan dibatasi dalam card login.
- Area guest access terlalu dominan karena tampil sebagai heading besar dan button full-width.
- Spacing, hierarchy, dan visual branding belum mencerminkan LMS SaaS modern.
- Akar teknis yang sering memicu tampilan polos adalah SCSS child theme yang tidak ter-load/ter-compile dengan benar. `lib.php` sekarang membaca preset Boost parent secara eksplisit, lalu menambahkan SCSS login yang scoped.

## Konsep Desain

- Split-screen desktop:
  - Kiri: hero branding AI-AA LMS dengan headline `Smart Learning, Powered by AI`.
  - Kanan: modern login card dengan headline `Welcome Back`.
- Warna utama:
  - Red: `#ED1C24`
  - Dark red: `#B01116`
  - Soft red: `#FDE7E8`
  - Background: `#F8FAFC`
- Typography: `Inter`, lalu fallback ke `system-ui`.
- Card login: radius `24px`, soft shadow, padding lega.
- Input: tinggi `48px`, radius `12px`, focus ring merah lembut.
- Primary button: merah, full width, tinggi `48px`.
- Guest access: secondary, kecil, dashed container, tidak bersaing dengan login utama.
- Mobile: hero kiri disembunyikan, branding ringkas muncul di atas card.

## Wireframe

```text
Desktop
+--------------------------------------------------+----------------------------------+
| AI-AA LMS / AI-Powered Learning                  |                                  |
|                                                  |          Welcome Back card       |
| Smart Learning, Powered by AI                    |   +----------------------------+ |
| Moodle + AI learning description                 |   | AI-AA LMS                  | |
|                                                  |   | Welcome Back               | |
| [AI Assistant]                                   |   | Username                   | |
| [Material Summarizer]                            |   | Password                   | |
| [Quiz Generator]                                 |   | Remember / Forgot          | |
|                                                  |   | [ Log in ]                 | |
| Copyright                                        |   | Guest access subtle        | |
+--------------------------------------------------+----------------------------------+

Mobile
+----------------------------------+
| AI-AA LMS / AI-Powered Learning  |
| +------------------------------+ |
| | Welcome Back                 | |
| | Username                     | |
| | Password                     | |
| | [ Log in ]                   | |
| | Guest access subtle          | |
| +------------------------------+ |
+----------------------------------+
```

## File Theme

```text
moodle/theme/teluai/
|-- config.php
|-- version.php
|-- lib.php
|-- settings.php
|-- layout/
|   |-- login.php
|   `-- frontpage.php
|-- templates/
|   |-- login.mustache
|   |-- frontpage.mustache
|   `-- core/
|       `-- loginform.mustache
|-- scss/
|   |-- home.scss
|   `-- login.scss
|-- pix/
|   `-- logo.svg
`-- lang/en/theme_teluai.php
```

## Catatan Implementasi

- `config.php` mendaftarkan layout `login` ke `layout/login.php`.
- `config.php` mendaftarkan layout `frontpage` ke `layout/frontpage.php`.
- `layout/login.php` merender `theme_teluai/login`.
- `layout/frontpage.php` merender `theme_teluai/frontpage`.
- `templates/login.mustache` membuat split-screen wrapper dan login card.
- `templates/frontpage.mustache` membuat navbar SaaS, hero, AI shortcuts, sidebar, dan wrapper course content.
- `templates/core/loginform.mustache` override form login Moodle tanpa mengubah autentikasi core.
- `scss/login.scss` seluruhnya scoped ke `body.telu-page-login`.
- `scss/home.scss` seluruhnya scoped ke `body.telu-page-home`.
- `lib.php` memuat Boost preset + SCSS scoped untuk login dan home.

## Cara Install

1. Pastikan folder berada di:

   ```text
   moodle/theme/teluai
   ```

2. Login sebagai admin Moodle.
3. Buka `Site administration > Notifications` untuk menjalankan upgrade plugin.
4. Aktifkan theme:

   ```text
   Site administration > Appearance > Themes > Theme selector
   ```

5. Pilih `Tel-U AI LMS Theme`.

## Purge Cache

Via UI:

```text
Site administration > Development > Purge caches > Purge all caches
```

Via CLI dari root Moodle:

```bash
php admin/cli/purge_caches.php
```

Jika masih polos, aktifkan sementara theme designer mode:

```text
Site administration > Appearance > Themes > Theme settings > Theme designer mode
```

Lalu purge cache lagi.

## Checklist Testing

- Desktop 1366px dan 1920px: layout split-screen tampil, hero kiri 1 kolom dan card kanan tidak melebar.
- Mobile 360px/390px/430px: hero kiri hilang, card penuh tetapi tetap punya padding.
- Input username/password tinggi 48px dan focus ring merah.
- Button login merah, full width, hover menjadi `#B01116`.
- Guest access kecil/subtle, bukan heading dominan.
- Lost password, cookies notice, language menu, dan identity provider tetap muncul jika aktif.
- Login valid dan login invalid tetap memakai flow Moodle core.
- Purge cache tidak menghilangkan styling custom.

## Checklist Testing Home

- Navbar sticky tampil modern, active state merah, search bar tidak overflow.
- Hero menampilkan badge, headline, subtitle, CTA, statistik, dan visual AI.
- AI Learning Tools tampil 3 kolom desktop, 2 kolom tablet, 1 kolom mobile.
- Moodle tabs tampil sebagai segmented navigation dengan underline merah.
- Available Courses memiliki header, subtitle, filter kecil, dan coursebox modern.
- Course card memiliki gradient header, badge, lecturer styling, progress text, radius, hover shadow.
- Sidebar desktop tampil; di tablet/mobile turun menjadi card grid/satu kolom.
- Floating help button merah dan mobile friendly.
- Edit mode Moodle tetap dapat dipakai.
