# Media

**Media** di bilah samping adalah pustaka semua berkas yang dipakai situs: gambar, dokumen, video, dan audio. Berkas yang diunggah sekali bisa dipakai di banyak tempat, dan field yang meminta gambar atau berkas memilihnya dari sini.

## Mencari berkas

- **Folder** tampil di atas daftar berkas. Klik folder untuk membukanya; jejak di atasnya kembali ke **Media (tingkat teratas)** atau folder induk.
- **Cari media** mencari di semua folder (*Cari di mana saja…*), berdasarkan judul dan nama berkas.
- Filter jenis menampilkan hanya **Gambar**, **Video**, **Audio**, atau **Dokumen**, atau **Semua jenis**.
- **Tempat sampah** menampilkan berkas di tempat sampah; **Kembali ke daftar** kembali ke pustaka.

## Mengunggah

Seret berkas dari komputer ke mana saja di halaman ini, atau klik **Unggah** dan pilih berkasnya. Berkas masuk ke folder yang sedang Anda buka. Setiap berkas diunggah sendiri-sendiri, dengan kemajuannya; berkas yang tidak diizinkan (terlalu besar, atau jenis yang tidak diterima situs) disertai alasannya. **Bersihkan unggahan yang selesai** merapikan daftar.

> [!NOTE]
> Demi keamanan, panel menolak skrip, program, dan halaman web, serta gambar SVG yang bisa menjalankan kode, apa pun namanya. Percobaan seperti itu dicatat di log aktivitas.

Jika halaman menampilkan *File tidak bisa ditampilkan: tautan storage tidak ada*, unggahan tetap berjalan tetapi tautannya belum berfungsi sampai developer membereskan servernya. Beri tahu mereka.

## Detail berkas

Klik berkas untuk membuka detailnya:

- Pratinjau, **Jenis**, **Ukuran**, **Dimensi** (ukuran gambar), dan kapan **Diunggah**.
- **Judul** dan **Keterangan**.
- **Teks alternatif**: apa yang ditampilkan gambar, untuk orang yang tidak bisa melihatnya dan untuk mesin pencari. Isilah untuk setiap gambar yang bermakna.
- Di situs web dengan beberapa bahasa, **Dalam …** (satu per bahasa lain) berisi teks alternatif dan keterangan yang dibaca pengunjung dalam bahasa itu; jika kosong, mereka mendapat yang di atas.
- **Salin tautan** menyalin alamat berkas; **Buka** membukanya di tab baru.
- **Ganti file** mengunggah versi baru sebagai gantinya: semua yang memakai berkas itu langsung menampilkan versi baru, dan alamatnya tetap sama.
- **Dipakai oleh** menampilkan di mana berkas dipakai (entri, global…), beserta tautannya.

Klik **Simpan** setelah mengubah judul, keterangan, atau teks alternatif.

## Merapikan

- **Folder baru** membuat folder di dalam folder yang sedang dibuka. Menu sebuah folder berisi **Ganti nama** dan hapus; menghapus folder memindahkan isinya naik satu tingkat, jadi tidak ada berkas yang hilang.
- Pilih beberapa berkas (kotak centang di setiap berkas) untuk **Pindahkan ke folder** atau **Pindahkan ke tempat sampah** sekaligus.

## Tempat sampah dan menghapus

**Pindahkan ke tempat sampah** mengeluarkan berkas dari pustaka tanpa menghapusnya, dan membuatnya offline: tautannya tidak berfungsi lagi, di situs web juga, sampai berkas dipulihkan. Jika berkas sedang dipakai, panel bertanya dulu dan menyebutkan di mana: halaman yang memakainya akan menampilkan gambar rusak.

Di **Tempat sampah**, **Pulihkan** mengembalikan berkas seperti semula, dengan tautan yang sama, dan **Hapus permanen** menghapusnya; ini tidak dapat dibatalkan. Jika berkas masih dipakai, panel memperingatkan bahwa halaman yang memakainya akan menampilkan tautan rusak; jika tidak ada yang memakainya, panel hanya menyebutkan hal itu. Menghapus beberapa berkas sekaligus melewati berkas yang masih dipakai; buka masing-masing untuk melihat di mana dipakainya.

## Siapa boleh apa

Peran Anda yang menentukan: melihat pustaka, mengunggah, mengubah detail dan folder, serta tempat sampah adalah izin yang terpisah. Jika ada tombol yang tidak muncul, peran Anda tidak mencakupnya.

> [!TECHNICAL]
> Jenis berkas yang diizinkan, ukuran unggahan terbesar, dan ukuran gambar yang dibuat untuk setiap foto diatur di `config/sunrice-admin.php`. Lihat [Media](docs:panel/media).
