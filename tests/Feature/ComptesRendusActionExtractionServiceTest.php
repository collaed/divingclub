<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KanbanCard;
use App\Models\LibraryFile;
use App\Models\User;
use App\Services\ComptesRendusActionExtractionService;
use App\Services\WordTextExtractionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;
use ZipArchive;

#[Group('p1')]
class ComptesRendusActionExtractionServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $uploader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
        $this->uploader = User::factory()->create();
    }

    private function file(array $attrs): LibraryFile
    {
        return LibraryFile::create($attrs + ['uploaded_by' => $this->uploader->id]);
    }

    /** A minimal, real .docx: one paragraph per string, so WordTextExtractionService has real XML to parse. */
    private function makeDocx(array $paragraphs): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cr_').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $body = collect($paragraphs)->map(fn ($p) => '<w:p><w:r><w:t>'.htmlspecialchars($p, ENT_XML1).'</w:t></w:r></w:p>')->implode('');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="ns"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return $path;
    }

    public function test_word_text_extraction_reads_paragraphs_as_separate_lines(): void
    {
        $docx = $this->makeDocx(['Présents: Roger, Fred, Vincent.', 'Action: Roger contacte la piscine.']);

        $text = (new WordTextExtractionService)->extract($docx);

        $this->assertStringContainsString('Roger contacte la piscine', $text);
        $this->assertStringContainsString("\n", $text);
        @unlink($docx);
    }

    public function test_text_for_a_missing_file_is_null(): void
    {
        $file = $this->file(['filename' => 'x.pdf', 'original_name' => 'CR 1.pdf', 'path' => 'library/missing.pdf', 'mime_type' => 'application/pdf']);

        $this->assertNull(app(ComptesRendusActionExtractionService::class)->textFor($file));
    }

    public function test_text_for_a_legacy_doc_file_is_null(): void
    {
        Storage::disk('local')->put('library/old.doc', 'binary content, not really a .doc');
        $file = $this->file(['filename' => 'old.doc', 'original_name' => 'CR 2013.doc', 'path' => 'library/old.doc', 'mime_type' => 'application/msword']);

        $this->assertNull(app(ComptesRendusActionExtractionService::class)->textFor($file));
    }

    public function test_extract_actions_returns_null_without_cloudflare_credentials(): void
    {
        config(['services.cloudflare.account_id' => null, 'services.cloudflare.api_token' => null]);

        $this->assertNull(app(ComptesRendusActionExtractionService::class)->extractActions('some text', 'CR 1.pdf'));
    }

    public function test_extract_actions_parses_the_json_response(): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([
            'result' => ['choices' => [['message' => ['content' => json_encode([
                ['title' => 'Contacter la piscine', 'responsible' => 'Roger', 'context' => 'Le contrat arrive à échéance.'],
                ['title' => '', 'responsible' => null, 'context' => null], // blank title dropped
            ])]]]],
        ])]);

        $actions = app(ComptesRendusActionExtractionService::class)->extractActions('compte-rendu text', 'CR 1.pdf');

        $this->assertCount(1, $actions);
        $this->assertSame('Contacter la piscine', $actions[0]['title']);
        $this->assertSame('Roger', $actions[0]['responsible']);
    }

    public function test_extract_actions_returns_null_when_the_ai_call_fails(): void
    {
        config(['services.cloudflare.account_id' => 'acc', 'services.cloudflare.api_token' => 'tok']);
        Http::fake(['api.cloudflare.com/*' => Http::response([], 500)]);

        $this->assertNull(app(ComptesRendusActionExtractionService::class)->extractActions('text', 'CR 1.pdf'));
    }

    public function test_save_with_no_push_url_creates_local_cards(): void
    {
        config(['kanban.push_url' => null]);
        $file = $this->file(['filename' => 'a.pdf', 'original_name' => 'CR 1.pdf', 'path' => 'library/a.pdf', 'folder' => 'Bureau/Comptes-Rendus/2026', 'mime_type' => 'application/pdf']);

        $ok = app(ComptesRendusActionExtractionService::class)->save(
            [['title' => 'Do the thing', 'responsible' => 'Roger', 'context' => null]], $file, Carbon::parse('2026-09-03'),
        );

        $this->assertTrue($ok);
        $this->assertDatabaseHas('kanban_cards', ['title' => 'Do the thing', 'source_document_name' => 'CR 1.pdf', 'status' => KanbanCard::STATUS_TODO]);
    }

    public function test_save_with_no_actions_is_a_noop(): void
    {
        $file = $this->file(['filename' => 'a.pdf', 'original_name' => 'CR 1.pdf', 'path' => 'library/a.pdf', 'mime_type' => 'application/pdf']);

        $ok = app(ComptesRendusActionExtractionService::class)->save([], $file, null);

        $this->assertTrue($ok);
        $this->assertDatabaseCount('kanban_cards', 0);
    }

    public function test_save_with_a_push_url_posts_to_the_remote_board_instead_of_saving_locally(): void
    {
        config(['kanban.push_url' => 'https://test.clubcep.eu', 'kanban.push_token' => 'secret']);
        Http::fake(['test.clubcep.eu/*' => Http::response(['ok' => true], 200)]);
        $file = $this->file(['filename' => 'a.pdf', 'original_name' => 'CR 1.pdf', 'path' => 'library/a.pdf', 'mime_type' => 'application/pdf']);

        $ok = app(ComptesRendusActionExtractionService::class)->save(
            [['title' => 'Do the thing', 'responsible' => null, 'context' => null]], $file, null,
        );

        $this->assertTrue($ok);
        $this->assertDatabaseCount('kanban_cards', 0); // nothing saved locally
        Http::assertSent(fn ($r) => $r->url() === 'https://test.clubcep.eu/api/kanban/cards' && $r->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_save_with_a_push_url_that_refuses_the_request_reports_failure(): void
    {
        config(['kanban.push_url' => 'https://test.clubcep.eu', 'kanban.push_token' => 'secret']);
        Http::fake(['test.clubcep.eu/*' => Http::response(['message' => 'nope'], 403)]);
        $file = $this->file(['filename' => 'a.pdf', 'original_name' => 'CR 1.pdf', 'path' => 'library/a.pdf', 'mime_type' => 'application/pdf']);

        $ok = app(ComptesRendusActionExtractionService::class)->save(
            [['title' => 'Do the thing', 'responsible' => null, 'context' => null]], $file, null,
        );

        $this->assertFalse($ok);
    }
}
