# Selamat datang di {panel}

Panduan ini untuk orang-orang yang bekerja di {panel} setiap hari: editor yang menulis dan menerbitkan konten, serta pengelola situs yang mengurus struktur, pengaturan, dan tim. Panduan ini menjelaskan susunan panel dan memandu Anda melalui setiap tugas. Anda tidak perlu mengerti kode sama sekali.

Apa yang Anda lihat di panel bergantung pada peran (*role*) Anda. Jika panduan ini menyebut layar yang tidak Anda temukan, berarti peran Anda tidak mencakupnya: tanyakan kepada pengelola situs.

Tampilan panel berbahasa Inggris, jadi nama tombol dan menu ditulis seperti yang tampil di layar, dalam huruf tebal.

## Masuk

Buka alamat panel di peramban. Alamatnya adalah alamat situs Anda ditambah **/admin** (misalnya `https://example.com/admin`), kecuali developer Anda memilih alamat lain.

1. Di layar **Log in to {panel}**, ketik **Email address** (alamat email) dan **Password** (kata sandi) Anda.
2. Centang **Remember me** di komputer Anda sendiri agar tetap masuk.
3. Klik **Log in**.

Jika panel menampilkan *This account can't use the admin panel*, akun Anda ada tetapi belum diberi akses. Tanyakan kepada pengelola situs.

Setelah beberapa kali salah kata sandi, panel meminta Anda menunggu sebelum mencoba lagi. Ini melindungi akun Anda dari orang yang menebak-nebak kata sandi.

### Kode masuk

Situs Anda mungkin meminta langkah kedua setelah kata sandi (login dua langkah). Layar **Check your email** muncul, dan kode 6 angka dikirim ke alamat email Anda.

1. Buka email tersebut dan ketik kodenya di **Login code**. Kode berlaku 10 menit.
2. Klik **Log in**.

Email tidak datang? Periksa folder spam, lalu klik **Send a new code** (bisa diminta sekali per menit) dan pakai kode dari email terbaru. **Back to log in** memulai lagi dari awal.

### Lupa kata sandi?

1. Di layar masuk, klik **Forgot password?**.
2. Ketik **Email address** Anda dan klik **Email the reset link**.
3. Buka email tersebut dan ikuti tautannya.
4. Ketik kata sandi baru di **New password**, ulangi di **Confirm the new password**, lalu klik **Save the new password**.

Memilih kata sandi baru juga mengeluarkan Anda dari komputer lain tempat Anda mencentang *Remember me*.

Tidak ada halaman profil: untuk mengubah nama atau alamat email, mintalah kepada pengelola situs.

## Mengenal tampilan panel

### Bilah samping

Bilah samping (*sidebar*) di kiri berisi semua yang diizinkan peran Anda, dalam beberapa kelompok:

- **Dashboard** dan **Media** di bagian atas.
- **Content**: pekerjaan sehari-hari, seperti entri, term, global, menu, komentar, dan kiriman formulir.
- **Structure**: cara konten disusun (koleksi, blueprint, taksonomi, formulir…), untuk pengelola situs.
- **Administration**: pengguna dan peran, pengaturan, log aktivitas, dan akses AI.
- **Help**: panduan ini, serta dokumentasi developer untuk orang yang membangun situs.

Bagian atas bilah samping menampilkan nama dan logo panel; klik untuk kembali ke dashboard. Tombol di kiri atas halaman mengecilkan bilah samping menjadi ikon saja (arahkan kursor ke ikon untuk melihat namanya). Di ponsel, tombol yang sama membuka bilah samping.

### Go to…

Tekan **Ctrl K** (Windows) atau **⌘ K** (Mac), atau klik **Go to…** di bagian atas, untuk melompat ke mana saja: ketik sebagian nama halaman atau judul entri, lalu tekan Enter.

### Jejak halaman dan tombol kembali

Bagian atas halaman menunjukkan posisi Anda, misalnya **Blog › Tulisan pertama saya**. Klik bagian sebelumnya untuk naik kembali. Layar penyuntingan juga punya panah kembali di samping judulnya.

### Menu pengguna

Nama dan alamat email Anda ada di bagian bawah bilah samping. Klik untuk:

- Tampilan **Light** (terang), **Dark** (gelap), atau **System** (mengikuti komputer Anda).
- **Log out** (keluar).

## Bekerja dengan aman

- **Menyimpan**: layar penyuntingan menampilkan *Unsaved changes* selama ada perubahan yang belum disimpan, dan bertanya dulu sebelum Anda meninggalkan halaman. **Ctrl S** (Windows) atau **⌘ S** (Mac) menyimpan.
- **Satu orang dalam satu waktu**: jika rekan Anda sedang menyunting sesuatu, panel memberi tahu siapa dan sejak kapan. Pilih **Go back** (kembali), **View only** (hanya membaca), atau **Take over** (mengambil alih). Mengambil alih mengembalikan rekan Anda ke halaman sebelumnya; perubahan mereka yang belum disimpan tetap disimpan, dan orang berikutnya yang membuka item itu bisa memilih **Load into the form** (muat ke formulir) atau **Discard** (buang).
- **Seseorang menyimpan lebih dulu**: jika rekan menyimpan item yang sama setelah Anda membukanya, panel memberi tahu saat Anda menyimpan. **Load theirs** menampilkan versi mereka (perubahan Anda hilang); **Save mine anyway** menimpa versi mereka dengan versi Anda.
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

Jika situs Anda memakai CMS, bagian **Website** dari panduan ini menjelaskan [entri](guide:website/entries), [bahasa](guide:website/languages), [term](guide:website/terms), [global](guide:website/globals), [menu](guide:website/menus), [komentar](guide:website/comments), [kiriman formulir](guide:website/submissions), [SEO](guide:website/seo), [struktur](guide:website/structure), dan [pengaturan situs](guide:website/settings).

> [!TIP]
> Panduan ini juga tersedia dalam bahasa Inggris: gunakan pilihan bahasa di bagian atas halaman ini. Panel mengingat pilihan Anda.
