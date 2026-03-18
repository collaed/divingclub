@props(['name', 'value' => '', 'class' => 'form-control', 'required' => false, 'id' => null])
@php
    // Convert Y-m-d (from DB) to dd/mm/yyyy for display
    $display = '';
    if ($value) {
        try {
            $display = \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception $e) {
            $display = $value;
        }
    }
@endphp
<input type="text"
       name="{{ $name }}"
       value="{{ $display }}"
       class="{{ $class }}"
       placeholder="dd/mm/yyyy"
       pattern="\d{2}/\d{2}/\d{4}"
       maxlength="10"
       @if($id) id="{{ $id }}" @endif
       @if($required) required @endif
       {{ $attributes }}>
