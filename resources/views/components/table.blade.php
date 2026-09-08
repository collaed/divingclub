@props(['stack' => false])
{{--
    Data-table wrapper: always horizontally scrollable, never widens the page.
    <x-table>…thead/tbody…</x-table>
    Pass `stack` to collapse rows into cards below 768px — each <td> then needs
    data-label="Column name".
--}}
<div class="table-responsive">
    <table {{ $attributes->class(['table', 'table-stack' => $stack]) }}>
        {{ $slot }}
    </table>
</div>
