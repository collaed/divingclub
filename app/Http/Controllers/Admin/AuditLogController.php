<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ThemeSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    use PaginatesFromRequest;

    public function index(Request $request): RedirectResponse|StreamedResponse|View
    {
        $query = AuditLog::with('user.detail');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('model_type')) {
            $query->where('model_type', 'ILIKE', '%'.$request->model_type.'%');
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }

        $sortable = ['created_at', 'action', 'model_type'];
        $sort = in_array($request->input('sort'), $sortable) ? $request->input('sort') : 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $logs = $query->orderBy($sort, $dir)->paginate($this->perPage(50))->withQueryString();
        $oldestLog = AuditLog::min('created_at');
        $retentionMonths = (int) ThemeSetting::get('audit_retention_months', 24);

        return view('admin.audit-logs.index', compact('logs', 'oldestLog', 'retentionMonths'));
    }

    public function show(AuditLog $auditLog): RedirectResponse|StreamedResponse|View
    {
        $auditLog->load('user');

        return view('admin.audit-logs.show', ['log' => $auditLog]);
    }

    public function purge(Request $request): RedirectResponse|StreamedResponse
    {
        $years = (int) $request->validate(['years' => 'required|integer|min:1|max:5'])['years'];
        $cutoff = now()->subYears($years);
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        return back()->with('success', __(':count audit log entries older than :years year(s) deleted.', ['count' => $deleted, 'years' => $years]));
    }

    public function updateRetention(Request $request): RedirectResponse|StreamedResponse
    {
        $months = $request->validate(['audit_retention_months' => 'required|integer|min:1|max:120'])['audit_retention_months'];
        ThemeSetting::set('audit_retention_months', $months);

        return back()->with('success', __('Retention policy updated to :months months.', ['months' => $months]));
    }

    public function export(Request $request): Response
    {
        $query = AuditLog::with('user.detail')->orderByDesc('created_at');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $filename = 'audit_log_'.now()->format('Y-m-d_His').'.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];

        return response()->stream(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'User', 'Action', 'Model', 'Model ID', 'IP', 'Old Values', 'New Values']);
            $query->chunk(500, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at->toIso8601String(),
                        $log->user?->name ?? $log->user_id,
                        $log->action,
                        class_basename($log->model_type),
                        $log->model_id,
                        $log->ip_address,
                        $log->old_values ? json_encode($log->old_values) : '',
                        $log->new_values ? json_encode($log->new_values) : '',
                    ]);
                }
            });
            fclose($out);
        }, 200, $headers);
    }
}
