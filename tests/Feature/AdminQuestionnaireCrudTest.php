<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminQuestionnaireCrudTest extends TestCase
{
    protected function tearDown(): void
    {
        $ids = Questionnaire::where('title', 'like', 'Uji Kuisioner%')->pluck('id');
        QuestionnaireResponse::whereIn('questionnaire_id', $ids)->delete();
        Questionnaire::whereIn('id', $ids)->delete();

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
            'title' => 'Uji Kuisioner Portal',
            'description' => 'Kuisioner untuk pengujian fitur admin.',
            'target_url' => 'https://forms.gle/example-questionnaire',
            'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'sort_order' => 60,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_pegawai_is_forbidden_from_questionnaire_management(): void
    {
        $this->actingAs($this->pegawai())
            ->get(route('admin.questionnaires.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_questionnaire_list_and_create_form(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin())
            ->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertSee('Manajemen Kuisioner');

        $this->actingAs($this->admin())
            ->get(route('admin.questionnaires.create'))
            ->assertOk()
            ->assertSee('Tambah Kuisioner');
    }

    public function test_admin_can_create_questionnaire_with_uploaded_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.questionnaires.store'), $this->payload([
                'image' => UploadedFile::fake()->image('kuisioner.jpg', 1200, 675),
            ]))
            ->assertRedirect();

        $questionnaire = Questionnaire::where('title', 'Uji Kuisioner Portal')->firstOrFail();

        $this->assertSame($this->admin()->id, $questionnaire->created_by);
        $this->assertTrue($questionnaire->is_active);
        $this->assertSame(60, $questionnaire->sort_order);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $questionnaire->banner_image));
    }

    public function test_invalid_target_url_and_period_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.questionnaires.store'), $this->payload([
                'target_url' => 'bukan-url',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors(['target_url', 'ends_at']);

        $this->assertNull(Questionnaire::where('title', 'Uji Kuisioner Portal')->first());
    }

    public function test_admin_can_update_and_deactivate_questionnaire(): void
    {
        $questionnaire = Questionnaire::create([
            'created_by' => $this->admin()->id,
            'title' => 'Uji Kuisioner Ubah',
            'description' => null,
            'banner_image' => null,
            'target_url' => 'https://forms.gle/old-form',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 10,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.questionnaires.update', $questionnaire), $this->payload([
                'title' => 'Uji Kuisioner Diubah',
                'target_url' => 'https://forms.gle/new-form',
                'is_active' => '0',
            ]))
            ->assertRedirect(route('admin.questionnaires.edit', $questionnaire));

        $questionnaire->refresh();
        $this->assertSame('Uji Kuisioner Diubah', $questionnaire->title);
        $this->assertSame('https://forms.gle/new-form', $questionnaire->target_url);
        $this->assertFalse($questionnaire->is_active);
    }

    public function test_questionnaire_with_responses_cannot_be_deleted(): void
    {
        $questionnaire = Questionnaire::create([
            'created_by' => $this->admin()->id,
            'title' => 'Uji Kuisioner Berrespons',
            'description' => null,
            'banner_image' => null,
            'target_url' => 'https://forms.gle/response-form',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 10,
        ]);

        QuestionnaireResponse::create([
            'questionnaire_id' => $questionnaire->id,
            'user_id' => $this->pegawai()->id,
            'clicked_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.questionnaires.destroy', $questionnaire))
            ->assertRedirect(route('admin.questionnaires.index'))
            ->assertSessionHasErrors('questionnaire');

        $this->assertNotNull(Questionnaire::find($questionnaire->id));
        $this->assertDatabaseHas('questionnaire_responses', [
            'questionnaire_id' => $questionnaire->id,
            'user_id' => $this->pegawai()->id,
        ]);
    }

    public function test_admin_can_delete_questionnaire_without_responses_and_managed_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('questionnaires/test-delete.webp', 'image');

        $questionnaire = Questionnaire::create([
            'created_by' => $this->admin()->id,
            'title' => 'Uji Kuisioner Hapus',
            'description' => null,
            'banner_image' => '/storage/questionnaires/test-delete.webp',
            'target_url' => 'https://forms.gle/delete-form',
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 10,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.questionnaires.destroy', $questionnaire))
            ->assertRedirect(route('admin.questionnaires.index'));

        $this->assertNull(Questionnaire::find($questionnaire->id));
        Storage::disk('public')->assertMissing('questionnaires/test-delete.webp');
    }
}
