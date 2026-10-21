# Pengguna dan peran

Setiap orang yang bekerja di {panel} memiliki akun **pengguna** (*user*), dan setiap akun memiliki satu atau beberapa **peran** (*role*). Peran adalah sekumpulan izin, seperti *menerbitkan entri* atau *mengunggah media*, yang menentukan apa yang dilihat dan boleh dilakukan orang tersebut. **Users** dan **Roles** ada di bawah Administration, untuk pengelola situs.

## Pengguna

Daftar **Users** menampilkan nama, alamat email, peran, apakah akun **Locked** (terkunci), dan kapan akun dibuat. Cari berdasarkan nama atau email, atau saring berdasarkan peran.

### Menambahkan orang

1. Klik **New user**.
2. Di bagian **Account**, ketik **Name**, **Email**, dan **Password**, lalu kata sandi yang sama di **Confirm password** (mereka bisa memilih kata sandi baru nanti lewat *Forgot password?* di layar masuk).
3. Di bagian **Roles**, centang perannya.
4. Klik **Create user**, lalu beri tahu mereka alamat panel.

Peran yang bisa Anda berikan hanya yang berada di bawah peran Anda sendiri (lihat [Siapa boleh mengelola siapa](#siapa-boleh-mengelola-siapa)).

### Mengubah data seseorang

Klik pengguna untuk mengubah nama, email, kata sandi, atau perannya. Biarkan **Password** kosong untuk mempertahankan kata sandi saat ini; kata sandi baru diketik dua kali. Di samping formulir, kartu **Security** menampilkan apakah akun terkunci (kapan, oleh siapa, dan mengapa), kapan terakhir masuk dan dari mana, berapa token akses AI yang dimiliki, apa saja miliknya (**Owns**: unggahan, entri…), dan kejadian keamanan terbarunya, dengan **See all in the activity log**.

Setiap orang boleh mengubah nama, email, dan kata sandinya sendiri jika bisa membuka layar Users, tetapi tidak ada yang bisa mengubah perannya sendiri atau menghapus akunnya sendiri.

## Jika akun mungkin jatuh ke tangan yang salah

Jika kata sandi mungkin bocor, laptop hilang, atau seseorang keluar dengan tidak baik-baik, gunakan menu di ujung baris pengguna (atau kartu Security):

- **Sign out everywhere** mengakhiri semua sesinya di semua komputer, dan mencabut token akses AI-nya. Orang itu bisa masuk lagi dengan kata sandinya, jadi ganti dulu kata sandinya jika mungkin sudah diketahui orang lain.
- **Lock account** mengeluarkannya dari mana saja dan mencegahnya masuk sama sekali, sampai ada yang membuka kuncinya. Tambahkan alasan bila perlu, misalnya *Kata sandi mungkin bocor*; alasan ini tampil di halamannya. Tidak ada yang dihapus.
- **Unlock account** mengizinkannya masuk lagi. Ini juga membuka kunci akibat terlalu banyak salah kata sandi.
- **Revoke AI tokens** menghentikan semua asisten AI yang terhubung dengan akunnya.

Setiap tindakan ini dicatat sebagai kejadian keamanan di [log aktivitas](guide:panel/activity-log).

## Menghapus pengguna

**Delete…** (di menu baris, tombol tempat sampah di halamannya, atau untuk beberapa pengguna yang dicentang sekaligus) menanyakan apa yang terjadi pada hasil kerjanya (entri, komentar, dan unggahan):

- **Give it to another user**: diberikan kepada pengguna lain, dipilih di **Give it to**.
- **Keep it, without an owner**: tetap ada tanpa pemilik (pilihan bawaan).
- **Delete it**: entri dan komentar masuk tempat sampah; unggahan tetap ada, karena mungkin dipakai halaman lain.

Peran, token AI, dan status kuncinya ikut terhapus. Jika ragu, pilih **Lock account**: tindakan itu bisa dibatalkan.

## Peran

Daftar **Roles** menampilkan setiap peran dan jumlah izinnya. Klik **New role**, beri **Name**, lalu centang **Permissions**-nya, yang dikelompokkan per bidang (panel, media, entri setiap koleksi, pengaturan…).

Mengunduh daftar sebagai spreadsheet (**Export CSV**) adalah izin tersendiri, terpisah dari melihat daftar, karena ekspor membawa data keluar dari panel.

Situs Anda mungkin sudah memiliki peran siap pakai sebagai titik awal:

| Peran | Untuk |
| --- | --- |
| **administrator** | Semuanya, bagi orang yang menjalankan situs. |
| **editor** | Semua konten: entri, term, global, menu, komentar, media, serta membaca dan mengekspor kiriman formulir. Tidak termasuk struktur, pengaturan, atau pengguna. |
| **author** | Menulis entri dan mengubah entrinya sendiri, mengunggah media, dan melihat draf di situs. Tidak bisa menerbitkan. |
| **translator** | Menulis bahasa lain dari entri. |

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
