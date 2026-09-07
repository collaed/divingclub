@props(['name' => 'nationality', 'value' => '', 'id' => null, 'required' => false])
@php
    $inputId = $id ?? $name;
    $listId = $inputId.'-country-list';
    $common = config('countries.common', []);
    $all = config('countries.all', []);
    // Common nationalities first (for quick clicking), then the full list,
    // skipping any already shown in the common block.
    $ordered = array_merge($common, array_values(array_diff($all, $common)));
@endphp
<input type="text"
       list="{{ $listId }}"
       name="{{ $name }}"
       id="{{ $inputId }}"
       value="{{ $value }}"
       autocomplete="off"
       placeholder="{{ __('Type to search…') }}"
       class="form-control @error($name) is-invalid @enderror"
       @if($required) required @endif
       {{ $attributes }}>
<datalist id="{{ $listId }}">
    @foreach($ordered as $country)
        <option value="{{ $country }}"></option>
    @endforeach
</datalist>
@error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
