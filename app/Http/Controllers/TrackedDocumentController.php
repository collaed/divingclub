<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ResolveDocOpenGeo;
use App\Models\DocumentDispatchOpen;
use App\Models\DocumentDispatchRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackedDocumentController extends Controller
{
    /**
     * Public per-recipient link: record the open, then serve the PDF.
     * The token is the authorisation — no login required.
     */
    public function open(Request $request, string $token): StreamedResponse
    {
        $recipient = DocumentDispatchRecipient::with('dispatch.file')
            ->where('token', $token)
            ->firstOrFail();

        $open = DocumentDispatchOpen::create([
            'recipient_id' => $recipient->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 480, ''),
            'opened_at' => now(),
        ]);

        $recipient->forceFill([
            'opens_count' => $recipient->opens_count + 1,
            'first_opened_at' => $recipient->first_opened_at ?? now(),
        ])->save();

        ResolveDocOpenGeo::dispatch($open->id)->afterCommit();

        $file = $recipient->dispatch->file;
        abort_if($file === null || ! Storage::disk('local')->exists((string) $file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name ?: 'document.pdf');
    }
}
