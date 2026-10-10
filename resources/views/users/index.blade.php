@extends('layouts.app')

@section('title', 'مدیریت کاربران')
@section('header_title', 'مدیریت کاربران و دسترسی‌ها')

@section('content')
{{-- Phase 5B-3 — Visual Migration (فهرست کاربران).
     فقط زبان بصری منتقل شده است: Panel/Badge/Avatar/EmptyState/توکن‌ها.
     routeها/فرم حذف/`@method('DELETE')`/onsubmit confirm/برچسب نقش‌ها/
     ستون‌ها/pagination همگی دست‌نخورده‌اند. --}}
<div class="space-y-6">
    <!-- Action Bar -->
    <div class="flex flex-col justify-between gap-4 rounded-ui-md border border-line bg-surface-base p-6 shadow-ui-soft sm:flex-row sm:items-center">
        <div>
            <h1 class="text-xl font-bold text-ink">فهرست کاربران سامانه</h1>
            <p class="mt-1 text-sm text-ink-muted">مدیریت حساب‌های کاربری، تعیین نقش‌های دسترسی و اتصال به پیمانکاران</p>
        </div>
        <div>
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center rounded-ui bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                <i class="fas fa-user-plus ms-2" aria-hidden="true"></i>
                افزودن کاربر جدید
            </a>
        </div>
    </div>

    <!-- Users Table Card -->
    <x-ui.panel body-class="p-0">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-right text-sm">
                <thead>
                    <tr class="border-b border-line bg-surface-inset text-xs font-bold uppercase tracking-wider text-ink-muted">
                        <th class="px-6 py-4">شناسه / نام کاربر</th>
                        <th class="px-6 py-4">کد ملی (نام کاربری)</th>
                        <th class="px-6 py-4">نقش‌های دسترسی</th>
                        <th class="px-6 py-4">پیمانکار متصل</th>
                        <th class="px-6 py-4">شماره همراه</th>
                        <th class="px-6 py-4">وضعیت</th>
                        <th class="px-6 py-4 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-subtle text-ink">
                    @forelse ($users as $user)
                        <tr class="transition-colors hover:bg-surface-inset/60">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$user->name" size="sm" />
                                    <div>
                                        <div class="font-bold text-ink">{{ $user->name }}</div>
                                        <div class="text-xs text-ink-subtle">{{ $user->email ?? 'بدون ایمیل' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-ink-muted">
                                {{ $user->national_code }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        @php
                                            // برچسب/رنگ نقش‌ها همان نگاشت قبلی است — فقط به
                                            // variantهای design-system ترجمه شده است.
                                            $roleVariant = match($role->name) {
                                                'admin' => 'primary',
                                                'supervisor' => 'info',
                                                'contractor' => 'success',
                                                default => 'default',
                                            };
                                            $roleLabel = match($role->name) {
                                                'admin' => 'مدیر ارشد',
                                                'supervisor' => 'ناظر سیستم',
                                                'contractor' => 'پیمانکار',
                                                default => $role->name,
                                            };
                                        @endphp
                                        <x-ui.badge :variant="$roleVariant">{{ $roleLabel }}</x-ui.badge>
                                    @empty
                                        <span class="text-xs text-ink-subtle">بدون نقش</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($user->contractor)
                                    <span class="inline-flex items-center rounded-ui bg-surface-inset px-2 py-1 text-xs font-bold text-ink">
                                        <i class="fas fa-building me-1 text-ink-subtle" aria-hidden="true"></i>
                                        {{ $user->contractor->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-ink-subtle">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-ink-muted">
                                {{ $user->mobile }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($user->is_active)
                                    <x-ui.badge variant="success" dot>فعال</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" dot>غیرفعال</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('users.edit', $user) }}"
                                       class="grid h-8 w-8 place-items-center rounded-ui text-primary transition hover:bg-primary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                       title="ویرایش" aria-label="ویرایش کاربر {{ $user->name }}">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    @if ($user->id !== auth()->id())
                                        <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('آیا از حذف این کاربر اطمینان دارید؟');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="grid h-8 w-8 place-items-center rounded-ui text-danger-500 transition hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500"
                                                    title="حذف کاربر" aria-label="حذف کاربر {{ $user->name }}">
                                                <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4">
                                <x-ui.empty-state title="هیچ کاربری یافت نشد." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-line-subtle px-6 py-4">
                {{ $users->links() }}
            </div>
        @endif
    </x-ui.panel>
</div>
@endsection
