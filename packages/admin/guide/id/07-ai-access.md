# Akses AI

Asisten AI seperti Claude, ChatGPT, atau Cursor bisa bekerja di {panel} untuk Anda: menampilkan dan menyunting konten, mengisi field, mengunggah gambar, memeriksa SEO. Mereka terhubung dengan **token akses** yang Anda buat, dan bekerja **sebagai Anda**, dengan izin yang persis sama dengan Anda dan tidak lebih.

**Akses AI** ada di bawah Administrasi. Anda melihatnya jika peran Anda mencakup *Hubungkan agen AI*.

## Membuat token

1. Buka **Akses AI** dan klik **Token baru**.
2. Beri **Nama** sesuai asisten atau komputer yang memakainya (*Claude di laptop saya*), agar Anda tahu mana yang perlu dicabut nanti.
3. Centang **Hanya baca** jika asisten hanya boleh melihat, tidak mengubah apa pun.
4. Pilih kapan token **Kedaluwarsa**: dalam 30 hari, 90 hari (pilihan yang umum), satu tahun, atau **Tidak pernah**.
5. Klik **Buat token**.
6. **Salin** token saat itu juga: token hanya ditampilkan sekali. Siapa pun yang memegangnya bisa bekerja di panel sebagai Anda, jadi jaga seperti kata sandi.

Halaman lalu menunjukkan cara menghubungkan: perintah untuk **Claude Code**, dan pengaturan untuk **Claude Desktop, Cursor, VS Code … (konfigurasi JSON)**. Tempelkan token di tempat yang ditunjukkan.

## Apa yang bisa dilakukan asisten

- Hanya yang bisa Anda lakukan. Jika peran Anda tidak bisa menerbitkan, asisten Anda juga tidak.
- Dengan token hanya baca, hanya melihat, meskipun Anda administrator.
- Semua yang diubahnya dicatat di [log aktivitas](guide:panel/activity-log) atas nama Anda, ditandai **Agen AI** dengan nama tokennya.
- Asisten yang baik bertanya sebelum menghapus atau menerbitkan; tetap baca rencananya sebelum Anda menyetujui.

## Mencabut token

Daftar menampilkan setiap token, apakah hanya baca, kapan **Terakhir dipakai**, dan kapan **Kedaluwarsa**. Klik **Cabut** di samping token untuk menghentikannya seketika; asisten yang memakainya berhenti bekerja. Buat token baru jika masih diperlukan.

Token juga berhenti berfungsi jika:

- sudah kedaluwarsa;
- akun Anda dikunci, atau pengelola situs memakai **Keluarkan dari semua perangkat** atau **Cabut token AI** pada akun Anda;
- peran Anda tidak lagi mengizinkan panel atau akses AI.

> [!WARNING]
> Jika token mungkin telah disalin orang lain, segera cabut. Panel juga mengirim email kepada penerima peringatan keamanan saat token baru dibuat dan saat token dipakai dari alamat baru.

> [!TECHNICAL]
> Asisten terhubung ke endpoint MCP (`/mcp`). Token juga bisa dibuat di server dengan `php artisan sunrice:ai-token`. Lihat [AI access](docs:panel/ai).
