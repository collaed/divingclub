<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InstallController extends Controller
{
    public function index(): RedirectResponse|View
    {
        // If already installed, redirect home
        try {
            if (file_exists(storage_path('installed.lock'))) {
                return redirect('/');
            }
            if (Schema::hasTable('users') && User::count() > 0) {
                return redirect('/');
            }
        } catch (\Exception $e) {
            // DB not ready yet — show wizard
        }

        $currentDriver = config('database.default');
        $envExists = file_exists(base_path('.env'));

        return view('install.index', compact('currentDriver', 'envExists'));
    }

    public function run(Request $request): RedirectResponse
    {
        $request->validate([
            'db_driver' => 'required|in:sqlite,mysql',
            'app_name' => 'required|string|max:100',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
            'locales' => 'required|array|min:1',
            'locales.*' => 'string|in:'.implode(',', array_keys(config('languages', []))),
            // MySQL fields (conditional)
            'db_host' => 'required_if:db_driver,mysql',
            'db_port' => 'required_if:db_driver,mysql|nullable|integer',
            'db_database' => 'required_if:db_driver,mysql',
            'db_username' => 'required_if:db_driver,mysql',
            'db_password' => 'nullable|string',
        ]);

        $driver = $request->input('db_driver');

        // Update .env
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $replacements = [
            'APP_NAME' => $request->input('app_name'),
            'DB_CONNECTION' => $driver,
        ];

        if ($driver === 'sqlite') {
            $sqlitePath = database_path('database.sqlite');
            if (! file_exists($sqlitePath)) {
                touch($sqlitePath);
            }
            $replacements['DB_DATABASE'] = $sqlitePath;
            $replacements['DB_HOST'] = '127.0.0.1';
            $replacements['DB_PORT'] = '3306';
            $replacements['DB_USERNAME'] = 'root';
            $replacements['DB_PASSWORD'] = '';
        } else {
            $replacements['DB_HOST'] = $request->input('db_host', '127.0.0.1');
            $replacements['DB_PORT'] = $request->input('db_port', '3306');
            $replacements['DB_DATABASE'] = $request->input('db_database');
            $replacements['DB_USERNAME'] = $request->input('db_username');
            $replacements['DB_PASSWORD'] = $request->input('db_password', '');
        }

        $this->updateEnv($envPath, $replacements);

        // Reconfigure database at runtime
        if ($driver === 'sqlite') {
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        } else {
            config(['database.default' => 'mysql']);
            config(['database.connections.mysql.host' => $replacements['DB_HOST']]);
            config(['database.connections.mysql.port' => $replacements['DB_PORT']]);
            config(['database.connections.mysql.database' => $replacements['DB_DATABASE']]);
            config(['database.connections.mysql.username' => $replacements['DB_USERNAME']]);
            config(['database.connections.mysql.password' => $replacements['DB_PASSWORD']]);
        }

        DB::purge();
        DB::reconnect();

        // Test connection
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'db_driver' => 'Database connection failed: '.$e->getMessage(),
            ]);
        }

        // Run migrations and seed (standard package: roles, federations, certifications, dive rules)
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        // Save enabled locales and club name
        ThemeSetting::set('enabled_locales', json_encode($request->input('locales')));
        ThemeSetting::set('club_full_name', $request->input('app_name'));

        // Create admin user
        $admin = User::create([
            'name' => 'Administrator',
            'email' => $request->input('admin_email'),
            'password' => Hash::make($request->input('admin_password')),
            'email_verified_at' => now(),
        ]);

        // Assign bureau_master role if roles table exists
        $masterRole = Role::where('slug', 'bureau_master')->first();
        if ($masterRole) {
            $admin->role_id = $masterRole->id;
            $admin->save();
        }

        Artisan::call('key:generate', ['--force' => true]);

        file_put_contents(storage_path('installed.lock'), now()->toIso8601String());

        return redirect('/')->with('success', 'Installation complete! Log in with your admin credentials.');
    }

    private function updateEnv(string $path, array $values): void
    {
        $content = file_get_contents($path);

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') || str_contains($value, '#')
                ? '"'.$value.'"'
                : $value;

            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        file_put_contents($path, $content);
    }
}
