import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
import { fillRich, openTranslation, fillLink } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
async function tr(id, title, slug, texts = {}, rich = {}, after) {
  await openTranslation(p, BASE, id);
  await p.fill('#title', title); if (slug) await p.fill('#slug', slug);
  for (const [k, v] of Object.entries(texts)) await p.fill(`#data-${k}`, v);
  for (const [k, v] of Object.entries(rich)) await fillRich(p, `#data-${k}`, v);
  if (after) await after();
  await p.getByRole('button', { name: 'Publish', exact: true }).click();
  await p.waitForTimeout(1500);
  console.log(id, title, await errors(p));
}
// Neighbourhoods
await tr(5, 'Kemang', null, { intro: 'Rimbun, kosmopolitan dan nyaman untuk berjalan kaki menurut ukuran Jakarta, Kemang menjadi pilihan keluarga ekspatriat dan para profesional kreatif berkat sekolah internasional, galeri dan rumah-rumah bertaman.',
  'highlights-0-label': 'Sekolah internasional', 'highlights-0-text': 'JIS Pattimura serta beberapa sekolah Inggris dan Prancis dalam 15 menit.', 'highlights-1-label': 'Rumah bertaman', 'highlights-1-text': 'Langka di Jakarta: rumah tunggal di lahan 600–1.500 m² dengan pohon-pohon besar.', 'highlights-2-label': 'Kuliner dan budaya', 'highlights-2-text': 'Galeri, kopi spesialti dan beberapa restoran terbaik kota di Jalan Kemang Raya.' },
  { body: '<h2>Tinggal di Kemang</h2><p>Kemang tumbuh dari desa kebun buah yang tenang menjadi kawasan paling internasional di Jakarta Selatan. Rumah-rumah rendah bertaman berjajar di jalan-jalan samping Jalan Kemang Raya, sementara jalan utamanya dipenuhi kafe, toko desain dan galeri.</p><h3>Siapa yang tinggal di sini</h3><ul><li>Keluarga dengan anak di Jakarta Intercultural School dan sekolah internasional sekitarnya</li><li>Diplomat dan pimpinan perusahaan yang lebih memilih rumah daripada apartemen</li><li>Arsitek, desainer dan pemilik restoran</li></ul><p>Perhitungkan kemacetan di jam sekolah dan musim hujan: kami dapat memberi tahu jalan mana yang tetap kering.</p>' });
await tr(9, 'Ubud', null, { intro: 'Jantung budaya Bali di perbukitan Gianyar: lembah hutan, sawah berundak, yoga dan cara hidup yang lebih lambat dan hijau.',
  'highlights-0-label': 'Pemandangan sawah', 'highlights-0-text': 'Rumah menghadap sawah berundak Tegallalang dan Sayan.', 'highlights-1-label': 'Budaya', 'highlights-1-text': 'Pura, tari dan desa kerajinan di sekelilingnya.', 'highlights-2-label': 'Kebugaran', 'highlights-2-text': 'Beberapa tempat retret paling terkenal di dunia.' },
  { body: '<h2>Hijau, berbudaya, tenang</h2><p>Ubud terletak di antara lembah sungai dan sawah berundak, setengah jam dari pantai. Vila di sini mengutamakan pemandangan dan privasi: punggung bukit di atas Sungai Ayung, taman yang menghadap sawah.</p><ul><li>Puri kerajaan, museum dan pasar seni setiap Sabtu</li><li>Tempat retret dan studio yoga</li><li>Malam yang lebih sejuk daripada di pesisir</li></ul>' });
// Journal
await tr(30, 'Pasar hunian premium Jakarta: tinjauan kuartal III 2026', 'pasar-hunian-premium-jakarta-kuartal-iii-2026', { excerpt: 'Harga rumah tapak di Kemang, Menteng dan Pondok Indah bertahan pada kuartal ketiga sementara volume transaksi naik 8%. Inilah yang kami lihat dan artinya bagi pembeli.' },
  { body: '<p>Kuartal ketiga membawa permintaan yang stabil untuk rumah tapak di Jakarta Selatan dan Pusat. Pembeli tetap selektif, tetapi properti dengan harga wajar di jalan yang baik terjual dalam 60 hari.</p><h2>Angka utama</h2><ul><li>Rata-rata harga penawaran di Menteng: Rp65 miliar, sama dengan kuartal II</li><li>Kemang: volume transaksi naik 8% dibanding kuartal sebelumnya</li><li>Pondok Indah: permintaan sewa dari penyewa korporat naik 12%</li></ul><h2>Artinya bagi pembeli</h2><p>Ruang negosiasi untuk rumah terbaik semakin sempit. Jika sebuah properti memenuhi semua kriteria, bertindaklah cepat; untuk yang lain, masih ada waktu untuk bersabar.</p>' });
