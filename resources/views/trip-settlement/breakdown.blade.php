<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Cost Breakdown') }} — {{ $event->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 11pt; line-height: 1.4; color: #222; padding: 20mm; }
        h1 { font-size: 16pt; margin-bottom: 4px; }
        h2 { font-size: 12pt; margin-top: 16px; margin-bottom: 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .subtitle { color: #555; font-size: 10pt; margin-bottom: 16px; }
        .decisions { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; }
        .decisions dt { font-weight: 600; float: left; width: 220px; clear: left; }
        .decisions dd { margin-left: 230px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: right; }
        th { background: #f0f0f0; font-weight: 600; text-align: center; }
        td:first-child, th:first-child { text-align: left; }
        .text-center { text-align: center; }
        .positive { color: #c0392b; }
        .negative { color: #27ae60; }
        .zero { color: #888; }
        .summary-row { background: #f8f9fa; }
        .footer { margin-top: 24px; font-size: 9pt; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
        @media print {
            body { padding: 10mm; }
            .no-print { display: none; }
        }
        .no-print { margin-bottom: 16px; }
        .btn { display: inline-block; padding: 6px 16px; background: #003366; color: #fff; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 10pt; }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="{{ route('events.settlement.manage', $event) }}" class="btn">← {{ __('Back to manage') }}</a>
        <button onclick="window.print()" class="btn">🖨️ {{ __('Print') }}</button>
    </div>

    <h1>{{ __('Trip Cost Breakdown') }}</h1>
    <p class="subtitle">{{ $event->title }} — {{ $event->event_date->format('d/m/Y') }}@if($event->end_date && $event->end_date->ne($event->event_date)) → {{ $event->end_date->format('d/m/Y') }}@endif</p>

    <h2>{{ __('Settlement Parameters') }}</h2>
    <dl class="decisions">
        <dt>{{ __('Participants') }}</dt>
        <dd>{{ count($settlement['participants']) }}</dd>

        <dt>{{ __('Van riders') }}</dt>
        <dd>{{ collect($settlement['participants'])->where('transit_mode', 'van')->count() }}</dd>

        <dt>{{ __('Driver bounty (fixed amount)') }}</dt>
        <dd>{{ number_format($event->driver_bounty_total ?? 0, 2) }} €</dd>

        <dt>{{ __('Local daily van charge') }}</dt>
        <dd>{{ number_format($event->local_daily_charge ?? 0, 2) }} €/{{ __('day') }}</dd>

        <dt>{{ __('Global expenses (shared equally)') }}</dt>
        <dd>{{ number_format($settlement['global_pool'], 2) }} € → {{ number_format($settlement['global_pool'] / max(1, count($settlement['participants'])), 2) }} €/{{ __('person') }}</dd>

        <dt>{{ __('Transit expenses (van riders)') }}</dt>
        <dd>{{ number_format($settlement['transit_pool'], 2) }} € → {{ number_format($settlement['transit_pool'] / max(1, collect($settlement['participants'])->where('transit_mode', 'van')->count()), 2) }} €/{{ __('van rider') }}</dd>

        <dt>{{ __('Driver bounty distribution') }}</dt>
        <dd>
            @foreach(collect($settlement['participants'])->where('driving_percentage', '>', 0) as $driver)
                {{ $driver['name'] }} ({{ $driver['driving_percentage'] }}% = {{ number_format($driver['bounty_credit'], 2) }} €)@if(!$loop->last), @endif
            @endforeach
            @if(collect($settlement['participants'])->where('driving_percentage', '>', 0)->isEmpty())
                —
            @endif
        </dd>
    </dl>

    <h2>{{ __('Per-Participant Breakdown') }}</h2>
    <table id="breakdown-table">
        <thead>
            <tr>
                <th class="sortable-col" data-col="0" role="button" style="cursor:pointer">{{ __('Name') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="1" role="button" style="cursor:pointer">{{ __('Mode') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="2" role="button" style="cursor:pointer">{{ __('Shared') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="3" role="button" style="cursor:pointer">{{ __('Transit') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="4" role="button" style="cursor:pointer">{{ __('Local') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="5" role="button" style="cursor:pointer">{{ __('Bounty') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="6" role="button" style="cursor:pointer">{{ __('Prepaid') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="7" role="button" style="cursor:pointer">{{ __('Paid') }} <span class="sort-icon">↕</span></th>
                <th class="sortable-col" data-col="8" role="button" style="cursor:pointer">{{ __('Balance') }} <span class="sort-icon">↕</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($settlement['participants'] as $p)
                <tr>
                    <td>{{ $p['name'] }}</td>
                    <td class="text-center">
                        @if($p['transit_mode'] === 'van') 🚐
                        @elseif($p['transit_mode'] === 'fly') ✈️
                        @else 🚗
                        @endif
                    </td>
                    <td>{{ number_format($p['global_share'], 2) }}</td>
                    <td>{{ $p['transit_share'] > 0 ? number_format($p['transit_share'], 2) : '—' }}</td>
                    <td>{{ $p['local_charge'] > 0 ? number_format($p['local_charge'], 2) : '—' }}</td>
                    <td>{{ $p['bounty_credit'] > 0 ? '-'.number_format($p['bounty_credit'], 2) : '—' }}</td>
                    <td>{{ $p['prepaid'] > 0 ? '-'.number_format($p['prepaid'], 2) : '—' }}</td>
                    <td>{{ $p['total_paid'] > 0 ? '-'.number_format($p['total_paid'], 2) : '—' }}</td>
                    <td class="{{ $p['balance'] > 0 ? 'positive' : ($p['balance'] < 0 ? 'negative' : 'zero') }}">
                        <strong>{{ $p['balance'] > 0 ? '+' : '' }}{{ number_format($p['balance'], 2) }} €</strong>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="summary-row">
                <td colspan="2"><strong>{{ __('Totals') }}</strong></td>
                <td>{{ number_format($settlement['global_pool'], 2) }}</td>
                <td>{{ number_format($settlement['transit_pool'], 2) }}</td>
                <td>{{ number_format($settlement['local_subsidy'], 2) }}</td>
                <td>{{ number_format($settlement['driver_bounties'], 2) }}</td>
                <td>{{ number_format(collect($settlement['participants'])->sum('prepaid'), 2) }}</td>
                <td>{{ number_format(collect($settlement['participants'])->sum('total_paid'), 2) }}</td>
                <td class="zero">{{ number_format(collect($settlement['participants'])->sum('balance'), 2) }} €</td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top: 8px; font-size: 9pt; color: #666;">
        {{ __('Positive balance = owes club. Negative balance = club owes member.') }}
    </p>

    <div class="footer">
        {{ __('Generated') }} {{ now()->format('d/m/Y H:i') }} — {{ config('app.name') }}
        @if($event->settlement_status === 'closed')
            — <strong>{{ __('LEDGER CLOSED') }}</strong>
        @else
            — {{ __('DRAFT — ledger still open') }}
        @endif
    </div>
<script>
(function() {
    const table = document.getElementById('breakdown-table');
    const tbody = table.querySelector('tbody');
    let sortCol = -1, sortAsc = true;
    function val(row, col) {
        const text = row.cells[col].textContent.replace(/[€,—+\s]/g, '').trim();
        const n = parseFloat(text);
        return isNaN(n) ? text.toLowerCase() : n;
    }
    table.querySelectorAll('.sortable-col').forEach(th => {
        th.addEventListener('click', function() {
            const col = parseInt(this.dataset.col);
            if (sortCol === col) sortAsc = !sortAsc; else { sortCol = col; sortAsc = true; }
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const va = val(a, col), vb = val(b, col);
                if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * (sortAsc ? 1 : -1);
                return String(va).localeCompare(String(vb)) * (sortAsc ? 1 : -1);
            });
            rows.forEach(r => tbody.appendChild(r));
            table.querySelectorAll('.sort-icon').forEach(s => s.textContent = '↕');
            this.querySelector('.sort-icon').textContent = sortAsc ? '↑' : '↓';
        });
    });
    // Hide sort icons when printing
    const style = document.createElement('style');
    style.textContent = '@media print { .sort-icon { display: none !important; } }';
    document.head.appendChild(style);
})();
</script>
</body>
</html>
