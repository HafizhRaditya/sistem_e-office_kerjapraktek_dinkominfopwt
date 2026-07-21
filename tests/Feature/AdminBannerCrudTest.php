<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBannerCrudTest extends TestCase
{
    protected function tearDown(): void
    {
        Banner::where('title', 'like', 'Uji Banner%')->delete();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000002')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Uji Banner Portal',
            'description' => 'Banner untuk pengujian fitur admin.',
            'target_url' => 'https://banyumaskab.go.id/informasi',
            'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'sort_order' => 50,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_pegawai_is_forbidden_from_banner_management(): void
    {
        $this->actingAs($this->pegawai())
            ->get(route('admin.banners.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_banner_list_and_create_form(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('Manajemen Banner');

        $this->actingAs($this->admin())
            ->get(route('admin.banners.create'))
            ->assertOk()
            ->assertSee('Tambah Banner');
    }

    public function test_admin_can_create_banner(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload())
            ->assertRedirect();

        $banner = Banner::where('title', 'Uji Banner Portal')->firstOrFail();

        $this->assertSame($this->admin()->id, $banner->created_by);
        $this->assertTrue($banner->is_active);
        $this->assertSame(50, $banner->sort_order);
    }

    public function test_invalid_period_and_target_url_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload([
                'target_url' => 'bukan-url',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors(['target_url', 'ends_at']);

        $this->assertNull(Banner::where('title', 'Uji Banner Portal')->first());
    }

    public function test_admin_can_upload_and_replace_banner_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload([
                'image' => UploadedFile::fake()->image('banner-awal.jpg', 1200, 600),
            ]));

        $banner = Banner::where('title', 'Uji Banner Portal')->firstOrFail();
        $oldPath = str_replace('/storage/', '', $banner->image_path);
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.update', $banner), $this->payload([
                'title' => 'Uji Banner Diubah',
                'image' => UploadedFile::fake()->image('banner-baru.webp', 1200, 600),
            ]))
            ->assertRedirect(route('admin.banners.edit', $banner));

        $banner->refresh();
        $newPath = str_replace('/storage/', '', $banner->image_path);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertSame('Uji Banner Diubah', $banner->title);
    }

    public function test_admin_can_deactivate_and_delete_banner(): void
    {
        $banner = Banner::create([
            'created_by' => $this->admin()->id,
            'title' => 'Uji Banner Hapus',
            'description' => null,
            'image_path' => null,
            'target_url' => null,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 10,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.update', $banner), $this->payload([
                'title' => 'Uji Banner Hapus',
                'is_active' => null,
            ]))
            ->assertRedirect(route('admin.banners.edit', $banner));

        $this->assertFalse($banner->fresh()->is_active);

        $this->actingAs($this->admin())
            ->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect(route('admin.banners.index'));

        $this->assertNull(Banner::find($banner->id));
    }
}
