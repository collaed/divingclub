<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestKanbanCardsRequest;
use App\Models\KanbanCard;
use Illuminate\Http\JsonResponse;

/**
 * Receives kanban cards extracted elsewhere — the compte-rendu extraction
 * task runs on whichever app has the source documents (production) and
 * pushes its findings here, to the app hosting the board (staging). See
 * config/kanban.php.
 */
class KanbanIngestController extends Controller
{
    public function store(IngestKanbanCardsRequest $request): JsonResponse
    {
        $v = $request->validated();
        foreach ($v['actions'] as $action) {
            KanbanCard::create([
                'title' => $action['title'],
                'responsible' => $action['responsible'] ?? null,
                'context' => $action['context'] ?? null,
                'source_document_name' => $v['source_document_name'],
                'source_document_folder' => $v['source_document_folder'] ?? null,
                'source_document_date' => $v['source_document_date'] ?? null,
            ]);
        }

        return response()->json(['ok' => true, 'created' => count($v['actions'])]);
    }
}
