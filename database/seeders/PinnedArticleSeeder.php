<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PinnedArticleSeeder extends Seeder
{
    public function run(): void
    {
        $stubs = [
            [
                'title' => 'Training Schedule',
                'slug' => 'schedule',
                'article_type' => 'training',
                'is_public' => true,
                'sort_order' => 100,
                'body' => '<h4>🗓️ Training Schedule</h4>
<p><em>This page is maintained by the Bureau. Edit it in Administration → Articles.</em></p>
<h5>Pool Sessions</h5>
<table class="table table-sm">
<thead><tr><th>Day</th><th>Time</th><th>Location</th><th>Level</th></tr></thead>
<tbody>
<tr><td>Tuesday</td><td>19:30 – 21:00</td><td>Piscine de Bonnevoie</td><td>All levels</td></tr>
<tr><td>Thursday</td><td>19:30 – 21:00</td><td>Piscine de Mersch</td><td>Beginners</td></tr>
<tr><td>Saturday</td><td>10:00 – 12:00</td><td>Piscine de Bonnevoie</td><td>Advanced / Instructors</td></tr>
</tbody>
</table>
<h5>Open Water</h5>
<p>Open water sessions are organized seasonally (May–October). Check the <a href="/events">Calendar</a> for upcoming dates.</p>
<h5>Holiday Breaks</h5>
<p>No training during school holidays (Toussaint, Christmas, Carnival, Easter). The season runs from September to June.</p>',
            ],
            [
                'title' => 'Our Values',
                'slug' => 'values',
                'article_type' => 'news',
                'is_public' => true,
                'sort_order' => 99,
                'body' => '<h4>🤝 Our Values</h4>
<p><em>Edit this page in Administration → Articles to reflect the club\'s identity.</em></p>
<ul>
<li><strong>Safety first</strong> — Every dive is planned, every buddy is briefed.</li>
<li><strong>Inclusivity</strong> — All ages, all levels, all nationalities welcome.</li>
<li><strong>Environmental respect</strong> — We leave only bubbles, take only memories.</li>
<li><strong>Continuous learning</strong> — From baptism to instructor, we grow together.</li>
<li><strong>Community</strong> — A club is its members. We share, we help, we celebrate.</li>
</ul>',
            ],
            [
                'title' => 'Contact & Social Networks',
                'slug' => 'contact-info',
                'article_type' => 'news',
                'is_public' => true,
                'sort_order' => 98,
                'body' => '<h4>📬 Contact & Social Networks</h4>
<p><em>Edit this page in Administration → Articles to add your actual contact details and social links.</em></p>
<h5>Email</h5>
<p>📧 {{ __('Contact us via the Contact page') }}</p>
<h5>Postal Address</h5>
<p>Your Diving Club<br>Address configured in Settings</p>
<h5>Social Networks</h5>
<ul>
<li>📘 Facebook: <a href="#">facebook.com/your-page</a></li>
<li>📸 Instagram: <a href="#">@your-handle</a></li>
<li>💬 WhatsApp group: <a href="#">Invite link</a></li>
</ul>
<h5>Training Locations</h5>
<p><strong>Piscine de Bonnevoie</strong> — Rue du Cimetière, L-1338 Luxembourg<br>
<strong>Piscine de Mersch</strong> — Krounebierg, L-7572 Mersch</p>',
            ],
            [
                'title' => 'Club History',
                'slug' => 'history',
                'article_type' => 'history',
                'is_public' => true,
                'sort_order' => 97,
                'body' => '<h4>🏛️ Club History</h4>
<p><em>Edit this page in Administration → Articles to tell the story of your club.</em></p>
<p>This diving club was founded by a group of diving enthusiasts who wanted to create a welcoming, multilingual community for underwater exploration.</p>
<p>Over the decades, the club has grown from a handful of members to a vibrant community of divers of all levels, nationalities, and backgrounds.</p>
<h5>Key Milestones</h5>
<ul>
<li><strong>Year of founding</strong> — Club established</li>
<li><strong>First international trip</strong> — …</li>
<li><strong>Affiliation with FLASSA</strong> — …</li>
<li><strong>Current era</strong> — Digital management, 30+ active members</li>
</ul>',
            ],
            [
                'title' => 'The Bureau',
                'slug' => 'bureau',
                'article_type' => 'news',
                'is_public' => true,
                'sort_order' => 96,
                'body' => '<h4>👥 The Bureau</h4>
<p><em>Edit this page in Administration → Articles to introduce the current bureau members.</em></p>
<p>The Bureau is the elected governing body of the club, responsible for day-to-day management, finances, and strategic direction.</p>
<h5>Current Bureau (Season 2025–2026)</h5>
<table class="table table-sm">
<thead><tr><th>Role</th><th>Name</th></tr></thead>
<tbody>
<tr><td>President</td><td><em>To be filled in</em></td></tr>
<tr><td>Vice-President</td><td><em>To be filled in</em></td></tr>
<tr><td>Treasurer</td><td><em>To be filled in</em></td></tr>
<tr><td>Secretary</td><td><em>To be filled in</em></td></tr>
<tr><td>Technical Director</td><td><em>To be filled in</em></td></tr>
</tbody>
</table>
<p>The Bureau meets monthly. Minutes are available in the <a href="/documents">Document Library</a>.</p>',
            ],
            [
                'title' => 'Our Members',
                'slug' => 'member-figures',
                'article_type' => 'news',
                'is_public' => true,
                'sort_order' => 95,
                'body' => '<h4>📊 Our Members</h4>
<p><em>This page shows a snapshot of the club\'s membership. Edit in Administration → Articles to update the figures or add commentary.</em></p>
<p>The club is proud of its diverse, international membership. Here is a breakdown of our community:</p>
<h5>By Gender</h5>
<p><em>Update with current figures from the Dashboard → Export CSV.</em></p>
<h5>By Nationality</h5>
<p>Our members come from across Europe and beyond — a true reflection of Luxembourg\'s multicultural spirit.</p>
<h5>By Language</h5>
<p>The club operates in French, German, Luxembourgish, and English. Many members speak multiple languages.</p>
<h5>By Certification Level</h5>
<p>From complete beginners to CMAS 3★ instructors, every level is represented.</p>',
            ],
            [
                'title' => 'Our Instructors',
                'slug' => 'instructors',
                'article_type' => 'training',
                'is_public' => true,
                'sort_order' => 94,
                'body' => '<h4>🎓 Our Instructors</h4>
<p><em>This page is auto-populated from instructor profiles. Instructors can fill in their bio, specialties, and motivation in My Profile → Diving tab.</em></p>
<p>Our volunteer instructors are the heart of the club. They bring diverse experience and a shared passion for teaching.</p>
<div id="instructor-list">
<p class="text-muted">Instructor profiles will appear here once they complete their instructor bio in their profile.</p>
</div>',
            ],
        ];

        foreach ($stubs as $stub) {
            Article::firstOrCreate(
                ['slug' => $stub['slug']],
                array_merge($stub, [
                    'is_published' => true,
                    'author_id' => 1,
                ])
            );
        }
    }
}
