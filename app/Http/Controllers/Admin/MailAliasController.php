<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMailAliasRequest;
use App\Models\MailAlias;
use App\Models\User;
use App\Services\AliasAllocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MailAliasController extends Controller
{
    /** Suggest a unique alias for a member (AJAX helper for the edit form). */
    public function suggest(User $user): JsonResponse
    {
        $user->load('detail', 'mailAlias');

        return response()->json([
            'suggestion' => AliasAllocator::suggest($user, $user->mailAlias?->id),
            'current' => $user->mailAlias?->alias,
        ]);
    }

    /** Create or update a member's stable club alias (bureau confirms/overrides). */
    public function store(StoreMailAliasRequest $request, User $user): RedirectResponse
    {
        MailAlias::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'member'],
            ['alias' => $request->validated()['alias'], 'active' => true],
        );

        return back()
            ->with('success', __('Club alias saved for :name.', ['name' => $user->name]))
            ->withInput(['tab' => 'info']);
    }
}
