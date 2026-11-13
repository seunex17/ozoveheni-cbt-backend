<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $types = array_column(AuditObserver::MODELS, 'type');
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted', 'attempts_cleared'])],
            'type' => ['nullable', Rule::in($types)],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $filters = ['search' => trim($input['search'] ?? ''), 'action' => $input['action'] ?? '', 'type' => $input['type'] ?? '', 'from' => $input['from'] ?? '', 'to' => $input['to'] ?? ''];
        if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        $logs = AuditLog::query();
        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filters['search'])).'%';
            $logs->where(fn (Builder $query) => $query->whereRaw("LOWER(actor_name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(subject_label) LIKE ? ESCAPE '!'", [$term]));
        }
        if ($filters['action'] !== '') {
            $logs->where('action', $filters['action']);
        }
        if ($filters['type'] !== '') {
            $logs->where('subject_type', $filters['type']);
        }
        if ($filters['from'] !== '') {
            $logs->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $logs->where('created_at', '<', \Illuminate\Support\Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return Inertia::render('Dashboard/Audit/Index', [
            'logs' => $logs->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters, 'types' => $types,
        ]);
    }
}
