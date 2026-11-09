<?php

/**
 * PHASE 2 - Identitas versi naskah legal.
 *
 * Versi ini disimpan bersama setiap persetujuan pelanggan di tabel
 * terms_acceptances. Tanpa versi, kita tidak dapat membuktikan naskah
 * mana yang disetujui pelanggan ketika isinya berubah di kemudian hari.
 *
 * NAIKKAN versi setiap kali isi Pasal 4 (Ketentuan Penggunaan) atau
 * klausul tanggung jawab diubah secara substansial.
 */
return [

    // V5 (2026-11-10): Pasal 5 diubah. Masa tenggang VPS menjadi 2 hari, jadwal
    // pengingat disederhanakan, dan pembayaran ditutup otomatis setelah batas akhir.
    // V4 (2026-09-19): dokumen SLA dan janji persentase uptime dihapus, Pasal 3
    // dan 6 disesuaikan dengan infrastruktur penyedia pihak ketiga.
    //
    // Sengaja TIDAK dibaca dari env: isi naskah ada di repo (resources/views/pages/terms.blade.php),
    // jadi versinya harus ikut repo. Env lama di server (mis. TERMS_VERSION=...V3)
    // tidak boleh bisa menimpa versi naskah yang sebenarnya tampil.
    'terms_version' => 'TOS-2026-V5',

    'terms_effective_date' => '2026-11-10',

    /*
     * Larangan yang wajib selaras dengan Acceptable Use Policy Supplier.
     * Empat butir terakhir ditambahkan pada V3 karena sebelumnya tidak
     * tercantum, sehingga pelanggan secara kontraktual boleh melakukannya
     * padahal tindakan tersebut melanggar aturan supplier.
     */
    'prohibited_additions' => [
        'vpn_proxy',
        'scraping',
        'torrent',
        'automated_bots',
    ],

];
