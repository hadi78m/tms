@extends('layouts.app')

@section('title', isset($scopedTask) ? 'گزارش فعالیت وظیفه #' . $scopedTask->id : 'گزارش فعالیت (Audit)')
@section('header_title', isset($scopedTask) ? 'گزارش فعالیت وظیفه #' . $scopedTask->id : 'گزارش فعالیت (Audit)')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800">گزارش فعالیت سیستم</h2>
            <p class="text-sm text-slate-500 mt-1">
                این گزارش فقط-خواندنی است (immutable). طبق تصمیم DEC-045، مقادیر حساس (IP و User-Agent) ماسک شده‌اند.
            </p>
        </div>

        @if(!isset($scopedTask))
        <!-- Filters -->
        <div class="px-6 py-4 border-b border-slate-100">
            <form method="GET" action="{{ route('audit.index') }}" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">نوع رویداد</label>
                    <select name="action" class="text-sm px-3 py-2 border border-slate-300 rounded bg-white">
                        <option value="">همه</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">موجودیت</label>
                    <select name="entity_type" class="text-sm px-3 py-2 border border-slate-300 rounded bg-white">
                        <option value="">همه</option>
                        @foreach($entityTypes as $type)
                            <option value="{{ $type }}" {{ $filters['entity_type'] === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="text-sm px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">اعمال فیلتر</button>
                <a href="{{ route('audit.index') }}" class="text-sm px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">حذف فیلترها</a>
            </form>
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">#</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">رویداد</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">موجودیت</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">کاربر</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">تغییرات</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">دلیل</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-500">زمان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-slate-400">{{ $log->id }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-800">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $log->entity_type }}#{{ $log->entity_id }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $log->user?->name ?? '—' }}
                                @if(isset($log->new_values['actor_role']))
                                    <span class="text-xs text-slate-400">({{ $log->new_values['actor_role'] }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-xs truncate" title="@json($log->old_values) → @json($log->new_values)">
                                @if($log->old_values || $log->new_values)
                                    <span dir="ltr" class="font-mono">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values, JSON_UNESCAPED_UNICODE), 80) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $log->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500" dir="ltr">{{ jdate($log->created_at)->format('Y/m/d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-400">هیچ رکورد فعالیتی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
