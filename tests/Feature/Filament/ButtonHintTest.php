<?php

namespace Tests\Feature\Filament;

use App\Filament\Components\ButtonHint;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ButtonHintTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_button_hint_creates_valid_filament_action(): void
    {
        $hint = ButtonHint::make(
            'Karyawan diizinkan mulai melakukan absen masuk sekian menit sebelum waktu shift dimulai.',
            'Panduan Batas Awal Masuk'
        );

        $this->assertInstanceOf(Action::class, $hint);
        $this->assertTrue($hint->isLabelHidden());
        $this->assertSame('Panduan Batas Awal Masuk', $hint->getModalHeading());
        $this->assertNull($hint->getModalDescription());
        $this->assertNotNull($hint->getModalContent());
        $this->assertSame('info', $hint->getColor());
        $this->assertSame(Width::Medium, $hint->getModalWidth());
        $this->assertSame('Tutup Panduan', $hint->getModalCancelActionLabel());
        $this->assertNull($hint->getModalSubmitAction());
    }

    public function test_button_hint_defaults_heading_to_panduan_resmi_when_title_is_null(): void
    {
        $hint = ButtonHint::make('Petunjuk singkat');

        $this->assertSame('Panduan & Keterangan Resmi', $hint->getModalHeading());
        $this->assertNull($hint->getModalDescription());
        $this->assertNotNull($hint->getModalContent());
    }

    public function test_button_hint_formats_interactive_copy_chips_with_fallback_attributes(): void
    {
        $html = ButtonHint::formatTokens('Gunakan token {seq} dan ${QR_CODE} pada berkas.');

        $this->assertStringContainsString('data-token="{seq}"', $html);
        $this->assertStringContainsString('data-token="${QR_CODE}"', $html);
        $this->assertStringContainsString('copyToken($el.getAttribute(\'data-token\'), $el)', $html);
        $this->assertStringContainsString('hint-chip', $html);
        $this->assertStringContainsString('✓ Tersalin', $html);
    }

    public function test_super_admin_can_render_forms_with_button_hints_successfully(): void
    {
        $this->seed(MasterDataSeeder::class);

        $superAdmin = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $this->actingAs($superAdmin);

        $routes = [
            '/admin/units/create',
            '/admin/document-categories/create',
            '/admin/users/create',
            '/admin/post-categories/create',
            '/admin/accreditation-criteria/create',
            '/admin/academic-years/create',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertOk();

            if ($route === '/admin/document-categories/create') {
                $response->assertDontSee('Stempel Barcode Digital');
                $response->assertDontSee('Posisi Barcode QR Code');
                $response->assertDontSee('Offset Horizontal (X mm)');
                $response->assertDontSee('Offset Vertikal (Y mm)');
                $response->assertDontSee('${QR_CODE}');
            }
        }
    }
}
