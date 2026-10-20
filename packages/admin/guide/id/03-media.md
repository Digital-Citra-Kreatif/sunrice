# Media

**Media** di bilah samping adalah pustaka semua berkas yang dipakai situs: gambar, dokumen, video, dan audio. Berkas yang diunggah sekali bisa dipakai di banyak tempat, dan field yang meminta gambar atau berkas memilihnya dari sini.

## Mencari berkas

- **Folder** tampil di atas daftar berkas. Klik folder untuk membukanya; jejak di atasnya kembali ke **Media (top level)** atau folder induk.
- **Search media** mencari di semua folder (*Search everywhere…*), berdasarkan judul dan nama berkas.
- Filter jenis menampilkan hanya **Images** (gambar), **Videos**, **Audio**, atau **Documents** (dokumen), atau **All types** (semua).
- **Trash** menampilkan berkas di tempat sampah; **Leave the trash** kembali ke pustaka.

## Mengunggah

Seret berkas dari komputer ke mana saja di halaman ini, atau klik **Upload** dan pilih berkasnya. Berkas masuk ke folder yang sedang Anda buka. Setiap berkas diunggah sendiri-sendiri, dengan kemajuannya; berkas yang tidak diizinkan (terlalu besar, atau jenis yang tidak diterima situs) disertai alasannya. **Clear the finished uploads** merapikan daftar.

> [!NOTE]
> Demi keamanan, panel menolak skrip, program, dan halaman web, serta gambar SVG yang bisa menjalankan kode, apa pun namanya. Percobaan seperti itu dicatat di log aktivitas.

Jika halaman menampilkan *Files can't be shown: the storage link is missing*, unggahan tetap berjalan tetapi tautannya belum berfungsi sampai developer membereskan servernya. Beri tahu mereka.

## Detail berkas

Klik berkas untuk membuka detailnya:

- Pratinjau, **Type** (jenis), **Size** (ukuran), **Dimensions** (ukuran gambar), dan kapan **Uploaded** (diunggah).
- **Title** (judul) dan **Caption** (keterangan).
- **Alternative text** (teks alternatif): apa yang ditampilkan gambar, untuk orang yang tidak bisa melihatnya dan untuk mesin pencari. Isilah untuk setiap gambar yang bermakna.
- **Copy link** menyalin alamat berkas; **Open** membukanya di tab baru.
- **Replace file** mengunggah versi baru sebagai gantinya: semua yang memakai berkas itu langsung menampilkan versi baru, dan alamatnya tetap sama.
- **Used by** menampilkan di mana berkas dipakai (entri, global…), beserta tautannya.

Klik **Save** setelah mengubah judul, keterangan, atau teks alternatif.

## Merapikan

- **New folder** membuat folder di dalam folder yang sedang dibuka. Menu sebuah folder berisi **Rename** (ganti nama) dan hapus; menghapus folder memindahkan isinya naik satu tingkat, jadi tidak ada berkas yang hilang.
- Pilih beberapa berkas (kotak centang di setiap berkas) untuk **Move to folder** (pindah ke folder) atau **Move to trash** (pindah ke tempat sampah) sekaligus.

## Tempat sampah dan menghapus

**Move to trash** mengeluarkan berkas dari pustaka tanpa menghapusnya. Jika berkas sedang dipakai, panel bertanya dulu dan menyebutkan di mana: halaman yang memakainya akan menampilkan gambar rusak.

Di **Trash**, **Restore** mengembalikan berkas seperti semula, dan **Delete for good** menghapusnya selamanya. Menghapus beberapa berkas sekaligus melewati berkas yang masih dipakai; buka masing-masing untuk melihat di mana dipakainya.

## Siapa boleh apa

Peran Anda yang menentukan: melihat pustaka, mengunggah, mengubah detail dan folder, serta tempat sampah adalah izin yang terpisah. Jika ada tombol yang tidak muncul, peran Anda tidak mencakupnya.

> [!TECHNICAL]
> Jenis berkas yang diizinkan, ukuran unggahan terbesar, dan ukuran gambar yang dibuat untuk setiap foto diatur di `config/sunrice-admin.php`. Lihat [Media](docs:panel/media).
