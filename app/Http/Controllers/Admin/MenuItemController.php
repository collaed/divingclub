<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\Article;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class MenuItemController extends Controller
{
    public function index(): View
    {
        return view('admin.menu-items.index', [
            'menuItems' => MenuItem::forNav(),
            'articles' => Article::orderBy('title')->get(['id', 'title', 'slug']),
            'roleNames' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse|JsonResponse
    {
        $menuItem = MenuItem::create($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'html' => $this->renderFragment($menuItem)]);
        }

        return redirect()->route('admin.menu-items.index')->with('success', __('Menu item added.'));
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $menuItem->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'html' => $this->renderFragment($menuItem)]);
        }

        return redirect()->route('admin.menu-items.index')->with('success', __('Menu item updated.'));
    }

    public function destroy(MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $menuItem->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Menu item removed.'));
    }

    private function renderFragment(MenuItem $menuItem): string
    {
        $menuItem->load(['article', 'children']);

        $data = [
            'articles' => Article::orderBy('title')->get(['id', 'title', 'slug']),
            'roleNames' => Role::orderBy('name')->pluck('name'),
        ];

        if ($menuItem->parent_id === null) {
            return view('admin.menu-items._dropdown', ['dropdown' => $menuItem, ...$data])->render();
        }

        return view('admin.menu-items._row', ['item' => $menuItem, ...$data])->render();
    }
}
