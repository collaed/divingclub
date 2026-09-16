@props(['name', 'members', 'value' => null, 'id' => null, 'required' => false, 'placeholder' => null])
@php
    $inputId = $id ?? $name;
    $listId = $inputId.'-list';
    // Disambiguate same-named members (real occurrence in a 100+ member
    // club) by appending their email — only for the names that actually
    // collide, so everyone else's label stays clean.
    $nameCounts = collect($members)->countBy(fn ($m) => $m->name);
    $labelFor = fn ($m) => $nameCounts[$m->name] > 1 ? "{$m->name} ({$m->primary_email})" : $m->name;
    $selected = $value ? collect($members)->firstWhere('id', $value) : null;
@endphp
<input type="hidden" name="{{ $name }}" id="{{ $inputId }}" value="{{ $value }}">
<input type="text"
       list="{{ $listId }}"
       value="{{ $selected ? $labelFor($selected) : '' }}"
       autocomplete="off"
       placeholder="{{ $placeholder ?? __('Type to search…') }}"
       @if($required) required @endif
       data-member-select-input="{{ $inputId }}"
       {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
<datalist id="{{ $listId }}">
    @foreach($members as $m)
        <option value="{{ $labelFor($m) }}" data-id="{{ $m->id }}"></option>
    @endforeach
</datalist>
@error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
