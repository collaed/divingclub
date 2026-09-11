{{-- Admin layout. Navigation lives in the "Admin" mega-menu (top nav) only —
     a left sidebar used to duplicate the same ~24 links as a second, always-
     fully-expanded nav column that towered over short pages (e.g. the
     dashboard) and pushed later content around. --}}
<x-layout :title="$title ?? __('Administration')" width="container-xl layout-admin">
    {{ $slot }}
</x-layout>
