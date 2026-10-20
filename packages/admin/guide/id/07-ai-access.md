# Akses AI

Asisten AI seperti Claude, ChatGPT, atau Cursor bisa bekerja di {panel} untuk Anda: menampilkan dan menyunting konten, mengisi field, mengunggah gambar, memeriksa SEO. Mereka terhubung dengan **token akses** yang Anda buat, dan bekerja **sebagai Anda**, dengan izin yang persis sama dengan Anda dan tidak lebih.

**AI access** ada di bawah Administration. Anda melihatnya jika peran Anda mencakup *Connect AI agents*.

## Membuat token

1. Buka **AI access** dan klik **New token**.
2. Beri **Name** sesuai asisten atau komputer yang memakainya (*Claude di laptop saya*), agar Anda tahu mana yang perlu dicabut nanti.
3. Centang **Read-only** jika asisten hanya boleh melihat, tidak mengubah apa pun.
4. Pilih kapan token **Expires** (kedaluwarsa): dalam 30 hari, 90 hari (pilihan yang umum), satu tahun, atau **Never** (tidak pernah).
5. Klik **Create token**.
6. **Copy** token saat itu juga: token hanya ditampilkan sekali. Siapa pun yang memegangnya bisa bekerja di panel sebagai Anda, jadi jaga seperti kata sandi.

Halaman lalu menunjukkan cara menghubungkan: perintah untuk **Claude Code**, dan pengaturan untuk **Claude Desktop, Cursor and other MCP clients**. Tempelkan token di tempat yang ditunjukkan.

## Apa yang bisa dilakukan asisten

- Hanya yang bisa Anda lakukan. Jika peran Anda tidak bisa menerbitkan, asisten Anda juga tidak.
- Dengan token read-only, hanya melihat, meskipun Anda administrator.
- Semua yang diubahnya dicatat di [log aktivitas](guide:panel/activity-log) atas nama Anda, ditandai **AI agent** dengan nama tokennya.
- Asisten yang baik bertanya sebelum menghapus atau menerbitkan; tetap baca rencananya sebelum Anda menyetujui.

## Mencabut token

Daftar menampilkan setiap token, apakah read-only, kapan **Last used** (terakhir dipakai), dan kapan **Expires**. Klik **Revoke** di samping token untuk menghentikannya seketika; asisten yang memakainya berhenti bekerja. Buat token baru jika masih diperlukan.

Token juga berhenti berfungsi jika:

- sudah kedaluwarsa;
- akun Anda dikunci, atau pengelola situs memakai **Sign out everywhere** atau **Revoke AI tokens** pada akun Anda;
- peran Anda tidak lagi mengizinkan panel atau akses AI.

> [!WARNING]
> Jika token mungkin telah disalin orang lain, segera cabut. Panel juga mengirim email kepada penerima peringatan keamanan saat token baru dibuat dan saat token dipakai dari alamat baru.

> [!TECHNICAL]
> Asisten terhubung ke endpoint MCP (`/mcp`). Token juga bisa dibuat di server dengan `php artisan sunrice:ai-token`. Lihat [AI access](docs:panel/ai).
