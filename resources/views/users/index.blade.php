@extends('layouts.app')

@section('title', 'مدیریت کاربران')
@section('header_title', 'مدیریت کاربران و دسترسی‌ها')

@section('content')
<div class="space-y-6">
    <!-- Action Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-xl shadow-sm border border-slate-100">
        <div>
            <h1 class="text-xl font-bold text-slate-800">فهرست کاربران سامانه</h1>
            <p class="text-sm text-slate-500 mt-1">مدیریت حساب‌های کاربری، تعیین نقش‌های دسترسی و اتصال به پیمانکاران</p>
        </div>
        <div>
            <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-150">
                <i class="fas fa-user-plus ml-2"></i>
                افزودن کاربر جدید
            </a>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-4 px-6">شناسه / نام کاربر</th>
                        <th class="py-4 px-6">کد ملی (نام کاربری)</th>
                        <th class="py-4 px-6">نقش‌های دسترسی</th>
                        <th class="py-4 px-6">پیمانکار متصل</th>
                        <th class="py-4 px-6">شماره همراه</th>
                        <th class="py-4 px-6">وضعیت</th>
                        <th class="py-4 px-6 text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex items-center">
                                    <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-sm ml-3 border border-slate-200">
                                        {{ mb_substr($user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                                        <div class="text-xs text-slate-400">{{ $user->email ?? 'بدون ایمیل' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6 font-mono text-slate-600">
                                {{ $user->national_code }}
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        @php
                                            $roleClasses = match($role->name) {
                                                'admin' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                'supervisor' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'contractor' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                default => 'bg-slate-50 text-slate-700 border-slate-200',
                                            };
                                            $roleLabel = match($role->name) {
                                                'admin' => 'مدیر ارشد',
                                                'supervisor' => 'ناظر سیستم',
                                                'contractor' => 'پیمانکار',
                                                default => $role->name,
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $roleClasses }}">
                                            {{ $roleLabel }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">بدون نقش</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                @if ($user->contractor)
                                    <span class="text-xs font-medium text-slate-800 bg-slate-100 px-2 py-1 rounded">
                                        <i class="fas fa-building ml-1 text-slate-400"></i>
                                        {{ $user->contractor->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 font-mono text-slate-600 text-xs">
                                {{ $user->mobile }}
                            </td>
                            <td class="py-4 px-6">
                                @if ($user->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 ml-1.5"></span>
                                        فعال
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 ml-1.5"></span>
                                        غیرفعال
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('users.edit', $user) }}" class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors" title="ویرایش">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @if ($user->id !== auth()->id())
                                        <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('آیا از حذف این کاربر اطمینان دارید؟');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors" title="حذف کاربر">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i class="fas fa-users text-4xl mb-3 text-slate-300"></i>
                                <p>هیچ کاربری یافت نشد.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection