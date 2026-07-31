<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Link;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $links = Link::orderBy('sort_order')->get();

        return view('admin.links.index', compact('links'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ]);
        $validated['is_public'] = $request->boolean('is_public');

        Link::create($validated);

        return back()->with('success', __('Link added.'));
    }

    public function destroy(Link $link): RedirectResponse
    {
        $link->delete();

        return back()->with('success', __('Link removed.'));
    }
}
