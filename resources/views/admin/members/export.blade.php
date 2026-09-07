<x-admin-layout :title="__('Member Data Export')">
    <x-breadcrumb :items="[
        ['label' => __('Member Management'), 'url' => route('admin.members.index')],
        ['label' => __('Member Data Export')],
    ]" />

    <h4 class="mb-4">{{ __('Member Data Export') }}</h4>

    <div class="card dc-card" style="max-width: 720px;">
        <div class="card-body">
            <p class="mb-3">
                {{ __('Export every member with all related data flattened into a single spreadsheet row per member — one column per field. Licences, certifications and cotisation years are denormalized into their own columns, so the file is ready to filter, pivot and chart in Excel or Google Sheets.') }}
            </p>

            <p class="text-muted small mb-4">
                @icon('👥') {{ trans_choice('{0}No members yet|{1}:count member|[2,*]:count members', $memberCount, ['count' => $memberCount]) }}
            </p>

            <a href="{{ route('admin.members.export.download') }}" class="btn btn-primary">
                @icon('📊') {{ __('Download XLSX') }}
            </a>

            <p class="text-muted small mt-3 mb-0">
                {{ __('Column headers are in English so downstream statistics stay stable regardless of the interface language.') }}
            </p>
        </div>
    </div>
</x-admin-layout>
