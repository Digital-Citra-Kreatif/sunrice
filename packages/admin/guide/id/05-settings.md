# Pengaturan

**Pengaturan** di bawah Administrasi mengumpulkan pilihan untuk seluruh situs, dalam beberapa bagian yang tercantum di kiri. Anda hanya melihat bagian yang boleh diubah peran Anda, dan setiap bagian disimpan sendiri dengan **Simpan**.

Panel selalu memiliki bagian berikut:

- **Branding**: nama, logo, jenis huruf, warna panel, dan halaman masuk. Lihat [Tampilan dan identitas merek](guide:panel/appearance).
- **Keamanan**: verifikasi dua langkah (di bawah).
- **Email**: cara panel mengirim email, dan email uji coba (di bawah).

Plugin menambahkan bagiannya sendiri. Bagian situs web (CMS) menambahkan **Situs**, **Bahasa**, **Kode situs**, dan **Komentar**: lihat [Pengaturan situs](guide:website/settings).

Nilai rahasia, seperti kata sandi layanan lain, tidak pernah ditampilkan lagi setelah disimpan: field-nya bertuliskan *Tersimpan. Biarkan kosong untuk mempertahankannya.*

## Keamanan: verifikasi dua langkah

Nyalakan **Verifikasi dua langkah** agar semua orang harus mengetik kode 6 angka, yang dikirim ke alamat email mereka, setelah kata sandi. Orang yang mengetahui kata sandi tetap tidak bisa masuk tanpa kotak surat pemilik akun.

> [!WARNING]
> Login dua langkah membutuhkan email yang berfungsi. Sebelum menyalakannya, kirim email uji coba ke diri sendiri dari **Pengaturan → Email**. Jika kode tidak bisa terkirim, tidak ada yang bisa masuk.

Mematikannya dicatat dan diemailkan kepada penerima peringatan keamanan, karena hal itu melemahkan semua akun. Jika developer Anda mengunci pengaturan ini untuk seluruh situs, saklar ini tidak berpengaruh.

> [!TECHNICAL]
> Terkunci karena email berhenti berfungsi? Di server: `php artisan sunrice:two-factor off`. Lihat [Signing in](docs:panel/auth#two-factor-login).

## Email

**Pengaturan → Email** menampilkan cara panel mengirim email: mailer, server, alamat pengirim (*Dari*), dan cara email antrean dikirim. Semua ini berasal dari konfigurasi server, jadi tidak ada yang diubah di panel, dan kata sandi tidak pernah ditampilkan.

**Kirim email uji** langsung mengirim pesan ke alamat email Anda sendiri dan memberi tahu apakah berhasil, atau pesan galat dari server email jika gagal. Gunakan setelah developer mengubah pengaturan email, dan sebelum menyalakan verifikasi dua langkah. Anda bisa mengirim tiga kali per menit.

Jika kartu ini memperingatkan bahwa email tidak benar-benar terkirim (misalnya mailer *log*), pesan hanya masuk ke berkas log: beri tahu developer Anda.

Panel mengirim tautan atur ulang kata sandi, kode masuk, peringatan keamanan, dan email uji coba; situs web menambahkan pemberitahuan formulir dan komentar.

> [!TECHNICAL]
> Email diatur di `.env` (`MAIL_MAILER`, `MAIL_HOST` …), dan email antrean membutuhkan *queue worker*. Lihat [Mail](docs:panel/mail).