await tr(31, 'Membeli properti di Bali sebagai warga asing: panduan praktis', 'membeli-properti-di-bali-sebagai-warga-asing', { excerpt: 'Hak sewa, Hak Pakai atau PT PMA? Gambaran jelas tentang cara warga asing dapat memiliki rumah di Bali, dan pertanyaan yang perlu diajukan sebelum menandatangani.' },
  { body: '<p>Hukum Indonesia tidak mengizinkan warga asing memegang Hak Milik secara langsung, tetapi ada tiga cara yang sudah mapan untuk memiliki rumah di Bali.</p><h2>Pilihan Anda</h2><ol><li><strong>Hak Sewa</strong>: sewa jangka panjang, biasanya 25–30 tahun dengan opsi perpanjangan.</li><li><strong>Hak Pakai</strong>: hak guna bagi warga asing yang memiliki KITAS atau KITAP.</li><li><strong>PT PMA</strong>: perusahaan milik asing yang dapat memegang Hak Guna Bangunan.</li></ol><h2>Sebelum menandatangani</h2><ul><li>Periksa zonasi dan persetujuan bangunan gedung (PBG)</li><li>Gunakan notaris (PPAT) yang independen</li><li>Pastikan syarat perpanjangan sewa secara tertulis</li></ul>' });
// Pages
await tr(36, 'Tentang kami', 'tentang-kami', { intro: 'Lumina Estates adalah agen independen untuk hunian istimewa di Jakarta dan Bali, dibangun di atas kerahasiaan, pengetahuan lokal dan hubungan jangka panjang.' },
  { 'sections-0-body': '<h2>Agen yang dibangun di atas kepercayaan</h2><p>Kami adalah tim yang terdiri dari dua belas penasihat di Jakarta dan Bali. Bersama-sama kami berbicara dalam lima bahasa dan telah menangani lebih dari 900 penjualan dan penyewaan.</p><p>Kami bekerja dengan sejumlah kecil klien dalam satu waktu, dan sebagian besar datang kepada kami melalui rekomendasi.</p>' });
await tr(44, 'Kontak', 'kontak', { intro: 'Kunjungi kami di Kemang atau Canggu, telepon, atau kirimkan pesan. Kami membalas dalam satu hari kerja.' },
  { 'sections-0-body': '<h2>Kantor kami</h2><p><strong>Jakarta</strong>: Jl. Kemang Raya No. 8, Bangka, Mampang Prapatan, Jakarta Selatan 12730</p><p><strong>Bali</strong>: Jl. Pantai Berawa No. 12, Tibubeneng, Kuta Utara, Badung 80361</p><p>Senin sampai Jumat 09.00–18.00, Sabtu 10.00–15.00.</p>' });
await tr(47, 'Beranda', 'beranda', { intro: 'Lumina Estates: rumah, vila dan tanah istimewa untuk dijual dan disewakan di Jakarta dan Bali.',
  'sections-0-heading': 'Hunian dengan keanggunan yang tenang', 'sections-0-subheading': 'Vila, rumah keluarga dan tanah istimewa di Jakarta dan Bali, dipilih dengan cermat dan dijual dengan penuh kerahasiaan.',
  'sections-1-items-0-label': 'Tahun di Jakarta dan Bali', 'sections-1-items-1-label': 'Rumah terjual dan tersewa', 'sections-1-items-2-label': 'Bahasa yang kami kuasai', 'sections-1-items-3-label': 'Nilai transaksi properti', 'sections-1-items-3-value': 'Rp4,2 T',
  'sections-2-heading': 'Hunian pilihan', 'sections-3-heading': 'Agen yang dibangun di atas kepercayaan', 'sections-4-heading': 'Kenali penasihat kami', 'sections-5-heading': 'Kata klien kami',
  'sections-6-heading': 'Berencana menjual?', 'sections-6-text': 'Pesan penilaian gratis dan rahasia dengan salah satu mitra kami. Kami membalas dalam satu hari kerja.' },
  { 'sections-3-body': '<p>Selama lebih dari satu dekade kami membantu keluarga, investor dan pendatang baru menemukan rumah di Jakarta dan Bali. Kami bekerja dengan lebih sedikit klien, sehingga setiap klien mendapat perhatian seorang mitra.</p><p>Dari kunjungan pertama hingga kantor notaris, Anda berurusan dengan penasihat yang sama.</p>' },
  async () => {
    const lt = id => p.locator(id).locator('xpath=ancestor::*[.//input[@placeholder="Link text"]][1]').getByPlaceholder('Link text');
    console.log('hero button editable?', await p.locator('#data-sections-0-button').isEditable().catch(e => 'n/a'));
    if (await p.locator('#data-sections-0-button').isEditable()) {
      await lt('#data-sections-0-button').fill('Lihat properti'); await lt('#data-sections-0-secondary_button').fill('Minta penilaian'); await lt('#data-sections-6-button').fill('Pesan penilaian');
    }
  });
await shot(p, '69-home-translation', true);
await b.close();
