# Log aktivitas dan keamanan

**Log aktivitas** di bawah Administrasi menjawab *siapa mengubah apa, dan kapan*. Di sinilah Anda mencari tahu apa yang terjadi pada sebuah halaman, menelusuri kesalahan, atau menemukan sesuatu yang seharusnya tidak terjadi.

## Membaca log

Setiap baris menampilkan **Kapan**, **Siapa**, **Apa** (tindakan dan itemnya), **Detail** (field yang berubah), dan **Alamat IP** asalnya. Baris terbaru tampil paling atas. **Kolom** menyembunyikan yang tidak Anda perlukan (pilihan Anda diingat).

- **Sumber** menunjukkan asal perubahan: **Admin** (seseorang di panel), **Agen AI** (dengan nama tokennya), **Sistem** (server sendiri, misalnya penerbitan terjadwal), atau **Keamanan** (proses masuk dan kejadian keamanan lain).
- Log menyebutkan field yang berubah, tidak pernah nilai lama atau barunya, sehingga tidak menyimpan kata sandi atau data pribadi. Pengaturan adalah pengecualiannya: log menampilkan nilai lama dan baru sebuah pengaturan (*Close after days: 30 → 365*), kecuali kunci rahasia.

## Mencari sesuatu

- **Cari…** mencari di nama orang, item, dan tindakan, serta alamat IP: lengkap (`203.0.113.7`) atau awalnya (`192.168.`).
- Klik sebuah alamat IP untuk melihat hanya yang berasal dari alamat itu, termasuk kejadian keamanan (salah kata sandi, upaya masuk yang diblokir…).
- Saring berdasarkan **Sumber**, **Siapa** (siapa yang melakukannya), jenis item, **Aksi** (*published*, *deleted*, *logged in*…), atau tanggal.
- Klik nama seseorang untuk melihat aktivitasnya saja.
- Klik ikon riwayat di sebuah baris untuk melihat seluruh riwayat item tersebut.
- **Baris per halaman** menampilkan 25, 50, 100, atau 200 baris.

Filter tersimpan di alamat halaman, jadi Anda bisa menyimpan pencarian sebagai markah atau mengirimkannya ke rekan.

## Kejadian keamanan

Baris dengan sumber **Keamanan** mencatat hal-hal penting untuk mendeteksi pembobolan: masuk dan keluar, salah kata sandi, upaya masuk yang diblokir, akun terkunci, atur ulang kata sandi, unggahan yang diblokir, ekspor, perubahan peran dan izin, verifikasi dua langkah dinyalakan atau dimatikan, token AI dibuat atau dicabut, dan halaman yang ditolak untuk seseorang.

Saring log dengan sumber **Keamanan** untuk melihatnya saja. Kejadian keamanan seorang pengguna juga tampil di halamannya di Pengguna (kartu **Keamanan**).

## Peringatan keamanan lewat email

Jika ada tanda-tanda pembobolan, panel mengirim email kepada para super admin situs (dan siapa pun yang dipilih developer Anda). Contohnya:

- akun terkunci setelah banyak salah kata sandi, atau satu alamat mencoba banyak akun;
- seseorang yang berkuasa besar masuk dari alamat baru, atau setelah lama tidak masuk;
- seseorang atau sebuah peran diberi izin yang berkuasa besar;
- unggahan berbahaya diblokir;
- token AI dibuat, atau dipakai dari alamat baru;
- verifikasi dua langkah dimatikan, atau kode situs web diubah;
- log aktivitas dipangkas;
- seseorang terus membuka halaman yang tidak boleh ia lihat, atau menghapus atau mengekspor banyak hal dalam waktu singkat.

Setiap email menjelaskan apa yang terjadi dan apa yang perlu dilakukan, dengan tautan ke log. Peringatan yang sama tidak diulang lebih dari sekali dalam satu jam.

> [!TIP]
> Menerima peringatan yang tidak Anda kenali? Buka tautannya, lihat siapa pelakunya, dan jika ternyata bukan dia, gunakan **Kunci akun** pada pengguna tersebut (lihat [Pengguna dan peran](guide:panel/users-roles)).

## Memangkas log

Log menyimpan riwayat satu tahun dan merapikan dirinya setiap hari. Jika peran Anda mengizinkan, **Pangkas…** menghapus baris yang lebih lama secara manual: ketik berapa hari yang disimpan, lalu konfirmasi. Pemangkasan tidak bisa dibatalkan, dan dicatat serta diemailkan sebagai peringatan keamanan, karena menghapus riwayat bisa menutupi jejak seseorang.

> [!TECHNICAL]
> Lama penyimpanan log diatur dengan `activity.keep_days` di `config/sunrice-admin.php`; penerima peringatan dengan `security.alerts`. Lihat [Activity log](docs:panel/activity) dan [Security](docs:panel/security).
