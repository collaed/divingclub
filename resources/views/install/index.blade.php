<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="generator" content="DivingClub-Manager/1.0">
    <title>Install — DivingClub Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0d6efd 0%, #0a4a8a 100%); min-height: 100vh; }
        .install-card { max-width: 600px; margin: 3rem auto; }
        .mysql-fields { display: none; }
    </style>
</head>
<body class="d-flex align-items-center">
<div class="container">
    <div class="install-card">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white text-center py-3">
                <h3 class="mb-0">@icon('🤿') DivingClub Manager — Setup</h3>
                <small>First-time installation wizard</small>
            </div>
            <div class="card-body p-4">
                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('install.run') }}">
                    @csrf

                    <h5 class="mb-3">1. Club Name</h5>
                    <div class="mb-4">
                        <input type="text" name="app_name" class="form-control" placeholder="e.g. Club Européen de Plongée" value="{{ old('app_name', 'DivingClub') }}" required>
                    </div>

                    <h5 class="mb-3">2. Database</h5>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="db_driver" id="db_sqlite" value="sqlite" {{ old('db_driver', 'sqlite') === 'sqlite' ? 'checked' : '' }} onchange="toggleMysql()">
                            <label class="form-check-label" for="db_sqlite">
                                <strong>SQLite</strong> <span class="text-muted">— zero config, single file, ideal for small clubs & Wasmer</span>
                            </label>
                        </div>
                        <div class="form-check form-check-inline mt-2">
                            <input class="form-check-input" type="radio" name="db_driver" id="db_mysql" value="mysql" {{ old('db_driver') === 'mysql' ? 'checked' : '' }} onchange="toggleMysql()">
                            <label class="form-check-label" for="db_mysql">
                                <strong>MySQL / MariaDB</strong> <span class="text-muted">— for larger deployments</span>
                            </label>
                        </div>
                    </div>

                    <div class="mysql-fields" id="mysqlFields">
                        <div class="row g-2 mb-2">
                            <div class="col-8">
                                <input type="text" name="db_host" class="form-control" placeholder="Host (127.0.0.1)" value="{{ old('db_host', '127.0.0.1') }}">
                            </div>
                            <div class="col-4">
                                <input type="number" name="db_port" class="form-control" placeholder="Port (3306)" value="{{ old('db_port', '3306') }}">
                            </div>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="db_database" class="form-control" placeholder="Database name" value="{{ old('db_database', 'divingclub') }}">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <input type="text" name="db_username" class="form-control" placeholder="Username" value="{{ old('db_username', 'root') }}">
                            </div>
                            <div class="col-6">
                                <input type="password" name="db_password" class="form-control" placeholder="Password">
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3">3. Languages</h5>
                    <p class="text-muted small mb-2">Select which languages to enable. You can change this later in Settings.</p>
                    <div class="row mb-4">
                        @foreach(config('languages', []) as $code => $lang)
                            <div class="col-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="locales[]" value="{{ $code }}" id="lang_{{ $code }}"
                                        {{ in_array($code, old('locales', ['en', 'fr'])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="lang_{{ $code }}">
                                        {{ $lang['flag'] }} {{ $lang['native'] }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <h5 class="mb-3">4. Admin Account</h5>
                    <div class="mb-2">
                        <input type="email" name="admin_email" class="form-control" placeholder="Admin email" value="{{ old('admin_email') }}" required>
                    </div>
                    <div class="mb-4">
                        <input type="password" name="admin_password" class="form-control" placeholder="Admin password (min 8 chars)" required minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        @icon('🚀') Install DivingClub Manager
                    </button>
                </form>
            </div>
            <div class="card-footer text-center text-muted small">
                DivingClub-Manager v1.0 — Multi-club diving management system
            </div>
        </div>
    </div>
</div>
<script>
function toggleMysql() {
    document.getElementById('mysqlFields').style.display =
        document.getElementById('db_mysql').checked ? 'block' : 'none';
}
toggleMysql();
</script>
</body>
</html>
