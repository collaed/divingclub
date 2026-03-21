@extends('admin.guide.partials.guide-layout')
@section('content')
<h5>Content Management</h5>
<p>The CMS handles articles, classifieds, comments, and the document library.</p>

<h5>Article Types</h5>
<table class="table table-sm">
    <thead><tr><th>Type</th><th>Icon</th><th>Use</th></tr></thead>
    <tbody>
        @foreach(\App\Models\Article::TYPES as $key => $meta)
            <tr><td>{{ $key }}</td><td>{{ $meta['icon'] }}</td><td>{{ $meta['label'] }}</td></tr>
        @endforeach
    </tbody>
</table>
<p>Each type has a configurable background color (Settings → Appearance → Article Type Backgrounds).</p>

<h5>Creating Articles</h5>
<p>Administration → Articles → Create. Set title, type, body (rich text), featured image, and gallery images. Toggle Published and Public (visible without login).</p>

<h5>Auto-Translation</h5>
<p>Click "@icon('🌐') Generate translations" on any article to auto-translate to all configured languages. The scheduler also translates one untranslated article per hour automatically. Translations show as tabs on the article page with a @icon('🤖') indicator.</p>

<h5>Comments</h5>
<p>Authenticated members can comment on articles. Comments are threaded (up to 3 levels). Authors and bureau can delete comments.</p>

<h5>Classifieds</h5>
<p>Members can post buy/sell ads via the Classifieds page. Ads expire after 30 days and can be extended. Members manage their own ads.</p>

<h5>Document Library</h5>
<p>Administration → Document Library. Create folders, upload files (PDF, images, documents). Mark files as Public (visible to all members via Info → Documents) or Private (bureau-only). Image and PDF files show thumbnail previews.</p>
@endsection
