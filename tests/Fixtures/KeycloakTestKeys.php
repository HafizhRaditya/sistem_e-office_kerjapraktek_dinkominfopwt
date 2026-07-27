<?php

namespace Tests\Fixtures;

/**
 * ============================================================================
 *  KUNCI UJI — BUKAN RAHASIA, BUKAN UNTUK PRODUKSI.
 * ============================================================================
 *
 * Dua pasang kunci RSA-2048 yang dibuat sekali lalu ditulis tetap di sini.
 * Keduanya hanya dipakai oleh test SSO Keycloak untuk menandatangani ID token
 * palsu; TIDAK ADA kode aplikasi yang merujuk berkas ini, dan tidak boleh ada.
 * Nilainya sengaja dipublikasikan di repo — memperlakukannya sebagai rahasia
 * justru menyesatkan.
 *
 * Kenapa statis, bukan dibuat saat runtime: openssl_pkey_new() memerlukan
 * openssl.cnf, dan di mesin pengembangan Windows kami OPENSSL_CONF tidak
 * terpasang sehingga pembuatan kunci gagal. Memuat kunci yang sudah ada tidak
 * memerlukan berkas konfigurasi itu, jadi fixture statis membuat test berjalan
 * di mesin mana pun tanpa menyentuh phpunit.xml (berkas bersama).
 *
 * realm()    — kunci yang "dimiliki" Keycloak; bagian publiknya disajikan
 *              sebagai JWKS oleh mock, sehingga token yang ditandatanganinya
 *              lolos verifikasi.
 * foreign()  — kunci penyerang. Sengaja memakai `kid` yang SAMA dengan
 *              realm() supaya test membuktikan penolakan terjadi karena tanda
 *              tangannya tidak cocok, bukan sekadar karena kid berbeda.
 */
final class KeycloakTestKeys
{
    public const KID = 'eoffice-test-key';

    /** @return array<string, string> */
    public static function realm(): array
    {
        return [
            'alg' => 'RS256',
            'd' => 'NBBj55LzaUtvof7shUlDMx2sDo4bWvy1b11L5KS7NwGBpNhOiRUcm2MxDCtExrcBb6chMfARnBa6lPiS7IIVUPiuElUfp6Xf3vKJep6N87nEshokV68xzgnUY6oo8ZedEDzhZzD_GZHCBman2GL6aT5d0h7LQ1Kb_1E2ddAmSU1MY9mEKaRQZqiCQ8UNRSQphvbunXyMhgt-13Pb9HwLJxhBhtI7jJxTjcvSCu9kn_fOFSEdWlhupaQukP9AAoGtckdXXJzlV3OeQ-JGEX0bGSURiQZguXw_JGXoc7B7b2_7E0UVwHF6zXipPjkRPoQjrbTWPrEQdk1tSfNRtTGB',
            'dp' => 'z0GiFWTXgig0sirlPITyFFDHp_t-QpG6PmgqEbxZFGtb99mMr2fvHy2Qjpjg1lLSGe-VssnQnqCQ3A2A3c0PyTgpf3dn2Vhr1nSWjXz08wSfIMchuMj-oX4nYpalMnFpVc7oVtWXbQnKzOU2oxQXy5sb3M_FEzn5fgS2WIpdQiE',
            'dq' => 'BSrps-ByG2jbc9afMOeqoXM3HUmeQUkseviGF8uIV0FZ17y9WqpYWXIK4tZ-SKUQUuoLgEThKo9eSKwkLrTGoJ-Yo_1LTL04tWmL19S3n-zpD4X7hdaGn8IGZAlscOm-r0wq2NTKWL7Rty4Jkk7daLUww3cTCgQgTYzW7fIzUcE',
            'e' => 'AQAB',
            'kid' => self::KID,
            'kty' => 'RSA',
            'n' => 'vOP--qe5M3Ss-S1ULwThyn_f-ZF8nUBnHYQgrezzd-doKv8kEqUndiIwSd46TX0N7RMPGA2nvXQwsK61eLOcgBXBHqgMpvdDabMGTwXKSu_LG9-qipgOeQW0AOLtkrb3uLcCjUyA8YANClOlSWS3Yvyy6YD7uMVDJSW45niTS1abKFTRk44J-ef-nixC4TTwGAHLmg6X6rioMK_jKS0LETeRlbvSFEjb09iA22dihSKggJ4tJ78L7QxwhUu4DtHXUcjdXQlpu93ug-WqfPXDB2tcHBn2NDYVAfKq5AhyDGUgCy6hBwG-Orfvw4wlCA2V7ZGwBRXC78IPpjJe5uFI4Q',
            'p' => '6bvN71Wy0zqEpr6F0aPvCWQrglwR6W-7obizCT-VgMVI3zW7ktv2mquC5IoyoUXzxAIqbsDv0lT960eMkBOVcG8YAJpkC68OGJwAcx1jmymv6R7X1BnP3Kx1hEQ-nJTcIj0FiUU84x7SknSoHypprf8gG_ZwsI6AvySawHL74iE',
            'q' => 'zuKUYArxvxhf0MilES4PgA3Zm7dd0K7M4pm8LesQ2zOfNlsvmp3LHA3QSMcYat1HJq828JPZwTel7e0ODPkr2ZCKLadgLl7-sRDt6yHZoVCxGBb-4JMSp26WI1BMrj2NYJ84nFOxbD5QEXnwuno_bMfGjaiBB9hUFXJkNVjeDsE',
            'qi' => 'ecLdZRTp58m3Zlzb8TkAx_7hyXru3iUtqvEdtRjNbW6994GbF4BcdJfE7Mexx-4enLQxCfoCHAOja__gGQwEDvki8dEoztU3JfhpB3j_-izFerKbxeK6uuhFe8gi5h3A0D7kM6X6Gj4e2alt20O6gZ7D-zPSdqUrPAzmYHqJRAY',
            'use' => 'sig',
        ];
    }

