<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * V1.10 — DEC-045 (DR-4=C + Masking=yes): read-only Audit Visibility UI.
 *
 * Visibility: supervisor + employer only (enforced by route middleware AND
 * re-checked here as a guard — DEC-044 Hybrid).
 * Immutability: activity_logs are immutable at the model and DB-trigger level;
 * this controller exposes NO write path of any kind.
 * Masking (Owner decision): ip_address and user_agent are always masked;
 * entity/entity_id remain as navigational hints only.
 */
class AuditController extends Controller
{
    /** Fields never shown unmasked in the UI (DEC-045 Masking = بله). */
    private const MASKED = '••••••••';

    public function index(Request $request): View
    {
        $this->authorizeAuditViewer();

        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->input('entity_type')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $actions = ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $entityTypes = ActivityLog::query()->select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return view('audit.index', [
            'logs' => $logs,
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'filters' => [
                'action' => $request->input('action'),
                'entity_type' => $request->input('entity_type'),
            ],
        ]);
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorizeAuditViewer();

        $logs = ActivityLog::query()
            ->with('user')
            ->where('entity_type', $task->getMorphClass())
            ->where('entity_id', $task->getKey())
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'actions' => collect(),
            'entityTypes' => collect(),
            'filters' => ['action' => null, 'entity_type' => null],
            'scopedTask' => $task,
        ]);
    }

    private function authorizeAuditViewer(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole(['supervisor', 'employer'])) {
            abort(403, 'مشاهدهٔ گزارش فعالیت فقط برای ناظر و بهره‌بردار مجاز است.');
        }
    }

    /** Masking helper used by the view (DEC-045). */
    public static function masked(?string $value): string
    {
        return $value === null || $value === '' ? '—' : self::MASKED;
    }
}
