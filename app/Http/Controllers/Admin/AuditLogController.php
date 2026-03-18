<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', '%' . $request->model_type . '%');
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->paginate(50)->withQueryString();
        $oldestLog = AuditLog::min('created_at');

        return view('admin.audit-logs.index', compact('logs', 'oldestLog'));
    }

    public function purge(Request $request)
    {
        $years = (int) $request->validate(['years' => 'required|integer|min:1|max:5'])['years'];
        $cutoff = now()->subYears($years);
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        return back()->with('success', __(':count audit log entries older than :years year(s) deleted.', ['count' => $deleted, 'years' => $years]));
    }
}