    /** @return array<string, string> */
    public static function foreign(): array
    {
        return [
            'alg' => 'RS256',
            'd' => 'BnEPr23wv-yvSGEPNxtSNdtV-OUVdgbTdFla6gb4pnjJb3VSK-zATzl5mwUJf6XV9be5V-7AQdZXQ_yJ5li-6YtygqJo_Jf0_SwGj4nDiWpyeLFB2kgtqg1W2Dkh6bngvecPyGf-Y6j2C4Cm-3yBIbv2Uisj6m7C2e4q5jdGFcUReX4lo-8tjY__31pZ9CFzG5OinIxwDERBRP0fKz3RYqFUIkwgEch02G2Tid89FtlplZoxdFfdI1iNdIt3LubJV8H7PQ_QE9PypLA7aZZ09ctWS1nWZw0QB71-nQKnLp0p6mKadoYA58-TkkZ6rFLE95Q36sNCq7aDahZ9d-QxWQ',
            'dp' => 'nlOmG1WujjIbIFTjNTqbXIMOMZRMHKq8qh4V_EaVSpUjG2r-Vo7oRFf0KTl6etZQrNxNsaUgaVstCA7bgBDDQl8qXYe_awxylHf3Po_YXw2oC1nPH8kG9VhDF_gEh0QsAUuSVbOHwF0Ndmr5xWJBSpTpQBOB6Dln8NTiRA_29Q8',
            'dq' => 'berq2VpS73V4AwXm0nFrcRr4j0Fdt2aq1vUY3qEEAme4LabWhhfv0c_InN8FZu4Ni_tfABq6zIP5K2WRgAtk9NStKjH9jhJUYJBNnO0IunE3nIGx_61XezDQwnH15FnWgpE4ufmTeHIZtC-LJCcc1tS24aZkYb2pjDd6qxRYdEU',
            'e' => 'AQAB',
            'kid' => self::KID,
            'kty' => 'RSA',
            'n' => '0ghsEQTc1J_eAI2TgfqQUzzbmn-pygjZbmZOYIbWJ2kwrxflqsjBFFnE92vQtUfIaavSGwNV6u8S5DW7l2PCI3PoUiOdCg3075JvAVChZFwyU4QEhsETY3iZi1UYkCYDicgsw5n0v3iz5VewCJhntDsaghi4HN7LXBYDGwXlpDKg4j49OQfF0F3daN_I-NkJTQuZppdhgeeGnWO7yIph0aejLIgg1r0GXh90iboeIXpnUKDuorRW90oLOsvoliGDjdTX_bjwo5QibIWh_AcQ8fjOAs3SurrgUzLSErBQ0ULNoeiaFRYCQ_kDdz6teCK-FY9S1ma0xPxtu5ytdJg9nQ',
            'p' => '-vSg_1xoUEukwJr1QirceVGgfmvN9DEehfBvaYAhZEx85f147GYzuBsML5xD9boepJBTy6MqPDdK4aeeqbXu3nNtMQGliOMW7swVHcTwWOXSrAxsT8Wxa3YIA2MvSt2ji5KILwrM__yE8M11vvodtkXZ-lh9JBYEm6J6XMAHj1s',
            'q' => '1kE2cU5hoDfaTIVKZFrtHwHyV5ONfYdCDobzSE1hA5OYbcG_rvmDHLuB0yKZsnRJHEkeIek12ytGo3L2xNCp02Te_ffdxISgCgZ5KhCkRuq98DQCBUOPj0DTZCVH8KXi8qSUh20PQj0z9eXk1KmmG6K5vX5Fl_JR2anUriQtsGc',
            'qi' => 'iy8z8UKoBHELE5zfQIqxhANEJlA0F9LiIrcmZ1InCtAVieAlRiwic_3qZ8repNrKR2s22Xx0zDxOYQA6vhK2dAYvJIPS-7Vdvjrze43UKRIc5hPHvBLiD4SOEcqEdXKHRaYgZ6hMq9SIP-OnyccBkEIuhzBXnGtPr7YeOP1uyxU',
            'use' => 'sig',
        ];
    }
}
