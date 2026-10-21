# Selamat datang di {panel}

Panduan ini untuk orang-orang yang bekerja di {panel} setiap hari: editor yang menulis dan menerbitkan konten, serta pengelola situs yang mengurus struktur, pengaturan, dan tim. Panduan ini menjelaskan susunan panel dan memandu Anda melalui setiap tugas. Anda tidak perlu mengerti kode sama sekali.

Apa yang Anda lihat di panel bergantung pada peran (*role*) Anda. Jika panduan ini menyebut layar yang tidak Anda temukan, berarti peran Anda tidak mencakupnya: tanyakan kepada pengelola situs.

Nama tombol dan menu ditulis seperti yang tampil di layar, dalam huruf tebal.

## Masuk

Buka alamat panel di peramban. Alamatnya adalah alamat situs Anda ditambah **/admin** (misalnya `https://example.com/admin`), kecuali developer Anda memilih alamat lain.

1. Di layar **Masuk ke {panel}**, ketik **Alamat email** dan **Kata sandi** Anda.
2. Centang **Ingat saya** di komputer Anda sendiri agar tetap masuk.
3. Klik **Masuk**.

Jika panel mengatakan email atau kata sandi Anda tidak cocok padahal Anda yakin keduanya benar, akun Anda mungkin belum diberi akses ke panel (panel tidak menyebutkan yang mana, agar tidak ada yang bisa memakainya untuk menguji kata sandi). Tanyakan kepada pengelola situs.

Setelah beberapa kali salah kata sandi, panel meminta Anda menunggu sebelum mencoba lagi. Ini melindungi akun Anda dari orang yang menebak-nebak kata sandi.

### Kode masuk

Situs Anda mungkin meminta langkah kedua setelah kata sandi (verifikasi dua langkah). Layar **Periksa email Anda** muncul, dan kode 6 angka dikirim ke alamat email Anda.

1. Buka email tersebut dan ketik kodenya di **Kode masuk**. Kode berlaku 10 menit.
2. Klik **Masuk**.

Email tidak datang? Periksa folder spam, lalu klik **Kirim kode baru** (bisa diminta sekali per menit) dan pakai kode dari email terbaru. **Kembali ke halaman masuk** memulai lagi dari awal.

### Lupa kata sandi?

1. Di layar masuk, klik **Lupa kata sandi?**.
2. Ketik **Alamat email** Anda dan klik **Kirim tautan reset lewat email**.
3. Buka email tersebut dan ikuti tautannya.
4. Ketik kata sandi baru di **Kata sandi baru**, ulangi di **Konfirmasi kata sandi baru**, lalu klik **Simpan kata sandi baru**.

Memilih kata sandi baru juga mengeluarkan Anda dari komputer lain tempat Anda mencentang *Ingat saya*.

### Akun Anda

Untuk mengubah nama, alamat email, atau kata sandi Anda sendiri, klik nama Anda di bagian bawah bilah samping dan pilih **Akun Anda**. Alamat email atau kata sandi baru memerlukan kata sandi Anda saat ini (**Kata sandi saat ini**), dan kata sandi baru diketik dua kali. Peran Anda diubah oleh pengelola situs.

Baru di {panel}? Pengelola situs mungkin sudah mengirimi Anda undangan: ikuti **Pilih kata sandi Anda** di email tersebut, ketik kata sandi Anda dua kali, lalu masuk. Tautannya berlaku untuk waktu terbatas; setelah itu, pakai **Lupa kata sandi?**.

## Mengenal tampilan panel

### Bilah samping

Bilah samping (*sidebar*) di kiri berisi semua yang diizinkan peran Anda, dalam beberapa kelompok:

- **Dashboard** dan **Media** di bagian atas.
- **Konten**: pekerjaan sehari-hari, seperti entri, term, global, menu, komentar, dan kiriman formulir.
- **Struktur**: cara konten disusun (koleksi, blueprint, taksonomi, formulir…), untuk pengelola situs.
- **Administrasi**: pengguna dan peran, pengaturan, log aktivitas, dan akses AI.
- **Bantuan**: panduan ini, serta dokumentasi developer untuk orang yang membangun situs.

