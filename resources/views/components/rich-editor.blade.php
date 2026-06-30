{{-- TinyMCE 6 rich editor — include once per page, then add class="tinymce" to any textarea --}}
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
    images_upload_url: false,
    automatic_uploads: false,
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
