<?php

namespace App\Support;

final class ActivityType
{
    public const LOGIN_SUCCESS = 'login_success';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGOUT = 'logout';
    public const PASSWORD_CHANGED = 'password_changed';
    public const PASSWORD_RESET = 'password_reset';
    public const APP_LAUNCHED = 'app_launched';
    public const ACCESS_DENIED = 'access_denied';
    public const QUIZ_CLICKED = 'quiz_clicked';

    public const USER_CREATED = 'user_created';
    public const USER_UPDATED = 'user_updated';
    public const USER_ACTIVATED = 'user_activated';
    public const USER_DEACTIVATED = 'user_deactivated';
    public const ACCESS_UPDATED = 'access_updated';

    public const OPD_CREATED = 'opd_created';
    public const OPD_UPDATED = 'opd_updated';
    public const OPD_ACTIVATED = 'opd_activated';
    public const OPD_DEACTIVATED = 'opd_deactivated';

    public const APPLICATION_CREATED = 'application_created';
    public const APPLICATION_UPDATED = 'application_updated';
    public const APPLICATION_LINK_CREATED = 'application_link_created';
    public const APPLICATION_LINK_UPDATED = 'application_link_updated';

    public const BANNER_CREATED = 'banner_created';
    public const BANNER_UPDATED = 'banner_updated';
    public const BANNER_DELETED = 'banner_deleted';

    public const QUESTIONNAIRE_CREATED = 'questionnaire_created';
    public const QUESTIONNAIRE_UPDATED = 'questionnaire_updated';
    public const QUESTIONNAIRE_DELETED = 'questionnaire_deleted';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::LOGIN_SUCCESS => 'Login berhasil',
            self::LOGIN_FAILED => 'Login gagal',
            self::LOGOUT => 'Logout',
            self::PASSWORD_CHANGED => 'Ubah kata sandi',
            self::PASSWORD_RESET => 'Reset kata sandi',
            self::APP_LAUNCHED => 'Membuka aplikasi',
            self::ACCESS_DENIED => 'Akses ditolak',
            self::QUIZ_CLICKED => 'Membuka kuisioner',

            self::USER_CREATED => 'Tambah pengguna',
            self::USER_UPDATED => 'Ubah pengguna',
            self::USER_ACTIVATED => 'Aktifkan pengguna',
            self::USER_DEACTIVATED => 'Nonaktifkan pengguna',
            self::ACCESS_UPDATED => 'Ubah hak akses',

            self::OPD_CREATED => 'Tambah OPD',
            self::OPD_UPDATED => 'Ubah OPD',
            self::OPD_ACTIVATED => 'Aktifkan OPD',
            self::OPD_DEACTIVATED => 'Nonaktifkan OPD',

            self::APPLICATION_CREATED => 'Tambah aplikasi',
            self::APPLICATION_UPDATED => 'Ubah aplikasi',
            self::APPLICATION_LINK_CREATED => 'Tambah tautan aplikasi',
            self::APPLICATION_LINK_UPDATED => 'Ubah tautan aplikasi',

            self::BANNER_CREATED => 'Tambah banner',
            self::BANNER_UPDATED => 'Ubah banner',
            self::BANNER_DELETED => 'Hapus banner',

            self::QUESTIONNAIRE_CREATED => 'Tambah kuisioner',
            self::QUESTIONNAIRE_UPDATED => 'Ubah kuisioner',
            self::QUESTIONNAIRE_DELETED => 'Hapus kuisioner',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }
}
