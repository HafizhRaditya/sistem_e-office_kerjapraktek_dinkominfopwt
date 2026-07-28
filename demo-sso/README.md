# Demo SSO Keycloak — aplikasi kedua

Aplikasi ini berdiri sendiri dan tidak menyentuh repository portal E-Office. Tujuannya hanya membuktikan bahwa dua OIDC client pada realm Keycloak yang sama dapat memakai satu sesi SSO.

## Stack

- Node.js 20.19+ atau 22+
- Express
- `openid-client`
- `express-session` dengan penyimpanan memori

`MemoryStore` sengaja dipakai karena aplikasi ini hanya demo lokal. Jangan gunakan rancangan ini untuk produksi.

## Berkas

```text
keycloak-sso-demo/
├── server.mjs
├── package.json
├── .env.example
├── .gitignore
└── README.md
```

## 1. Membuat client baru di Keycloak

Realm: `EOffice`

Buka **Clients → Create client** dan isi:

### General settings

| Pengaturan | Nilai |
|---|---|
| Client type | OpenID Connect |
| Client ID | `eoffice-sso-demo` |

### Capability config

| Pengaturan | Nilai |
|---|---|
| Client authentication | ON |
| Authorization | OFF |
| Standard flow | ON |
| Direct access grants | OFF |
| Implicit flow | OFF |
| Service accounts roles | OFF |

### Login settings / Access settings

| Pengaturan | Nilai |
|---|---|
| Root URL | `http://127.0.0.1:9000` |
| Home URL | `http://127.0.0.1:9000/` |
| Valid redirect URIs | `http://127.0.0.1:9000/callback` |
| Valid post logout redirect URIs | `http://127.0.0.1:9000/logged-out` |
| Web origins | `http://127.0.0.1:9000` |

Pada tab **Advanced**, tetapkan **Proof Key for Code Exchange Code Challenge Method** ke `S256` bila opsi tersebut tersedia.

Simpan, buka tab **Credentials**, lalu salin client secret. Jangan memakai client ID atau secret portal E-Office.

Gunakan alamat yang sama secara konsisten. `localhost` dan `127.0.0.1` merupakan string redirect yang berbeda. Konfigurasi contoh ini seluruhnya memakai `127.0.0.1`.

## 2. Menyiapkan aplikasi di Windows

Buka PowerShell pada folder aplikasi:

```powershell
Copy-Item .env.example .env
npm install
```

Buat session secret:

```powershell
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

Buka `.env`, isi:

```env
PORT=9000
OIDC_ISSUER=https://account.dev.banyumaskab.go.id/realms/EOffice
OIDC_CLIENT_ID=eoffice-sso-demo
OIDC_CLIENT_SECRET=ISI_SECRET_DARI_KEYCLOAK
OIDC_REDIRECT_URI=http://127.0.0.1:9000/callback
OIDC_POST_LOGOUT_REDIRECT_URI=http://127.0.0.1:9000/logged-out
SESSION_SECRET=ISI_HASIL_RANDOM
```

Periksa sintaks dan jalankan:

```powershell
npm run check
npm start
```

Buka:

```text
http://127.0.0.1:9000
```

## 3. Menguji SSO

### Bukti utama

1. Hapus sesi lama terlebih dahulu dengan logout dari Keycloak atau gunakan jendela InPrivate baru.
2. Jalankan portal E-Office pada port 8000.
3. Login ke E-Office menggunakan tombol Keycloak.
4. Pastikan dashboard E-Office sudah terbuka.
5. Di admin E-Office, tambahkan aplikasi/link demo dengan URL `http://127.0.0.1:9000`, beri hak akses kepada pengguna uji, lalu klik aplikasi itu dari dashboard. Untuk tes cepat, URL tersebut juga boleh dibuka langsung.
6. Aplikasi demo akan mengarahkan browser sebentar ke Keycloak. Redirect singkat ini normal; indikator SSO adalah tidak munculnya form login kedua.
7. Keycloak menemukan sesi SSO yang sudah aktif dan langsung mengembalikan browser ke callback demo.
8. Form login Keycloak tidak muncul lagi.
9. Cocokkan `preferred_username`, `name`, `email`, dan `sub` dengan identitas pengguna portal.

### Uji pembanding

1. Buka jendela InPrivate lain yang belum memiliki cookie Keycloak.
2. Buka `http://127.0.0.1:9000` langsung.
3. Kali ini form login Keycloak harus muncul.

Perbedaan dua skenario tersebut merupakan bukti bahwa aplikasi demo tidak menerima sesi portal secara langsung; keduanya memakai sesi pusat Keycloak yang sama.

### Uji logout

1. Pada aplikasi demo tekan **Logout dari Keycloak**.
2. Setelah kembali ke halaman logout, buka portal atau demo lagi.
3. Keycloak semestinya meminta autentikasi kembali karena tombol tersebut menjalankan RP-Initiated Logout dengan `id_token_hint`.

## Keamanan yang dicakup demo

- Authorization Code Flow
- client secret hanya di `.env`
- PKCE S256
- `state`
- `nonce`
- discovery metadata issuer
- penukaran code server-to-server
- validasi ID token oleh `openid-client`, termasuk tanda tangan dengan JWKS, issuer, audience, expiration, dan nonce
- session cookie `HttpOnly` dan `SameSite=Lax`
- regenerasi session ID sesudah login
- RP-Initiated Logout

## Troubleshooting

### `Invalid parameter: redirect_uri`

Pastikan nilai berikut sama persis:

```text
Keycloak Valid redirect URIs
.env OIDC_REDIRECT_URI
URL callback aplikasi
```

Jangan mencampur `localhost` dan `127.0.0.1`.

### `Konfigurasi .env belum lengkap`

Isi semua variabel wajib, khususnya `OIDC_CLIENT_SECRET` dan `SESSION_SECRET`.

### Form login tetap muncul ketika berpindah dari portal

Periksa:

- portal dan demo menggunakan server Keycloak yang sama;
- keduanya berada di realm `EOffice`;
- browser yang digunakan sama dan bukan profile/incognito berbeda;
- portal benar-benar login melalui Keycloak, bukan login lokal NIP/NIK;
- sesi realm belum kedaluwarsa.

### `unauthorized_client` atau `invalid_client`

Pastikan client demo memiliki **Client authentication ON**, **Standard flow ON**, dan secret di `.env` sama dengan tab Credentials.
