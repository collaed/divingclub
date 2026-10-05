{{--
    TinyMCE 6 rich editor — include once per page, then add class="tinymce" to
    any textarea.

    Pass :upload-url="route(...)" to enable real inline image upload (drag/drop
    or the image dialog's upload tab): the endpoint must accept a multipart
    `file` field and return JSON {"location": "https://.../image.jpg"} — see
    Admin\ArticleController::uploadImage() for the reference implementation.
    Omit it (the default) to keep the previous behaviour: no upload, the image
    dialog only accepts an already-hosted URL.
--}}
@props(['uploadUrl' => null])
@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.6/tinymce.min.js" integrity="sha384-zFzebFBjO2w19oyogAFilUklY4d79QgCG5KxflEIHzc03UVoZ7hTQ9MsM334s4yD" crossorigin="anonymous" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: 'textarea.tinymce',
    height: 350,
    menubar: false,
    plugins: 'lists link image table code fullscreen media autolink',
    toolbar: 'blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | link image media table | code fullscreen',
    block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Blockquote=blockquote',
    // The alignment buttons apply Bootstrap utility classes instead of
    // TinyMCE's default inline style="text-align:...". HtmlSanitizer's
    // presets do not allow a style attribute on block elements (only on
    // img/span), so an inline-style alignment is silently stripped the
    // moment the form is saved — the content looks fine in the editor, then
    // "loses" its centering forever with no error shown anywhere. Classes
    // are on the allow-list, so they survive the round trip.
    formats: {
        alignleft: { selector: 'p,h2,h3,h4,li,div,blockquote', classes: 'text-start', remove: 'all' },
        aligncenter: { selector: 'p,h2,h3,h4,li,div,blockquote', classes: 'text-center', remove: 'all' },
        alignright: { selector: 'p,h2,h3,h4,li,div,blockquote', classes: 'text-end', remove: 'all' },
    },
@if($uploadUrl)
    automatic_uploads: true,
    // Pasting a screenshot/image directly would otherwise embed it as a giant
    // base64 data: URI in the body — bypasses storage entirely, bloats the
    // database row, and isn't sanitized as an image at all. Routing every
    // image through the real upload endpoint keeps one consistent path.
    paste_data_images: false,
    images_upload_handler: function (blobInfo) {
        return new Promise(function (resolve, reject) {
            var data = new FormData();
            data.append('file', blobInfo.blob(), blobInfo.filename());
            fetch('{{ $uploadUrl }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: data,
            }).then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            }).then(function (json) {
                resolve(json.location);
            }).catch(function (err) {
                reject('Image upload failed: ' + err.message);
            });
        });
    },
@else
    images_upload_url: false,
    automatic_uploads: false,
@endif
    file_picker_types: 'image',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; }',
    image_advtab: true,
    image_class_list: [
        {title: 'Responsive', value: 'img-fluid'},
        {title: 'Float left', value: 'img-fluid float-start me-3 mb-2'},
        {title: 'Float right', value: 'img-fluid float-end ms-3 mb-2'},
        {title: 'Centered', value: 'img-fluid d-block mx-auto'},
    ],
    promotion: false,
    branding: false,
    license_key: 'gpl',
});
</script>
@endpush
@endonce
