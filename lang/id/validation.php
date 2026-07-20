<?php

/*
|--------------------------------------------------------------------------
| Indonesian validation messages
|--------------------------------------------------------------------------
|
| The UI is Indonesian (users are ASN), so validation errors must be too.
| Controllers already pass per-rule messages for the common cases; those still
| win over this file. This exists so the *remaining* rules (max, string,
| integer, exists, ...) stop falling through to Laravel's English defaults.
|
| `attributes` renames the raw field keys, so ":attribute wajib diisi" reads
| "OPD pemilik wajib diisi" instead of "opd_id wajib diisi".
|
*/

return [
    'accepted' => ':attribute harus disetujui.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'array' => ':attribute harus berupa daftar.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',

    'between' => [
        'array' => ':attribute harus berisi antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus berisi antara :min sampai :max karakter.',
    ],

    'boolean' => ':attribute hanya boleh bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus tanggal yang sama dengan :date.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'email' => 'Format :attribute tidak valid.',
    'ends_with' => ':attribute harus diakhiri dengan salah satu dari: :values.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib diisi.',

    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih.',
        'file' => ':attribute harus lebih besar dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value.',
        'string' => ':attribute harus lebih dari atau sama dengan :value karakter.',
    ],

    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => ':attribute tidak ada di dalam :other.',
    'integer' => ':attribute harus berupa angka bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':attribute harus berupa JSON yang valid.',

    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item.',
        'file' => ':attribute harus lebih kecil dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value.',
        'string' => ':attribute harus kurang dari atau sama dengan :value karakter.',
    ],

    'max' => [
        'array' => ':attribute maksimal :max item.',
        'file' => ':attribute maksimal :max kilobyte.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],

    'mimes' => ':attribute harus berupa berkas berjenis: :values.',
    'mimetypes' => ':attribute harus berupa berkas berjenis: :values.',

    'min' => [
        'array' => ':attribute minimal :min item.',
        'file' => ':attribute minimal :min kilobyte.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],

    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'present' => ':attribute wajib ada.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi bila :other bernilai :value.',
    'required_unless' => ':attribute wajib diisi kecuali :other bernilai :values.',
    'required_with' => ':attribute wajib diisi bila ada :values.',
    'required_with_all' => ':attribute wajib diisi bila ada :values.',
    'required_without' => ':attribute wajib diisi bila tidak ada :values.',
    'required_without_all' => ':attribute wajib diisi bila tidak ada satu pun dari :values.',
    'same' => ':attribute dan :other harus sama.',

    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus berisi :size karakter.',
    ],

    'starts_with' => ':attribute harus diawali dengan salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'timezone' => ':attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah dipakai.',
    'uploaded' => ':attribute gagal diunggah.',
    'url' => 'Format :attribute tidak valid.',
    'uuid' => ':attribute harus berupa UUID yang valid.',

    'custom' => [],

    /*
    | Field names as they appear to the admin. Keys mirror the request inputs
    | used by the admin panel forms and the activity-log filters.
    */
    'attributes' => [
        // applications
        'name' => 'Nama',
        'slug' => 'Slug',
        'description' => 'Deskripsi',
        'opd_id' => 'OPD pemilik',
        'app_group' => 'Grup aplikasi',
        'category' => 'Kategori',
        'sort_order' => 'Urutan',
        'is_active' => 'Status aktif',
        'is_new' => 'Tanda baru',

        // application links
        'label' => 'Label',
        'url' => 'URL',

        // users
        'nip_nik' => 'NIP/NIK',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'password_confirmation' => 'Konfirmasi kata sandi',
        'current_password' => 'Kata sandi saat ini',
        'role' => 'Peran',

        // access management
        'access' => 'Hak akses',
        'access.*' => 'Aplikasi yang dipilih',

        // activity log filters
        'user' => 'Pengguna',
        'type' => 'Jenis aktivitas',
        'from' => 'Tanggal awal',
        'to' => 'Tanggal akhir',
    ],
];
