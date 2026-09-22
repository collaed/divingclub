<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KanbanCard;
use App\Models\LibraryFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Turns one compte-rendu into kanban cards: extract its text (pdftotext for
 * PDF, a small XML strip for .docx — these are typed documents, not scans,
 * so no OCR is needed), ask Cloudflare Workers AI for the action items and
 * who's responsible, then either save them locally or push them to another
 * app's board (see config/kanban.php — production has the documents,
 * staging has the board being reviewed).
 */
class ComptesRendusActionExtractionService
{
    public function __construct(
        private PdfTextExtractionService $pdf,
        private WordTextExtractionService $word,
    ) {}

    /** Extracted text, or null when the format isn't supported / the file is missing. */
    public function textFor(LibraryFile $file): ?string
    {
        if (! Storage::disk('local')->exists($file->path)) {
            return null;
        }

        $path = Storage::disk('local')->path($file->path);

        return match ($file->mime_type) {
            'application/pdf' => $this->pdf->extract($path),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->word->extract($path),
            default => null, // legacy .doc: no converter installed on either server
        };
    }

    /**
     * @return array<int, array{title: string, responsible: string|null, context: string|null}>|null
     *                                                                                               null means the call itself failed (worth retrying later);
     *                                                                                               an empty array means it succeeded and found nothing to act on.
     */
    public function extractActions(string $text, string $documentLabel): ?array
    {
        $accountId = config('services.cloudflare.account_id');
        $apiToken = config('services.cloudflare.api_token');
        if (! $accountId || ! $apiToken) {
            return null;
        }

        $prompt = "This is the text of \"{$documentLabel}\", the minutes (compte-rendu) of a diving club committee's meeting."
            .' List every concrete action item or task that someone was asked to do — not general discussion, decisions with nothing left to do, or routine reports.'
            .' For each one give a short title (a few words, in French, matching the document\'s language), who is responsible if a name is given (or null), and a short'
            .' supporting quote or paraphrase from the text (one sentence).'
            ."\n\nDocument text:\n".mb_substr($text, 0, 6000)
            ."\n\nRespond with ONLY a JSON array, no other text, like:\n"
            .'[{"title": "...", "responsible": "..." or null, "context": "..."}]'
            .' Respond with [] if there are no action items.';

        try {
            $response = Http::withToken($apiToken)->timeout(60)->post(
                "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/meta/llama-3.1-8b-instruct",
                ['messages' => [['role' => 'user', 'content' => $prompt]]]
            );

            if (! $response->ok()) {
                Log::warning('Cloudflare AI compte-rendu extraction failed', ['status' => $response->status()]);

                return null;
            }

            $content = $response->json('result.choices.0.message.content');
            if (! is_string($content) || $content === '') {
                return null;
            }

            $content = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($content)));
            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                return null;
            }

            return collect($decoded)
                ->filter(fn ($row): bool => is_array($row) && is_string($row['title'] ?? null) && trim($row['title']) !== '')
                ->map(fn (array $row): array => [
                    'title' => trim($row['title']),
                    'responsible' => is_string($row['responsible'] ?? null) && trim($row['responsible']) !== '' ? trim($row['responsible']) : null,
                    'context' => is_string($row['context'] ?? null) && trim($row['context']) !== '' ? trim($row['context']) : null,
                ])
                ->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Cloudflare AI compte-rendu extraction failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, array{title: string, responsible: string|null, context: string|null}>  $actions
     * @return bool whether every card was saved (locally, or accepted by the remote board)
     */
    public function save(array $actions, LibraryFile $file, ?Carbon $documentDate): bool
    {
        if ($actions === []) {
            return true;
        }

        $payload = [
            'source_document_name' => $file->original_name,
            'source_document_folder' => $file->folder,
            'source_document_date' => $documentDate?->toDateString(),
            'actions' => $actions,
        ];

        $pushUrl = config('kanban.push_url');
        if (is_string($pushUrl) && $pushUrl !== '') {
            return $this->push($pushUrl, $payload);
        }

        foreach ($actions as $action) {
            KanbanCard::create([
                'title' => $action['title'],
                'responsible' => $action['responsible'],
                'context' => $action['context'],
                'source_document_name' => $file->original_name,
                'source_document_folder' => $file->folder,
                'source_document_date' => $documentDate,
            ]);
        }

        return true;
    }

    /** @param  array<string, mixed>  $payload */
    private function push(string $baseUrl, array $payload): bool
    {
        try {
            $response = Http::withToken((string) config('kanban.push_token'))
                ->timeout(30)
                ->post(rtrim($baseUrl, '/').'/api/kanban/cards', $payload);

            if (! $response->ok()) {
                Log::warning('Kanban card push refused', ['status' => $response->status(), 'body' => $response->body()]);
            }

            return $response->ok();
        } catch (\Throwable $e) {
            Log::warning('Kanban card push failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
