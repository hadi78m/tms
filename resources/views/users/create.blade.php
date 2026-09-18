@extends('layouts.app')

@section('title', 'افزودن کاربر جدید')
@section('header_title', 'تعریف و ثبت کاربر جدید')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div>
                <h1 class="text-lg font-bold text-slate-800">مشخصات کاربر جدید</h1>
                <p class="text-xs text-slate-500 mt-1">اطلاعات هویتی، سطوح دسترسی و ارتباط با پیمانکار را وارد نمایید</p>
            </div>
            <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:text-slate-800 flex items-center">
                <i class="fas fa-arrow-right ml-1"></i>
                بازگشت به فهرست
            </a>
        </div>

        <form action="{{ route('users.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                        نام و نام خانوادگی <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-rose-500 @enderror"
                           placeholder="مثال: علی محمدی">
                    @error('name')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- National Code -->
                <div>
                    <label for="national_code" class="block text-sm font-medium text-slate-700 mb-1">
                        کد ملی (۱۰ رقم) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="national_code" id="national_code" value="{{ old('national_code') }}" required maxlength="10"
                           class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('national_code') border-rose-500 @enderror"
                           placeholder="0123456789">
                    <p class="text-xs text-slate-400 mt-1">نام کاربری به صورت خودکار برابر با کد ملی خواهد بود.</p>
                    @error('national_code')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Mobile -->
                <div>
                    <label for="mobile" class="block text-sm font-medium text-slate-700 mb-1">
                        شماره همراه (۱۱ رقم) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="mobile" id="mobile" value="{{ old('mobile') }}" required maxlength="11"
                           class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mobile') border-rose-500 @enderror"
                           placeholder="09123456789">
                    @error('mobile')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                        پست الکترونیک (ایمیل)
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                           class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-rose-500 @enderror"
                           placeholder="user@example.com">
                    @error('email')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                        کلمه عبور <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" name="password" id="password" required minlength="6"
                           class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-rose-500 @enderror"
                           placeholder="حداقل ۶ کاراکتر">
                    @error('password')
                        <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Is Active -->
                <div class="flex items-center pt-6">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="mr-3 text-sm font-medium text-slate-700">حساب کاربری فعال باشد</span>
                    </label>
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Roles Section -->
            <div>
                <label class="block text-sm font-bold text-slate-800 mb-2">
                    نقش‌های دسترسی سامانه <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach ($roles as $role)
                        @php
                            $roleBadgeColor = match($role->name) {
                                'admin' => 'border-purple-200 bg-purple-50/50 hover:bg-purple-50 text-purple-900',
                                'supervisor' => 'border-blue-200 bg-blue-50/50 hover:bg-blue-50 text-blue-900',
                                'contractor' => 'border-emerald-200 bg-emerald-50/50 hover:bg-emerald-50 text-emerald-900',
                                default => 'border-slate-200 bg-slate-50/50 hover:bg-slate-50 text-slate-900',
                            };
                            $roleTitle = match($role->name) {
                                'admin' => 'مدیر ارشد (Admin)',
                                'supervisor' => 'ناظر سیستم (Supervisor)',
                                'contractor' => 'پیمانکار (Contractor)',
                                default => $role->name,
                            };
                        @endphp
                        <label class="relative flex items-center p-3.5 border rounded-xl cursor-pointer transition-colors {{ $roleBadgeColor }}">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" 
                                   class="role-checkbox h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                   {{ is_array(old('roles')) && in_array($role->name, old('roles')) ? 'checked' : '' }}>
                            <span class="mr-3 text-xs font-semibold">{{ $roleTitle }}</span>
                        </label>
                    @endforeach
                </div>
                @error('roles')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Contractor Selection (Visible only when contractor role is selected) -->
            <div id="contractor-wrapper" class="{{ is_array(old('roles')) && in_array('contractor', old('roles')) ? '' : 'hidden' }}">
                <label for="contractor_id" class="block text-sm font-medium text-slate-700 mb-1">
                    اتصال به شرکت پیمانکار
                </label>
                <select name="contractor_id" id="contractor_id"
                        class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- انتخاب شرکت پیمانکار --</option>
                    @foreach ($contractors as $contractor)
                        <option value="{{ $contractor->id }}" {{ old('contractor_id') == $contractor->id ? 'selected' : '' }}>
                            {{ $contractor->name }} (کد: {{ $contractor->code }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">در صورت انتخاب نقش پیمانکار، اتصال به شرکت پیمانکار الزامی است.</p>
                @error('contractor_id')
                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                <a href="{{ route('users.index') }}" class="px-5 py-2.5 border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                    انصراف
                </a>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors">
                    <i class="fas fa-check ml-1.5"></i>
                    ثبت و ذخیره کاربر
                </button>
            </div>
        </form>
    </div>
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
