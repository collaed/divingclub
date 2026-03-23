<x-layout :title="__('Dive Group Rules')">
    <h4>@icon('📋') {{ __('Dive Group Rules') }}</h4>
    <p class="text-muted small">{{ __('Rules that govern dive group (palanquée) composition. The planner validates groups against these rules.') }}</p>

    {{-- Add new rule --}}
    <div class="card dc-card mb-4">
        <div class="card-header">{{ __('Add Rule') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.dive-group-rules.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Rule name') }}" required></div>
                    <div class="col-md-2">
                        <select name="scope" class="form-select form-select-sm" required>
                            <option value="global">{{ __('Global') }}</option>
                            @foreach($federations as $f)<option value="{{ $f }}">{{ $f }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><input type="text" name="diver_condition" class="form-control form-control-sm" placeholder="no_cert / max_rank:20 / any" required></div>
                    <div class="col-md-2">
                        <select name="dive_mode" class="form-select form-select-sm" required>
                            @foreach(['supervised','autonomous','training','certification'] as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-1"><input type="number" name="min_leader_rank" class="form-control form-control-sm" placeholder="{{ __('Leader rank') }}" required></div>
                    <div class="col-md-1">
                        <select name="leader_category" class="form-select form-select-sm" required>
                            <option value="diver">{{ __('Diver') }}</option>
                            <option value="instructor">{{ __('Instructor') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="number" name="max_depth" class="form-control form-control-sm" placeholder="{{ __('Max depth') }}"></div>
                    <div class="col-md-1"><input type="number" name="max_group_size" class="form-control form-control-sm" value="4" min="1" max="10" required></div>
                    <div class="col-md-7"><input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Description') }}"></div>
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('Add') }}</button></div>
                </div>
            </form>
        </div>
    </div>

    {{-- Existing rules --}}
    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:0.85rem">
                <thead>
                    <tr><th>{{ __('Name') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Diver Condition') }}</th><th>{{ __('Mode') }}</th><th>{{ __('Leader Rank') }}</th><th>{{ __('Leader Cat.') }}</th><th>{{ __('Depth') }}</th><th>{{ __('Size') }}</th><th>{{ __('Active') }}</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($rules as $rule)
                    <tr class="{{ $rule->is_active ? '' : 'text-muted' }}">
                        <form method="POST" action="{{ route('admin.dive-group-rules.update', $rule) }}">
                            @csrf @method('PUT')
                            <td><input type="text" name="name" value="{{ $rule->name }}" class="form-control form-control-sm"></td>
                            <td>
                                <select name="scope" class="form-select form-select-sm">
                                    <option value="global" @selected($rule->scope === 'global')>Global</option>
                                    @foreach($federations as $f)<option value="{{ $f }}" @selected($rule->scope === $f)>{{ $f }}</option>@endforeach
                                </select>
                            </td>
                            <td><input type="text" name="diver_condition" value="{{ $rule->diver_condition }}" class="form-control form-control-sm" style="width:120px"></td>
                            <td>
                                <select name="dive_mode" class="form-select form-select-sm">
                                    @foreach(['supervised','autonomous','training','certification'] as $m)<option value="{{ $m }}" @selected($rule->dive_mode === $m)>{{ ucfirst($m) }}</option>@endforeach
                                </select>
                            </td>
                            <td><input type="number" name="min_leader_rank" value="{{ $rule->min_leader_rank }}" class="form-control form-control-sm" style="width:70px"></td>
                            <td>
                                <select name="leader_category" class="form-select form-select-sm">
                                    <option value="diver" @selected($rule->leader_category === 'diver')>Diver</option>
                                    <option value="instructor" @selected($rule->leader_category === 'instructor')>Instructor</option>
                                </select>
                            </td>
                            <td><input type="number" name="max_depth" value="{{ $rule->max_depth }}" class="form-control form-control-sm" style="width:70px"></td>
                            <td><input type="number" name="max_group_size" value="{{ $rule->max_group_size }}" class="form-control form-control-sm" style="width:60px"></td>
                            <td>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($rule->is_active)>
                            </td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-primary">💾</button>
                        </form>
                                <form method="POST" action="{{ route('admin.dive-group-rules.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">✕</button>
                                </form>
                            </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 small text-muted">
        <strong>{{ __('Diver condition syntax') }}:</strong>
        <code>no_cert</code> = uncertified,
        <code>max_rank:20</code> = rank ≤ 20 (e.g. 1★/OWD),
        <code>min_rank:60</code> = rank ≥ 60 (e.g. 3★),
        <code>any</code> = all divers.
        <strong>{{ __('Rank reference') }}:</strong> 10=basic, 20=1★, 40=2★, 60=3★, 70=GP/4★, 100=instr1, 110=instr2, 120=instr3
    </div>
</x-layout>
