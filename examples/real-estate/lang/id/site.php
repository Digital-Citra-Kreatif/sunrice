<?php

declare(strict_types=1);

/*
 * Kata-kata antarmuka situs dalam Bahasa Indonesia. Konten ada di CMS; kata-kata CMS sendiri ada di
 * sunrice-cms::site. Kunci harus sama dengan lang/en/site.php (diperiksa oleh TranslationsTest).
 */
return [
    'skip_to_content' => 'Langsung ke konten',
    'home' => 'Beranda',
    'breadcrumbs' => 'Navigasi jejak',
    'read_more' => 'Selengkapnya',
    'empty' => 'Belum ada apa pun di sini.',
    'results' => 'Hasil',
    'fallback' => 'Halaman ini belum tersedia dalam Bahasa Indonesia, jadi ditampilkan dalam bahasa aslinya.',
    'rating' => 'Nilai :rating dari 5',

    'nav' => [
        'main' => 'Utama',
        'footer' => 'Kaki halaman',
        'legal' => 'Legal',
        'languages' => 'Bahasa',
        'toggle' => 'Menu',
    ],

    'collections' => [
        'properties' => 'Properti',
        'agents' => 'Tim kami',
        'neighbourhoods' => 'Kawasan',
        'journal' => 'Jurnal',
        'pages' => 'Halaman',
    ],

    'taxonomies' => [
        'property-types' => 'Jenis properti',
        'locations' => 'Lokasi',
        'topics' => 'Topik jurnal',
    ],

    'number' => ['thousands' => '.', 'decimal' => ','],

    'price' => [
        'format' => 'Rp :amount',
        'month' => '/ bulan',
        'year' => '/ tahun',
        'on_request' => 'Harga atas permintaan',
        'short' => [
            'million' => 'Rp :amount juta',
            'billion' => 'Rp :amount miliar',
            'trillion' => 'Rp :amount triliun',
        ],
    ],

    'status' => [
        'available' => 'Tersedia',
        'under_offer' => 'Dalam penawaran',
        'sold' => 'Terjual',
        'rented' => 'Tersewa',
    ],

    'facts' => [
        'bedrooms' => 'Kamar tidur',
        'bathrooms' => 'Kamar mandi',
        'building_area' => 'Bangunan',
        'land_area' => 'Tanah',
        'year_built' => 'Tahun dibangun',
        'property_type' => 'Jenis',
        'sqm' => ':value m²',
        'bedrooms_count' => ':count KT|:count KT',
        'bathrooms_count' => ':count KM|:count KM',
    ],

    'property' => [
        'price' => 'Harga',
        'price_rent' => 'Harga sewa',
        'price_sold' => 'Harga penawaran terakhir',
        'price_rented' => 'Harga sewa terakhir',
        'gone_sold' => 'Rumah ini sudah terjual',
        'gone_rented' => 'Rumah ini sudah disewa',
        'gone_text' => 'Beri tahu kami apa yang Anda cari, kami akan mengabari Anda saat ada yang serupa.',
        'gone_link' => 'Lihat rumah yang tersedia sekarang',
        'facts' => 'Fakta utama',
        'features' => 'Fitur',
        'amenities' => 'Fasilitas',
        'floor_plan' => 'Denah',
        'location' => 'Lokasi',
        'map' => 'Lihat di peta',
        'video' => 'Tonton video',
        'agent' => 'Agen Anda',
        'enquire' => 'Atur kunjungan',
        'enquire_text' => 'Beri tahu kami waktu yang cocok, kami akan menghubungi Anda dalam satu hari kerja.',
        'message_default' => 'Saya ingin tahu lebih lanjut tentang :title.',
        'send' => 'Kirim pertanyaan',
        'neighbourhood' => 'Panduan kawasan',
        'nearby' => 'Properti lain di sekitar',
    ],

    'gallery' => [
        'open' => 'Buka foto :number dari :total',
        'count' => ':count foto|:count foto',
        'title' => 'Foto :title',
        'close' => 'Tutup',
        'previous' => 'Foto sebelumnya',
        'next' => 'Foto berikutnya',
    ],

    'filter' => [
        'label' => 'Cari properti',
        'search' => 'Kata kunci',
        'search_placeholder' => 'Kolam renang, pemandangan laut, Menteng…',
        'listing' => 'Beli atau sewa',
        'type' => 'Jenis properti',
        'location' => 'Lokasi',
        'bedrooms' => 'Kamar tidur',
        'bedrooms_min' => 'KT',
        'price' => 'Harga',
        'price_max' => 'Harga maks.',
        'up_to' => 'Hingga :amount',
        'any' => 'Semua',
        'submit' => 'Cari',
        'results' => ':count properti|:count properti',
        'applied' => 'Filter aktif',
        'remove' => 'Hapus filter :filter: :value',
        'clear' => 'Hapus semua',
        'empty_title' => 'Tidak ada properti yang cocok',
        'empty_text' => 'Coba kurangi filter, atau tanyakan kepada kami: banyak properti kami ditawarkan secara privat.',
    ],

    'sort' => [
        'label' => 'Urutkan',
        'newest' => 'Terbaru',
        'price_asc' => 'Harga terendah',
        'price_desc' => 'Harga tertinggi',
        'size' => 'Terluas',
    ],

    'pagination' => [
        'label' => 'Halaman',
        'previous' => 'Halaman sebelumnya',
        'next' => 'Halaman berikutnya',
        'page' => 'Halaman :page',
    ],

    'blocks' => [
        'missing' => 'Blok “:type” belum punya templat (resources/views/cms/blocks). Pengunjung tidak melihatnya.',
        'featured_eyebrow' => 'Properti pilihan',
        'all_properties' => 'Lihat semua properti',
        'team_eyebrow' => 'Tim kami',
        'testimonials_eyebrow' => 'Cerita klien',
        'faq_eyebrow' => 'Pertanyaan',
        'faq_title' => 'Pertanyaan yang sering diajukan',
    ],

    'pages' => [
        'children' => 'Lainnya di :title',
    ],

    'team' => [
        'eyebrow' => 'Orang-orang di balik Lumina',
    ],

    'agent' => [
        'email' => 'Email',
        'languages' => 'Bahasa',
        'listings' => 'Properti bersama :name',
    ],

    'languages' => [
        'english' => 'Inggris',
        'indonesian' => 'Indonesia',
        'mandarin' => 'Mandarin',
        'dutch' => 'Belanda',
    ],

    'neighbourhoods' => [
        'eyebrow' => 'Tempat tinggal',
        'guide' => 'Panduan kawasan',
        'average_price' => 'Harga rata-rata',
        'listings' => 'Properti di :name',
    ],

    'locations' => [
        'within' => 'Tempat di :place',
    ],

    'journal' => [
        'eyebrow' => 'Wawasan & cerita',
        'topics' => 'Topik',
        'all' => 'Semua',
        'related' => 'Properti dalam cerita ini',
        'more' => 'Lainnya dari jurnal',
    ],

    'contact' => [
        'address' => 'Kantor',
        'phone' => 'Telepon',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'office_hours' => 'Jam buka',
        'directions' => 'Petunjuk arah',
        'primary' => 'Kontak Anda',
        'form_title' => 'Kirim pesan kepada kami',
    ],

    'sell' => [
        'eyebrow' => 'Penilaian gratis',
        'title' => 'Berapa nilai rumah Anda?',
        'text' => 'Bagikan beberapa detail dan foto. Agen senior kami akan menyiapkan penilaian rahasia, biasanya dalam 48 jam.',
        'submit' => 'Minta penilaian',
    ],

    'forms' => [
        'choose' => 'Pilih…',
        'send' => 'Kirim pesan',
        'failed' => 'Mohon periksa kolom yang ditandai di bawah.',
        'fields' => [
            'name' => 'Nama Anda',
            'email' => 'Email',
            'phone' => 'Telepon',
            'topic' => 'Saya tertarik untuk',
            'message' => 'Pesan',
            'preferred_date' => 'Tanggal kunjungan',
        ],
        'options' => [
            'topic' => [
                'buying' => 'Membeli',
                'selling' => 'Menjual',
                'renting' => 'Menyewa',
                'other' => 'Lainnya',
            ],
        ],
    ],

    'newsletter' => [
        'eyebrow' => 'Buletin',
        'email' => 'Alamat email',
        'placeholder' => 'anda@contoh.com',
        'submit' => 'Berlangganan',
        'note' => 'Satu email pilihan setiap bulan. Berhenti kapan saja.',
    ],

    'footer' => [
        'title' => 'Tentang Lumina Estates',
        'explore' => 'Jelajahi',
        'visit' => 'Kunjungi kami',
        'social' => 'Ikuti kami',
    ],

    'search' => [
        'eyebrow' => 'Pencarian',
        'title' => 'Cari di situs',
        'title_for' => 'Hasil untuk “:query”',
        'label' => 'Cari di situs',
        'placeholder' => 'Vila di Canggu, laporan pasar…',
        'submit' => 'Cari',
        'results' => ':count hasil|:count hasil',
        'none' => 'Tidak ditemukan. Coba kata lain, atau jelajahi properti kami.',
    ],

    'errors' => [
        'home' => 'Kembali ke beranda',
        '404_title' => 'Halaman tidak ditemukan',
        '404_text' => 'Halaman yang Anda cari tidak ada. Mungkin sudah terjual, berganti nama, atau alamatnya salah ketik.',
        '500_title' => 'Terjadi kesalahan',
        '500_text' => 'Maaf, halaman tidak dapat ditampilkan. Silakan coba lagi sebentar lagi.',
        '503_title' => 'Segera kembali',
        '503_text' => 'Kami sedang melakukan beberapa perbaikan. Silakan kembali dalam beberapa menit.',
    ],
];
