<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Opd;
use App\Models\Questionnaire;
use App\Models\User;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

/**
 * Asset-path fields must reject directory traversal — in all three admin forms.
 *
 * Applications (icon_path), banners (image_path) and questionnaires
 * (image_path) accept either an http(s) URL or a path to a public asset. The
 * "public asset" branch is guarded by a regex that ALLOWS dots and slashes, so
 * on its own it happily matches `banners/../../.env`. ApplicationController has
 * always carried two extra guards against exactly that; banners and
 * questionnaires did not, which is the inconsistency this file locks shut.
 *
 * Impact is modest — the field is admin-only, and Flysystem refuses a traversing
 * path when the old image is later deleted, so the realistic outcome was a 500
 * rather than a file being removed. The reason to fix it is that three
 * controllers validating the same kind of field should not disagree about what
 * is acceptable, and the weakest of them sets the real limit.
 */
class AssetPathValidationTest extends TestCase
{
    /** Paths that must never be accepted, whichever form they arrive at. */
    private const TRAVERSAL_PATHS = [
        'naik satu tingkat' => 'banners/../secret.txt',
        'naik ke akar' => 'banners/../../../.env',
        'diawali titik ganda' => '../.env',
        'backslash Windows' => 'banners\\..\\..\\.env',
    ];

    private function admin(): User
    {
        return User::where('nip_nik', 'admin')->firstOrFail();
    }

    /**
     * Post every traversal path and report which ones got through.
     *
     * Collected rather than asserted one by one, so a failure names every path
     * that slipped instead of stopping at the first. assertSessionHasErrors()
     * is deliberately not used here: its third argument is the error BAG name,
     * not a message, so a custom message there sends it looking in a bag that
     * does not exist and the test fails for the wrong reason.
     *
     * @param  callable(string): array<string, mixed>  $payload
     * @return array<int, string>  labels of the paths that were accepted
     */
    private function pathsThatSlipped(string $route, string $field, callable $payload): array
    {
        $slipped = [];

        foreach (self::TRAVERSAL_PATHS as $label => $path) {
            $response = $this->actingAs($this->admin())->post($route, $payload($path));

            // Laravel's own assertion decides whether the field was rejected —
            // reading session('errors') by hand is unreliable, because the flash
            // bag is a ViewErrorBag on a fresh session and a plain array after
            // the session has been flushed. Catching the failure keeps the loop
            // going so one run names EVERY path that slipped, not just the first.
            try {
                $response->assertSessionHasErrors($field);
            } catch (ExpectationFailedException) {
                $slipped[] = "{$label} ({$path})";
            }

            $this->flushSession();
        }

        return $slipped;
    }

    protected function tearDown(): void
    {
        Application::where('slug', 'like', 'ujipath-%')->delete();
        Banner::where('title', 'like', 'UJIPATH%')->delete();
        Questionnaire::where('title', 'like', 'UJIPATH%')->delete();

        parent::tearDown();
    }

    // ------------------------------------------------------------- aplikasi

    /** @param array<string, mixed> $overrides */
    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'UJIPATH Aplikasi',
            'opd_id' => Opd::orderBy('id')->firstOrFail()->id,
            'slug' => 'ujipath-aplikasi',
            'description' => 'Aplikasi untuk pengujian path.',
            'app_group' => 'spbe',
            'category_ids' => [Category::where('slug', 'data')->firstOrFail()->id],
            'sort_order' => 99,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_icon_path_aplikasi_menolak_traversal(): void
    {
        $slipped = $this->pathsThatSlipped(
            route('admin.aplikasi.store'),
            'icon_path',
            fn (string $path): array => $this->applicationPayload(['icon_path' => $path]),
        );

        $this->assertSame([], $slipped, 'Path traversal lolos pada icon_path aplikasi: '.implode(' · ', $slipped));
        $this->assertSame(0, Application::where('slug', 'ujipath-aplikasi')->count());
    }

    public function test_icon_path_aplikasi_menerima_path_wajar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.aplikasi.store'), $this->applicationPayload([
                'icon_path' => 'images/icons/simpus.png',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Application::where('slug', 'ujipath-aplikasi')->count());
    }

    // -------------------------------------------------------------- banner

    /** @param array<string, mixed> $overrides */
    private function bannerPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'UJIPATH Banner',
            'description' => 'Banner untuk pengujian path.',
            'target_url' => 'https://banyumaskab.go.id/informasi',
            'sort_order' => 50,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_image_path_banner_menolak_traversal(): void
    {
        $slipped = $this->pathsThatSlipped(
            route('admin.banners.store'),
            'image_path',
            fn (string $path): array => $this->bannerPayload(['image_path' => $path]),
        );

        $this->assertSame([], $slipped, 'Path traversal lolos pada image_path banner: '.implode(' · ', $slipped));
        $this->assertSame(0, Banner::where('title', 'UJIPATH Banner')->count());
    }

    public function test_image_path_banner_menerima_path_wajar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->bannerPayload([
                'image_path' => '/storage/banners/pengumuman.jpg',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Banner::where('title', 'UJIPATH Banner')->count());
    }

    // ----------------------------------------------------------- kuisioner

    /** @param array<string, mixed> $overrides */
    private function questionnairePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'UJIPATH Kuisioner',
            'description' => 'Kuisioner untuk pengujian path.',
            'target_url' => 'https://forms.gle/example-questionnaire',
            'sort_order' => 60,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_image_path_kuisioner_menolak_traversal(): void
    {
        // The questionnaire form calls this field banner_image, not image_path —
        // it maps to questionnaires.banner_image.
        $slipped = $this->pathsThatSlipped(
            route('admin.questionnaires.store'),
            'banner_image',
            fn (string $path): array => $this->questionnairePayload(['banner_image' => $path]),
        );

        $this->assertSame([], $slipped, 'Path traversal lolos pada banner_image kuisioner: '.implode(' · ', $slipped));
        $this->assertSame(0, Questionnaire::where('title', 'UJIPATH Kuisioner')->count());
    }

    public function test_image_path_kuisioner_menerima_path_wajar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.questionnaires.store'), $this->questionnairePayload([
                'banner_image' => 'storage/questionnaires/survei.png',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Questionnaire::where('title', 'UJIPATH Kuisioner')->count());
    }
}
