@extends('layouts.app')

@section('title', 'ویرایش کاربر')
@section('header_title', 'ویرایش مشخصات کاربر')

@section('content')
{{-- Phase 5B-5 — Visual Migration (ویرایش کاربر).
     فقط زبان بصری به Design System منتقل شده است: route/متد PUT/نام فیلدها/idها
     (`contractor-wrapper`, `.role-checkbox`)، required/maxlength/minlength،
     @error/old() با fallback به مقادیر کاربر، منطق checked بودن نقش‌ها و
     نمایش انتخاب پیمانکار و بلوک JS عیناً حفظ شده‌اند. --}}
<div class="max-w-4xl mx-auto">
    <x-ui.panel>
        <x-slot:header>
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line-subtle px-5 py-3.5">
                <div>
                    <h1 class="text-lg font-bold text-ink">ویرایش کاربر: {{ $user->name }}</h1>
                    <p class="mt-1 text-xs text-ink-muted">تغییر مشخصات هویتی، سطوح دسترسی یا رمز عبور</p>
                </div>
                <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1 rounded-ui px-2 py-1 text-sm font-bold text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    بازگشت به فهرست
                </a>
            </header>
        </x-slot:header>

        <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-bold text-ink mb-1">
                        نام و نام خانوادگی <span class="text-danger-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-ui px-3.5 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 {{ $errors->has('name') ? 'border border-danger-500' : 'border border-line-strong' }}"
                           @error('name') aria-describedby="name-error" @enderror>
                    @error('name')
                        <p id="name-error" class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- National Code -->
                <div>
                    <label for="national_code" class="block text-sm font-bold text-ink mb-1">
                        کد ملی (۱۰ رقم) <span class="text-danger-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text" name="national_code" id="national_code" value="{{ old('national_code', $user->national_code) }}" required maxlength="10"
                           class="w-full rounded-ui px-3.5 py-2 text-sm font-mono text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 {{ $errors->has('national_code') ? 'border border-danger-500' : 'border border-line-strong' }}"
                           @error('national_code') aria-describedby="national_code-help national_code-error" @enderror>
                    <p id="national_code-help" class="mt-1 text-xs text-ink-subtle">نام کاربری به صورت خودکار برابر با کد ملی خواهد بود.</p>
                    @error('national_code')
                        <p id="national_code-error" class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Mobile -->
                <div>
                    <label for="mobile" class="block text-sm font-bold text-ink mb-1">
                        شماره همراه (۱۱ رقم) <span class="text-danger-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text" name="mobile" id="mobile" value="{{ old('mobile', $user->mobile) }}" required maxlength="11"
                           class="w-full rounded-ui px-3.5 py-2 text-sm font-mono text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 {{ $errors->has('mobile') ? 'border border-danger-500' : 'border border-line-strong' }}"
                           @error('mobile') aria-describedby="mobile-error" @enderror>
                    @error('mobile')
                        <p id="mobile-error" class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-bold text-ink mb-1">
                        پست الکترونیک (ایمیل)
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                           class="w-full rounded-ui px-3.5 py-2 text-sm font-mono text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 {{ $errors->has('email') ? 'border border-danger-500' : 'border border-line-strong' }}"
                           @error('email') aria-describedby="email-error" @enderror>
                    @error('email')
                        <p id="email-error" class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-bold text-ink mb-1">
                        کلمه عبور جدید (اختیاری)
                    </label>
                    <input type="password" name="password" id="password" minlength="6"
                           class="w-full rounded-ui px-3.5 py-2 text-sm font-mono text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 {{ $errors->has('password') ? 'border border-danger-500' : 'border border-line-strong' }}"
                           placeholder="در صورت عدم تمایل به تغییر، خالی بگذارید"
                           @error('password') aria-describedby="password-error" @enderror>
                    @error('password')
                        <p id="password-error" class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Is Active -->
                <div class="flex items-center pt-6">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-line-strong peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-line-strong after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        <span class="ms-3 text-sm font-bold text-ink">حساب کاربری فعال باشد</span>
                    </label>
                </div>
            </div>

            <hr class="border-line-subtle">

            <!-- Roles Section -->
            <div>
                <label class="block text-sm font-bold text-ink mb-2">
                    نقش‌های دسترسی سامانه <span class="text-danger-500" aria-hidden="true">*</span>
                </label>
                @php
                    $userRoleNames = $user->roles->pluck('name')->toArray();
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach ($roles as $role)
                        @php
                            $roleBadgeColor = match($role->name) {
                                'admin' => 'border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary',
                                'supervisor' => 'border-info-200 bg-info-50 hover:bg-info-100 text-info-700',
                                'contractor' => 'border-success-200 bg-success-50 hover:bg-success-100 text-success-700',
                                default => 'border-line bg-surface-inset/60 hover:bg-surface-inset text-ink',
                            };
                            $roleTitle = match($role->name) {
                                'admin' => 'مدیر ارشد (Admin)',
                                'supervisor' => 'ناظر سیستم (Supervisor)',
                                'contractor' => 'پیمانکار (Contractor)',
                                default => $role->name,
                            };
                            $isChecked = is_array(old('roles')) ? in_array($role->name, old('roles')) : in_array($role->name, $userRoleNames);
                        @endphp
                        <label class="relative flex items-center p-3.5 border rounded-ui cursor-pointer transition-colors {{ $roleBadgeColor }}">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                   class="role-checkbox h-4 w-4 rounded border-line-strong text-primary focus:ring-2 focus:ring-primary/40"
                                   {{ $isChecked ? 'checked' : '' }}>
                            <span class="ms-3 text-xs font-semibold">{{ $roleTitle }}</span>
                        </label>
                    @endforeach
                </div>
                @error('roles')
                    <p class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contractor Selection (Visible only when contractor role is selected) -->
            @php
                $hasContractorRole = is_array(old('roles')) ? in_array('contractor', old('roles')) : in_array('contractor', $userRoleNames);
            @endphp
            <div id="contractor-wrapper" class="{{ $hasContractorRole ? '' : 'hidden' }}">
                <label for="contractor_id" class="block text-sm font-bold text-ink mb-1">
                    اتصال به شرکت پیمانکار
                </label>
                <select name="contractor_id" id="contractor_id"
                        class="w-full rounded-ui border border-line-strong bg-surface-base px-3.5 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30">
                    <option value="">-- انتخاب شرکت پیمانکار --</option>
                    @foreach ($contractors as $contractor)
                        <option value="{{ $contractor->id }}" {{ old('contractor_id', $user->contractor_id) == $contractor->id ? 'selected' : '' }}>
                            {{ $contractor->name }} (کد: {{ $contractor->code }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-ink-subtle">در صورت انتخاب نقش پیمانکار، اتصال به شرکت پیمانکار الزامی است.</p>
                @error('contractor_id')
                    <p class="mt-1 text-xs text-danger-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <div class="flex flex-wrap justify-end gap-3 border-t border-line-subtle pt-4">
                <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-ui border border-line-strong bg-surface-base px-5 py-2.5 text-sm font-bold text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    انصراف
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-ui bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-ui-soft transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <i class="fas fa-save" aria-hidden="true"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </x-ui.panel>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleCheckboxes = document.querySelectorAll('.role-checkbox');
        const contractorWrapper = document.getElementById('contractor-wrapper');

        function toggleContractorSelect() {
            let isContractorChecked = false;
            roleCheckboxes.forEach(cb => {
                if (cb.checked && cb.value === 'contractor') {
                    isContractorChecked = true;
                }
            });

            if (isContractorChecked) {
                contractorWrapper.classList.remove('hidden');
            } else {
                contractorWrapper.classList.add('hidden');
            }
        }

        roleCheckboxes.forEach(cb => {
            cb.addEventListener('change', toggleContractorSelect);
        });

        toggleContractorSelect();
    });
</script>
@endpush
@endsection