Bagian atas bilah samping menampilkan nama dan logo panel; klik untuk kembali ke dashboard. Tombol di kiri atas halaman mengecilkan bilah samping menjadi ikon saja (arahkan kursor ke ikon untuk melihat namanya). Di ponsel, tombol yang sama membuka bilah samping.

### Buka…

Tekan **Ctrl K** (Windows) atau **⌘ K** (Mac), atau klik **Buka…** di bagian atas, untuk melompat ke mana saja: ketik sebagian nama halaman atau judul entri, lalu tekan Enter.

### Jejak halaman dan tombol kembali

Bagian atas halaman menunjukkan posisi Anda, misalnya **Blog › Tulisan pertama saya**. Klik bagian sebelumnya untuk naik kembali. Layar penyuntingan juga punya panah kembali di samping judulnya.

### Menu pengguna

Nama dan alamat email Anda ada di bagian bawah bilah samping. Klik untuk:

- Tampilan **Terang**, **Gelap**, atau **Sistem** (mengikuti komputer Anda).
- **Keluar**.

## Bekerja dengan aman

- **Menyimpan**: layar penyuntingan menampilkan *Perubahan belum disimpan* selama ada perubahan yang belum disimpan, dan bertanya dulu sebelum Anda meninggalkan halaman. **Ctrl S** (Windows) atau **⌘ S** (Mac) menyimpan.
- **Satu orang dalam satu waktu**: jika rekan Anda sedang menyunting sesuatu, panel memberi tahu siapa dan sejak kapan. Pilih **Kembali**, **Hanya lihat**, atau **Ambil alih**. Mengambil alih mengembalikan rekan Anda ke halaman sebelumnya; perubahan mereka yang belum disimpan tetap disimpan, dan orang berikutnya yang membuka item itu bisa memilih **Muat ke formulir** atau **Buang**.
- **Seseorang menyimpan lebih dulu**: jika rekan menyimpan item yang sama setelah Anda membukanya, panel memberi tahu saat Anda menyimpan. **Muat versi mereka** menampilkan versi mereka (perubahan Anda hilang); **Tetap simpan versi saya** menimpa versi mereka dengan versi Anda.
- **Halaman yang tidak bisa dibuka**: panel menampilkan halaman yang ramah, bukan pesan galat, jika halaman tidak ada atau peran Anda tidak mengizinkannya.

## Istilah dalam panduan ini

- **Entri** (*entries*) adalah halaman, artikel, produk, dan isi situs lainnya. Setiap entri termasuk dalam satu **koleksi** (*Pages*, *Blog*…).
- **Field** adalah kotak isian sebuah entri: judul, ringkasan, gambar. **Blueprint** menentukan field apa saja yang dimiliki sebuah koleksi.
- **Term** mengelompokkan entri berdasarkan topik, misalnya *Berita* dalam **taksonomi** bernama *Kategori*.
- **Global** adalah nilai yang dipakai bersama di seluruh situs, misalnya informasi kontak.
- **Menu** adalah navigasi situs.
- **Media** adalah berkas yang Anda unggah: gambar, dokumen, video.

## Isi panduan ini

- [Dashboard](guide:panel/dashboard)
- [Media](guide:panel/media)
- [Tampilan dan identitas merek](guide:panel/appearance)
- [Pengaturan](guide:panel/settings)
- [Pengguna dan peran](guide:panel/users-roles)
- [Akses AI](guide:panel/ai-access)
- [Log aktivitas dan keamanan](guide:panel/activity-log)

Jika situs Anda memakai CMS, bagian **Situs web** dari panduan ini menjelaskan [entri](guide:website/entries), [bahasa](guide:website/languages), [term](guide:website/terms), [global](guide:website/globals), [menu](guide:website/menus), [komentar](guide:website/comments), [kiriman formulir](guide:website/submissions), [SEO](guide:website/seo), [struktur](guide:website/structure), dan [pengaturan situs](guide:website/settings).

> [!TIP]
> Panduan ini juga tersedia dalam bahasa Inggris: gunakan pilihan bahasa di bagian atas halaman ini. Panel mengingat pilihan Anda.
