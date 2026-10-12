<?php

declare(strict_types=1);

use App\Models\Article;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('type'); // dropdown | link | divider
            $table->string('icon')->nullable();
            $table->string('label')->nullable();
            $table->string('link_type')->nullable(); // article | route | url
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('target')->nullable(); // route name, or a literal URL/path
            $table->boolean('auth_required')->nullable(); // null=anyone, true=auth only, false=guest only
            $table->json('roles')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }

    /**
     * Carry today's hardcoded About/Calendar/Members/Resources/My Account
     * navbar dropdowns (resources/views/components/layout.blade.php) into
     * the new table verbatim, so the dynamic render produces an identical
     * menu on first deploy.
     */
    private function seed(): void
    {
        $articleId = fn (string $slug): ?int => Article::where('slug', $slug)->value('id');

        $dropdown = function (string $label, int $sortOrder, ?bool $authRequired, array $children): void {
            $parentId = DB::table('menu_items')->insertGetId([
                'type' => 'dropdown',
                'label' => $label,
                'auth_required' => $authRequired,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($children as $i => $child) {
                DB::table('menu_items')->insert($child + [
                    'parent_id' => $parentId,
                    'sort_order' => ($i + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        };

        $link = fn (string $icon, ?string $label, string $linkType, ?int $articleId = null, ?string $target = null, ?array $roles = null): array => [
            'type' => 'link',
            'icon' => $icon,
            'label' => $label,
            'link_type' => $linkType,
            'article_id' => $articleId,
            'target' => $target,
            'roles' => $roles ? json_encode($roles) : null,
        ];

        $divider = fn (): array => ['type' => 'divider'];

        // Only values/history/instructors/member-figures already read exactly
        // like their current nav label — those sync live (label=null). The
        // other article-linked items below (bureau, contact-info,
        // horaire-regulier, first-certification, local) have a real,
        // longer/French article title that would visibly change the navbar
        // the moment this migration runs, so they keep today's curated text
        // as an explicit override; an admin can clear it later, once they've
        // confirmed the full article title actually reads well in a dropdown.
        $dropdown('About', 20, null, [
            $link('🤝', null, 'article', $articleId('values')),
            $link('🏛️', null, 'article', $articleId('history')),
            $link('👥', 'The Bureau', 'article', $articleId('bureau')),
            $link('🎓', null, 'article', $articleId('instructors')),
            $link('📊', null, 'article', $articleId('member-figures')),
            $link('📬', 'Contact & Social', 'article', $articleId('contact-info')),
            $divider(),
            $link('💶', 'Membership Fees', 'route', null, 'dues.show'),
        ]);

        $dropdown('Calendar', 30, true, [
            $link('📆', 'Events', 'route', null, 'events.index'),
            $link('🗓️', 'Training Schedule', 'article', $articleId('horaire-regulier-uViG0')),
            $link('📅', 'Instructor Availability', 'route', null, 'availability.index', ['bureau_master', 'bureau_finance', 'bureau_technical', 'instructor']),
        ]);

        $dropdown('Members', 40, true, [
            $link('📇', 'Directory', 'route', null, 'members.directory'),
            $link('📸', 'Trombinoscope', 'route', null, 'members.trombinoscope'),
            $link('🤝', 'Buddies', 'route', null, 'buddies.index'),
            $link('🏷️', 'Classifieds', 'route', null, 'classifieds.index'),
        ]);

        $dropdown('Resources', 50, true, [
            $link('🎓', 'First Certification', 'article', $articleId('first-certification')),
            $divider(),
            $link('📁', 'Documents', 'route', null, 'documents.index'),
            $link('📸', 'Photo Gallery', 'route', null, 'gallery'),
            $link('🏠', 'Our Warehouse', 'article', $articleId('local')),
        ]);

        $dropdown('My Account', 60, true, [
            $link('👤', 'My Profile', 'route', null, 'profile.show'),
            $link('🔒', 'Privacy', 'route', null, 'gdpr.consents'),
            $link('📬', 'Contact Us', 'route', null, 'contact'),
        ]);
    }
};
