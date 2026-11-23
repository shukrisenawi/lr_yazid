# Landing pages

1. Buka **Admin → Website content → Create page** (`/admin/content-pages/create`).
2. Tandakan **Landing page**, isi tajuk dan slug, kemudian sunting kandungan menggunakan editor teks berformat. Editor menyokong gambar, pautan dan jadual; HTML berbahaya ditapis.
3. Pilih **Published** dan simpan. Pautan awam dipaparkan pada borang dan senarai: `{APP_URL}/lp/{slug}`. Pelawat tidak perlu login. Status Draft menutup akses awam.
4. Landing page tidak menggunakan menu/footer kedai. Halaman maklumat biasa terus menggunakan `/pages/{slug}`.

## Subdomain pilihan

- Masukkan hostname sahaja, misalnya `promosi.example.com`, pada borang landing page. Satu hostname hanya boleh digunakan sekali.
- Tetapkan rekod DNS A/AAAA ke pelayan aplikasi (atau CNAME mengikut konfigurasi hosting).
- Daftarkan hostname dalam virtual host/hosting dengan document root ke direktori `landing-public` aplikasi Laravel yang sama, dan pasang sijil SSL.
- Pastikan konfigurasi trusted hosts/proxy pelayan membenarkan hostname tersebut jika digunakan.
- Selepas DNS/SSL siap, `https://promosi.example.com/` memaparkan landing page yang diterbitkan. Pautan `/lp/{slug}` kekal tersedia.
- Subdomain bukan pemasangan berasingan. Jangan ubah `APP_URL` utama kepada subdomain landing page. Pengurusan admin dilakukan pada domain utama.
- Deploy: jalankan `php artisan migrate --force` dan `npm run build`.

Tiada DNS, virtual host atau sijil SSL dicipta secara automatik oleh aplikasi.

## Document root khusus subdomain

Folder `landing-public` ialah root tambahan, berasingan daripada `public` untuk domain utama.
Ia menggunakan aplikasi, pangkalan data dan fail gambar/build yang sama; tidak perlu salin atau memasang Laravel sekali lagi.
Hanya halaman utama landing page yang diterbitkan dan aset awam dibenarkan melalui root ini. Hostname yang belum dipadankan akan memberi 404.

Contoh ruangan **Document root (The path to the website home directory.)**:

```text
/home/USERNAME/lr_yazid/landing-public
```

Gantikan `/home/USERNAME/lr_yazid` dengan lokasi sebenar projek di hosting.
Jika panel meminta laluan relatif kepada home, masukkan `lr_yazid/landing-public`.
Pada komputer pembangunan ini foldernya ialah `D:\xampp\htdocs\lr_yazid\landing-public`.
Jangan isikan URL, `/lp/landing-page`, atau folder projek induk yang mengandungi `.env`.

Untuk Apache, pastikan `mod_rewrite` aktif dan `AllowOverride All` membenarkan `.htaccess`.
Untuk Nginx, gunakan `try_files $uri /index.php?$query_string` dan benarkan hanya `index.php` sebagai skrip PHP.
Folder `public/build` mesti sudah dibina dan `public/storage` mesti dipautkan dengan `php artisan storage:link` jika gambar muat naik digunakan.
