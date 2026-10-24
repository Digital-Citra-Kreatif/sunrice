# Pengguna dan peran

Setiap orang yang bekerja di {panel} memiliki akun **pengguna** (*user*), dan setiap akun memiliki satu atau beberapa **peran** (*role*). Peran adalah sekumpulan izin, seperti *menerbitkan entri* atau *mengunggah media*, yang menentukan apa yang dilihat dan boleh dilakukan orang tersebut. **Pengguna** dan **Peran** ada di bawah Administrasi, untuk pengelola situs.

## Pengguna

Daftar **Pengguna** menampilkan nama, alamat email, peran, apakah akun **Dikunci**, dan kapan akun dibuat. Cari berdasarkan nama atau email, atau saring berdasarkan peran.

### Menambahkan orang

1. Klik **Tambah pengguna**.
2. Di bagian **Akun**, ketik **Nama** dan **Email**. Lalu ketik **Kata sandi** dan sekali lagi di **Konfirmasi kata sandi**, atau biarkan kosong dan nyalakan **Kirim undangan lewat email**: mereka menerima email berisi tautan untuk memilih kata sandi sendiri. Kata sandi harus memenuhi aturan kata sandi situs (lihat [Pengaturan](guide:panel/settings#keamanan-kata-sandi)); daftar periksa di bawahnya menunjukkan apa yang masih kurang.
3. Di bagian **Peran**, centang perannya.
4. Klik **Buat pengguna**. Tanpa undangan, beri tahu mereka alamat panel dan kata sandinya.

Untuk mengirim tautan baru untuk memilih kata sandi (misalnya undangan yang sudah kedaluwarsa), pakai **Kirim tautan kata sandi lewat email** di menu baris.

Peran yang bisa Anda berikan hanya yang berada di bawah peran Anda sendiri (lihat [Siapa boleh mengelola siapa](#siapa-boleh-mengelola-siapa)).

### Mengubah data seseorang

Klik pengguna untuk mengubah nama, email, kata sandi, atau perannya. Biarkan **Kata sandi** kosong untuk mempertahankan kata sandi saat ini; kata sandi baru diketik dua kali. Di samping formulir, kartu **Keamanan** menampilkan apakah akun terkunci (kapan, oleh siapa, dan mengapa), kapan terakhir masuk dan dari mana, berapa token akses AI yang dimiliki, apa saja miliknya (**Miliknya**: unggahan, entri…), dan kejadian keamanan terbarunya, dengan **Lihat semua di log aktivitas**.

Setiap orang mengubah nama, email, dan kata sandinya sendiri di **Akun Anda** (di bawah namanya di bagian bawah bilah samping; lihat [Selamat datang](guide:panel/welcome#akun-anda)). Tidak ada yang bisa mengubah perannya sendiri atau menghapus akunnya sendiri.

## Jika akun mungkin jatuh ke tangan yang salah

Jika kata sandi mungkin bocor, laptop hilang, atau seseorang keluar dengan tidak baik-baik, gunakan menu di ujung baris pengguna (atau kartu Keamanan):

- **Keluarkan dari semua perangkat** mengakhiri semua sesinya di semua komputer, dan mencabut token akses AI-nya. Orang itu bisa masuk lagi dengan kata sandinya, jadi ganti dulu kata sandinya jika mungkin sudah diketahui orang lain.
- **Kunci akun** mengeluarkannya dari mana saja dan mencegahnya masuk sama sekali, sampai ada yang membuka kuncinya. Tambahkan alasan bila perlu, misalnya *Kata sandi mungkin bocor*; alasan ini tampil di halamannya. Tidak ada yang dihapus.
- **Buka kunci akun** mengizinkannya masuk lagi. Ini juga membuka kunci akibat terlalu banyak salah kata sandi.
- **Cabut token AI** menghentikan semua asisten AI yang terhubung dengan akunnya.

Setiap tindakan ini dicatat sebagai kejadian keamanan di [log aktivitas](guide:panel/activity-log).

## Menghapus pengguna

**Hapus…** (di menu baris, tombol tempat sampah di halamannya, atau untuk beberapa pengguna yang dicentang sekaligus) menanyakan apa yang terjadi pada hasil kerjanya (entri, komentar, dan unggahan):

- **Alihkan ke pengguna lain**: dialihkan kepada pengguna lain, dipilih di **Alihkan ke** (pengguna yang sedang dihapus tidak ditawarkan).
- **Simpan, tanpa pemilik**: tetap ada tanpa pemilik (pilihan bawaan).
- **Hapus**: entri dan komentar masuk tempat sampah; unggahan tetap ada, karena mungkin dipakai halaman lain.

Di halaman pengguna, pertanyaannya juga menyebutkan apa yang dimilikinya. Peran, token AI, dan status kuncinya ikut terhapus. Jika ragu, pilih **Kunci akun**: tindakan itu bisa dibatalkan.

## Peran

Daftar **Peran** menampilkan setiap peran dan jumlah izinnya. Klik **Tambah peran**, beri **Nama**, lalu centang **Izin**-nya, yang dikelompokkan per bidang (panel, media, konten, entri setiap koleksi, pengaturan…). Setiap bidang berupa tabel kecil: satu baris per hal (Entri, Term, Komentar…) dan satu kolom per tindakan (**Lihat**, **Buat**, **Ubah**, **Terbitkan**, **Moderasi**, **Hapus**, **Ekspor**), dengan kotak hanya di tempat tindakan itu berlaku. Baris **… milik sendiri saja** di bawah Entri membatasi mengubah atau membuang ke tempat sampah pada entri milik orang itu sendiri, dan tindakan tanpa kolom berupa pilihan kecil di baris **Juga** di bawah barisnya (**Terjemahkan**, **Lihat draf di situs**) atau di baris itu sendiri (**Pilih beranda**). Arahkan kursor ke kotak atau pilihan untuk membaca izinnya secara lengkap. Kotak di depan bidang atau baris mencentang semuanya; bidang bisa dilipat (yang punya centang terbuka sejak awal) dan menyebutkan berapa izinnya yang dicentang. Di ponsel atau jendela yang sempit, setiap bidang berupa daftar biasa.

Mengunduh daftar sebagai spreadsheet (**Ekspor CSV**) adalah izin tersendiri, terpisah dari melihat daftar, karena ekspor membawa data keluar dari panel.

Situs Anda mungkin sudah memiliki peran siap pakai sebagai titik awal:

| Peran | Untuk |
| --- | --- |
| **administrator** | Semuanya, bagi orang yang menjalankan situs. |
| **editor** | Semua konten: entri, term, nilai global, tautan menu, komentar, dan media. Tidak termasuk **Pengaturan** sebuah set global atau menu (nama, handle, dan field-nya), serta membuat atau menghapus menu. Juga tidak termasuk kiriman formulir, karena berisi data pribadi pengunjung: centang *Lihat kiriman formulir* (atau *Lihat kiriman* di grup *Kiriman:* sebuah formulir) bagi mereka yang menjawabnya. Tidak termasuk struktur, pengaturan, atau pengguna. |
| **author** | Menulis entri di setiap koleksi dan mengubah entrinya sendiri, mengunggah media, dan melihat draf di situs. Tidak bisa menerbitkan. Untuk membatasi penulis ke beberapa koleksi saja (misalnya hanya *Jurnal*), hapus centang *Lihat entri* dan *Buat entri*, lalu centang **Lihat** dan **Buat** di grup *Entri:* koleksi tersebut. |
| **translator** | Menulis bahasa lain dari entri, di setiap koleksi yang boleh dilihatnya. Untuk membatasi penerjemah ke beberapa koleksi saja, beri peran itu **Lihat** di grup *Entri:* koleksi tersebut, bukan *Lihat entri*. |

Setiap peran yang bisa membuka panel juga bisa membaca panduan ini.

Silakan ubah peran siap pakai ini sesuai kebutuhan. Peran yang masih diberikan kepada seseorang tidak bisa dihapus: cabut dulu dari orang tersebut.

> [!NOTE]
> Beberapa izin memberi kuasa atas seluruh situs: mengelola pengguna, peran, dan pengaturan, menghubungkan asisten AI, dan log aktivitas (serta, di situs web, kode situs dan blueprint). Setiap kali seseorang mendapatkan salah satunya, penerima peringatan keamanan menerima email.

## Siapa boleh mengelola siapa

Tidak ada yang bisa memberikan akses lebih dari yang ia miliki. Anda hanya boleh mengelola pengguna atau peran yang **di bawah** Anda: semua yang boleh ia lakukan juga boleh Anda lakukan, dan Anda boleh melakukan setidaknya satu hal lagi.

- Anda hanya melihat peran di bawah Anda, dan hanya izin yang Anda miliki sendiri.
- Orang dengan akses yang sama dengan Anda, dan super admin, berada di luar jangkauan Anda.
- Super admin boleh melakukan segalanya dan mengelola semua orang. Peran super admin sendiri tidak bisa diubah atau dihapus.

> [!TECHNICAL]
> Super admin pertama dibuat di server dengan `php artisan sunrice:make-admin`, dan peran siap pakai dengan `php artisan sunrice:roles`. Lihat [Roles and permissions](docs:panel/permissions) dan [Users and roles](docs:panel/users).
