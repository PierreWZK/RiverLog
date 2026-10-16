<?php

namespace Tests\Feature;

use App\HandHistory\HandImporter;
use App\Models\PokerSession;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Import du fichier de test : valeurs attendues du cahier des charges, section 8.3.
 */
class HandImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/betclic/spin-2026-01-29.txt'));
    }

    private function upload(string $content, string $name = 'export.txt'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_import_du_fichier_betclic(): void
    {
        $this->post('/imports', ['files' => [$this->upload($this->fixture())]])
            ->assertRedirect('/imports')
            ->assertSessionHas('status');

        $import = $this->user->handImports()->sole();
        $this->assertSame([41, 41, 0, 5], [$import->hands_found, $import->hands_imported, $import->duplicates, $import->tournaments_touched]);
        $this->assertNull($import->errors);
        Storage::disk('local')->assertExists($import->stored_path);

        $tournaments = $this->user->tournaments()->orderBy('started_at')->get();
        $this->assertSame([4, 2, 2, 2, 10], $tournaments->pluck('multiplier')->all());
        $this->assertSame([12, 11, 4, 3, 11], $tournaments->pluck('hands_count')->all());
        $this->assertSame([1, 2, 3, 3, 1], $tournaments->pluck('finish_position')->all());
        $this->assertSame(['3.00', '-1.00', '-1.00', '-1.00', '9.00'], $tournaments->pluck('net_result')->all());
        $this->assertSame([true, true, false, false, true], $tournaments->pluck('reached_heads_up')->all());
        $this->assertTrue($tournaments->every('is_complete'));
        $this->assertSame('2026-01-29 11:04:53', $tournaments->first()->started_at->toDateTimeString()); // heure de Paris
    }

    public function test_deux_sessions_automatiques(): void
    {
        $this->post('/imports', ['files' => [$this->upload($this->fixture())]]);

        $sessions = $this->user->pokerSessions()->orderBy('played_at')->get();

        $this->assertCount(2, $sessions);
        $this->assertTrue($sessions->every(fn (PokerSession $s) => $s->isImported()));
        // 11:04:53 → 11:12:44 (8 min) et 16:04:29 → 16:12:00 (8 min)
        $this->assertSame(['2026-01-29 11:04:53', '2026-01-29 16:04:29'], $sessions->map(fn ($s) => $s->played_at->toDateTimeString())->all());
        $this->assertSame([8, 8], $sessions->pluck('duration_minutes')->all());
        $this->assertSame(['2.00', '7.00'], $sessions->pluck('net_result')->all());
        $this->assertSame('spin', $sessions->first()->format->value);
        $this->assertSame('Import · 3 tournois : x2 ×2, x10 ×1', $sessions->last()->notes);
    }

    public function test_reimporter_ne_change_rien(): void
    {
        $this->post('/imports', ['files' => [$this->upload($this->fixture())]]);

        // Même fichier : refusé tout de suite
        $this->post('/imports', ['files' => [$this->upload($this->fixture())]])
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'déjà été importé'));
        $this->assertSame(1, $this->user->handImports()->count());

        // Fichier différent mais qui contient les mêmes mains : tout est ignoré comme doublon
        $this->post('/imports', ['files' => [$this->upload($this->fixture()."\n", 'autre-nom.txt')]]);
        $second = $this->user->handImports()->latest('id')->first();

        $this->assertSame([0, 41], [$second->hands_imported, $second->duplicates]);
        $this->assertSame(41, $this->user->hands()->count());
        $this->assertSame(5, $this->user->tournaments()->count());
        $this->assertSame(2, $this->user->pokerSessions()->count());
    }

    public function test_un_tournoi_reparti_sur_deux_exports(): void
    {
        $blocks = explode('------------', $this->fixture());
        // Premier export : seulement les mains anciennes du tournoi x10 (sans la main finale)
        $first = implode('------------', array_slice($blocks, 1, 10));

        $this->post('/imports', ['files' => [$this->upload($first, 'partie-1.txt')]]);
        $tournament = $this->user->tournaments()->where('multiplier', 10)->sole();
        $this->assertFalse($tournament->is_complete);
        $this->assertNull($tournament->net_result);
        $this->assertSame(0, $this->user->pokerSessions()->count()); // pas de session tant qu'il est incomplet

        $this->post('/imports', ['files' => [$this->upload($this->fixture(), 'complet.txt')]]);
        $tournament->refresh();
        $this->assertTrue($tournament->is_complete);
        $this->assertSame([11, '9.00'], [$tournament->hands_count, $tournament->net_result]);
    }

    public function test_import_d_un_zip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('hh/export.txt', $this->fixture());
        $zip->addFromString('lisez-moi.pdf', 'ignoré');
        $zip->addFromString('../piege.txt', 'ignoré');
        $zip->close();

        $this->post('/imports', ['files' => [new UploadedFile($path, 'historique.zip', 'application/zip', null, true)]]);

        $this->assertSame(41, $this->user->hands()->count());
        $this->assertSame('historique.zip', $this->user->handImports()->sole()->filename);
    }

    public function test_un_fichier_inconnu_est_signale(): void
    {
        $this->post('/imports', ['files' => [$this->upload("PokerStars Hand #123: Hold'em")]]);

        $import = $this->user->handImports()->sole();
        $this->assertSame(0, $import->hands_found);
        $this->assertStringContainsString('Format non reconnu', $import->errors[0]['message']);
    }

    public function test_seuls_txt_et_zip_sont_acceptes(): void
    {
        $this->post('/imports', ['files' => [UploadedFile::fake()->create('photo.png', 10, 'image/png')]])
            ->assertSessionHasErrors('files.0');
    }

    public function test_supprimer_un_import_recalcule_tout(): void
    {
        $this->post('/imports', ['files' => [$this->upload($this->fixture())]]);
        $import = $this->user->handImports()->sole();

        $this->delete("/imports/{$import->id}")->assertRedirect('/imports');

        $this->assertSame(0, $this->user->hands()->count());
        $this->assertSame(0, Tournament::count());
        $this->assertSame(0, $this->user->pokerSessions()->count());
        Storage::disk('local')->assertMissing($import->stored_path);
    }

    public function test_les_imports_d_un_autre_compte_sont_invisibles(): void
    {
        $other = User::factory()->create();
        $import = app(HandImporter::class)->importTexts($other, 'x.txt', str_repeat('a', 64), ['x.txt' => $this->fixture()]);

        $this->delete("/imports/{$import->id}")->assertNotFound();
        $this->get('/tournaments')->assertDontSee('Spin & Rush');
        $this->get('/tournaments/'.$other->tournaments()->first()->id)->assertNotFound();
        $this->assertSame(0, $this->user->pokerSessions()->count());
    }

    public function test_une_session_importee_ne_se_modifie_pas_a_la_main(): void
    {
        $this->post('/imports', ['files' => [$this->upload($this->fixture())]]);
        $session = $this->user->pokerSessions()->first();

        $this->get("/sessions/{$session->id}/edit")->assertForbidden();
        $this->delete("/sessions/{$session->id}")->assertForbidden();
    }
}
