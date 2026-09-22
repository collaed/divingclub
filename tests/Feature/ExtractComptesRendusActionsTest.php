<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LibraryFile;
use App\Models\User;
use App\Services\ComptesRendusActionExtractionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Tests the command's own logic — which document it picks, how it reacts to
 * what the extractor returns, and the processed/skip bookkeeping. The
 * extractor itself (real pdftotext/docx parsing, real Cloudflare calls) is
 * covered separately in ComptesRendusActionExtractionServiceTest, so it's
 * mocked here — same layering DocumentClassifierServiceTest and
 * BankReconciliationServiceTest already use for their own AI calls.
 */
#[Group('p1')]
class ExtractComptesRendusActionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function libraryFile(string $name, ?Carbon $uploadedAt = null, string $mime = 'application/pdf'): LibraryFile
    {
        $uploader = User::factory()->create();
        $file = LibraryFile::create([
            'filename' => $name, 'original_name' => $name, 'path' => "library/{$name}",
            'mime_type' => $mime, 'uploaded_by' => $uploader->id,
        ]);
        if ($uploadedAt) {
            $file->forceFill(['created_at' => $uploadedAt])->save();
        }

        return $file;
    }

    public function test_a_non_compte_rendu_file_is_ignored(): void
    {
        $this->libraryFile('Facture Steinfort.pdf');

        Artisan::call('compte-rendus:extract-actions');

        $this->assertStringContainsString('No unprocessed compte-rendu left', Artisan::output());
    }

    public function test_the_most_recently_uploaded_unprocessed_compte_rendu_is_picked(): void
    {
        $older = $this->libraryFile('CR bureau 01-01-2020.pdf', now()->subYears(5));
        $newer = $this->libraryFile('CR bureau 01-01-2026.pdf', now());
        $this->mock(ComptesRendusActionExtractionService::class, function ($m) {
            $m->shouldReceive('textFor')->once()->andReturn(str_repeat('text ', 20));
            $m->shouldReceive('extractActions')->once()->andReturn([]);
        });

        Artisan::call('compte-rendus:extract-actions');

        $this->assertNotNull($newer->fresh()->kanban_processed_at);
        $this->assertNull($older->fresh()->kanban_processed_at);
    }

    public function test_a_file_the_extractor_cannot_read_is_skipped_and_marked_processed_so_it_is_not_retried(): void
    {
        $file = $this->libraryFile('CR 2013.doc', null, 'application/msword');
        $this->mock(ComptesRendusActionExtractionService::class, fn ($m) => $m->shouldReceive('textFor')->once()->andReturnNull());

        Artisan::call('compte-rendus:extract-actions');

        $file->refresh();
        $this->assertNotNull($file->kanban_processed_at);
        $this->assertNotNull($file->kanban_skip_reason);
        $this->assertDatabaseCount('kanban_cards', 0);
    }

    public function test_a_document_with_too_little_extracted_text_is_treated_as_unreadable(): void
    {
        $file = $this->libraryFile('CR bureau 01-01-2026.pdf');
        $this->mock(ComptesRendusActionExtractionService::class, fn ($m) => $m->shouldReceive('textFor')->once()->andReturn('too short'));

        Artisan::call('compte-rendus:extract-actions');

        $this->assertNotNull($file->fresh()->kanban_processed_at);
    }

    public function test_a_document_with_no_action_items_is_marked_processed_with_no_cards(): void
    {
        $file = $this->libraryFile('CR bureau 01-01-2026.pdf');
        $this->mock(ComptesRendusActionExtractionService::class, function ($m) {
            $m->shouldReceive('textFor')->once()->andReturn(str_repeat('text ', 20));
            $m->shouldReceive('extractActions')->once()->andReturn([]);
        });

        Artisan::call('compte-rendus:extract-actions');

        $this->assertNotNull($file->fresh()->kanban_processed_at);
        $this->assertSame('No action items found.', $file->fresh()->kanban_skip_reason);
        $this->assertDatabaseCount('kanban_cards', 0);
    }

    public function test_a_failed_ai_call_leaves_the_document_unprocessed_for_the_next_run(): void
    {
        $file = $this->libraryFile('CR bureau 01-01-2026.pdf');
        $this->mock(ComptesRendusActionExtractionService::class, function ($m) {
            $m->shouldReceive('textFor')->once()->andReturn(str_repeat('text ', 20));
            $m->shouldReceive('extractActions')->once()->andReturnNull();
        });

        $code = Artisan::call('compte-rendus:extract-actions');

        $this->assertSame(1, $code);
        $this->assertNull($file->fresh()->kanban_processed_at);
    }

    public function test_a_push_failure_also_leaves_the_document_unprocessed(): void
    {
        $file = $this->libraryFile('CR bureau 01-01-2026.pdf');
        $this->mock(ComptesRendusActionExtractionService::class, function ($m) {
            $m->shouldReceive('textFor')->once()->andReturn(str_repeat('text ', 20));
            $m->shouldReceive('extractActions')->once()->andReturn([['title' => 'Do it', 'responsible' => null, 'context' => null]]);
            $m->shouldReceive('save')->once()->andReturn(false);
        });

        $code = Artisan::call('compte-rendus:extract-actions');

        $this->assertSame(1, $code);
        $this->assertNull($file->fresh()->kanban_processed_at);
    }

    public function test_the_document_date_is_parsed_from_the_filename_and_passed_to_save(): void
    {
        $file = $this->libraryFile('CR bureau 14-12-2016.pdf');
        $actions = [['title' => 'Do the thing', 'responsible' => 'Roger', 'context' => null]];
        $this->mock(ComptesRendusActionExtractionService::class, function ($m) use ($actions) {
            $m->shouldReceive('textFor')->once()->andReturn(str_repeat('text ', 20));
            $m->shouldReceive('extractActions')->once()->andReturn($actions);
            $m->shouldReceive('save')->once()
                ->withArgs(fn ($a, $f, $date) => $a === $actions && $date instanceof Carbon && $date->toDateString() === '2016-12-14')
                ->andReturn(true);
        });

        Artisan::call('compte-rendus:extract-actions');

        $this->assertNotNull($file->fresh()->kanban_processed_at);
        $this->assertNull($file->fresh()->kanban_skip_reason);
    }

    public function test_a_filename_without_a_recognisable_date_falls_back_to_the_upload_date(): void
    {
        $uploadedAt = Carbon::parse('2025-06-01 10:00:00');
        $file = $this->libraryFile('Compte rendu réunion.pdf', $uploadedAt);
        $this->mock(ComptesRendusActionExtractionService::class, function ($m) {
            $m->shouldReceive('textFor')->once()->andReturn(str_repeat('text ', 20));
            $m->shouldReceive('extractActions')->once()->andReturn([]);
        });

        Artisan::call('compte-rendus:extract-actions');

        $this->assertNotNull($file->fresh()->kanban_processed_at);
    }
}
