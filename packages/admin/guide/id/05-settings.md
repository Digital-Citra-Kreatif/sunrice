# Pengaturan

**Settings** di bawah Administration mengumpulkan pilihan untuk seluruh situs, dalam beberapa bagian yang tercantum di kiri. Anda hanya melihat bagian yang boleh diubah peran Anda, dan setiap bagian disimpan sendiri dengan **Save**.

Panel selalu memiliki bagian berikut:

- **Branding**: nama, logo, jenis huruf, warna panel, dan halaman masuk. Lihat [Tampilan dan identitas merek](guide:panel/appearance).
- **Security**: login dua langkah (di bawah).
- **Mail**: cara panel mengirim email, dan email uji coba (di bawah).

Plugin menambahkan bagiannya sendiri. Bagian situs web (CMS) menambahkan **Site**, **Languages**, **Site code**, dan **Comments**: lihat [Pengaturan situs](guide:website/settings).

Nilai rahasia, seperti kata sandi layanan lain, tidak pernah ditampilkan lagi setelah disimpan: field-nya bertuliskan *Saved. Leave empty to keep it.* (tersimpan; kosongkan untuk mempertahankannya).

## Security: login dua langkah

Nyalakan **Two-factor login** agar semua orang harus mengetik kode 6 angka, yang dikirim ke alamat email mereka, setelah kata sandi. Orang yang mengetahui kata sandi tetap tidak bisa masuk tanpa kotak surat pemilik akun.

> [!WARNING]
> Login dua langkah membutuhkan email yang berfungsi. Sebelum menyalakannya, kirim email uji coba ke diri sendiri dari **Settings → Mail**. Jika kode tidak bisa terkirim, tidak ada yang bisa masuk.

Mematikannya dicatat dan diemailkan kepada penerima peringatan keamanan, karena hal itu melemahkan semua akun. Jika developer Anda mengunci pengaturan ini untuk seluruh situs, saklar ini tidak berpengaruh.

> [!TECHNICAL]
> Terkunci karena email berhenti berfungsi? Di server: `php artisan sunrice:two-factor off`. Lihat [Signing in](docs:panel/auth#two-factor-login).

## Mail

**Settings → Mail** menampilkan cara panel mengirim email: mailer, server, alamat *From* (pengirim), dan cara email antrean dikirim. Semua ini berasal dari konfigurasi server, jadi tidak ada yang diubah di panel, dan kata sandi tidak pernah ditampilkan.

**Send a test email** langsung mengirim pesan ke alamat email Anda sendiri dan memberi tahu apakah berhasil, atau pesan galat dari server email jika gagal. Gunakan setelah developer mengubah pengaturan email, dan sebelum menyalakan login dua langkah. Anda bisa mengirim tiga kali per menit.

Jika kartu ini memperingatkan bahwa email tidak benar-benar terkirim (misalnya mailer *log*), pesan hanya masuk ke berkas log: beri tahu developer Anda. Kartu ini juga memperingatkan selama email dikirim dari alamat contoh seperti *hello@example.com*: balasan tidak sampai ke mana pun dan filter spam bisa membuang emailnya, jadi minta developer memasang alamat Anda sendiri.

Panel mengirim tautan atur ulang kata sandi, kode masuk, peringatan keamanan, dan email uji coba; situs web menambahkan pemberitahuan formulir dan komentar.

> [!TECHNICAL]
> Email diatur di `.env` (`MAIL_MAILER`, `MAIL_HOST` …), dan email antrean membutuhkan *queue worker*. Lihat [Mail](docs:panel/mail).
