<?php

/**
 * Homepage layout configuration and widget rendering.
 *
 * The homepage is composed of draggable widgets whose order and settings
 * are stored as JSON in theme_settings. Bureau admins can reorder,
 * show/hide, and configure widgets via an inline edit mode.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Link;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Http\Request;

class HomepageLayoutController extends Controller
{
    /** Default layout when none is saved. */
    public static function defaultLayout(): array
    {
        return [
            ['type' => 'hero', 'enabled' => true, 'zone' => 'top', 'visibility' => 'public', 'config' => ['height' => '350px', 'title' => ThemeSetting::get('club_full_name', config('app.name')), 'subtitle' => 'Dive with us in Luxembourg 🤿']],
            ['type' => 'welcome', 'enabled' => true, 'zone' => 'main', 'visibility' => 'public', 'config' => ['text' => 'Welcome to DivingClub']],
            ['type' => 'articles', 'enabled' => true, 'zone' => 'main', 'visibility' => 'public', 'config' => ['limit' => 10]],
            ['type' => 'upcoming_events', 'enabled' => false, 'zone' => 'main', 'visibility' => 'members', 'config' => ['limit' => 5]],
            ['type' => 'quick_links', 'enabled' => true, 'zone' => 'sidebar', 'visibility' => 'public', 'config' => []],
            ['type' => 'photos', 'enabled' => true, 'zone' => 'sidebar', 'visibility' => 'public', 'config' => ['count' => 8]],
            ['type' => 'custom_html', 'enabled' => false, 'zone' => 'sidebar', 'visibility' => 'public', 'config' => ['html' => '']],
        ];
    }

    /** Widget type metadata. */
    public static function widgetTypes(): array
    {
        return [
            'hero' => ['icon' => '🖼️', 'label' => 'Hero Slideshow', 'zones' => ['top']],
            'welcome' => ['icon' => '👋', 'label' => 'Welcome Text', 'zones' => ['main', 'top']],
            'articles' => ['icon' => '📰', 'label' => 'Article Stream', 'zones' => ['main']],
            'upcoming_events' => ['icon' => '📅', 'label' => 'Upcoming Events', 'zones' => ['main', 'sidebar']],
            'quick_links' => ['icon' => '🔗', 'label' => 'Quick Links', 'zones' => ['sidebar']],
            'photos' => ['icon' => '📸', 'label' => 'Photo Gallery', 'zones' => ['sidebar', 'main']],
            'custom_html' => ['icon' => '✏️', 'label' => 'Custom HTML', 'zones' => ['main', 'sidebar', 'top']],
        ];
    }

    /** Get the saved layout or default. */
    public static function getLayout(): array
    {
        $json = ThemeSetting::get('homepage_layout');

        return $json ? json_decode($json, true) : self::defaultLayout();
    }

    /** Save layout via AJAX. */
    public function saveLayout(Request $request)
    {
        abort_unless(auth()->user()?->isBureauMaster(), 403);

        $layout = $request->input('layout');
        if (! is_array($layout)) {
            return response()->json(['error' => 'Invalid layout'], 422);
        }

        ThemeSetting::set('homepage_layout', json_encode($layout));

        return response()->json(['ok' => true]);
    }

    /** Check if a widget is visible to the current user. */
    public static function isVisibleTo(array $widget, ?User $user): bool
    {
        $vis = $widget['visibility'] ?? 'public';

        return match ($vis) {
            'public' => true,
            'members' => (bool) $user,
            'instructors' => $user && ($user->isBureau() || $user->hasRole('instructor')),
            'bureau' => $user && $user->isBureau(),
            default => true,
        };
    }

    /** Load widget data based on type. */
    public static function loadWidgetData(array $widget): array
    {
        return match ($widget['type']) {
            'hero' => ['photos' => auth()->check()
                ? EventPhoto::randomForMembers($widget['config']['count'] ?? 8)->get()
                : EventPhoto::randomPublic($widget['config']['count'] ?? 8)->get()],
            'articles' => ['articles' => Article::active()->where('is_public', true)
                ->where('article_type', '!=', 'classified')->where('sort_order', '>=', 0)
                ->with('author.detail')->orderByDesc('created_at')
                ->limit($widget['config']['limit'] ?? 10)->get()],
            'quick_links' => ['links' => Link::where('is_public', true)->orderBy('sort_order')->get()],
            'photos' => ['photos' => auth()->check()
                ? EventPhoto::randomForMembers($widget['config']['count'] ?? 8)->get()
                : EventPhoto::randomPublic($widget['config']['count'] ?? 8)->get()],
            'upcoming_events' => ['events' => auth()->check()
                ? Event::where('event_date', '>=', now())
                    ->withCount('registrations')
                    ->orderBy('event_date')->limit($widget['config']['limit'] ?? 5)->get()
                : collect()],
            default => [],
        };
    }
}
