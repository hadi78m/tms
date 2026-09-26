<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'سیستم مدیریت تسک') - TMS</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=vazirmatn:400,500,600,700&display=swap" rel="stylesheet" />
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Persian Datepicker -->
    <link rel="stylesheet" href="{{ asset('css/persianDatepicker/persianDatepicker-default.css') }}">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-800 text-white flex flex-col shadow-lg transition-all duration-300 z-20 hidden md:flex">
            <div class="flex items-center justify-center h-16 border-b border-slate-700">
                <span class="text-xl font-bold tracking-wider uppercase">TMS</span>
            </div>
            
            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 bg-slate-700 text-white rounded-lg group transition-colors">
                <!-- Navigation Links -->
                <nav class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('dashboard') ? 'bg-slate-100 text-blue-700' : '' }}">
                        <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        پیشخوان
                    </a>
                    
                    <a href="{{ route('tasks.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('tasks.*') ? 'bg-slate-100 text-blue-700' : '' }}">
                        <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        مدیریت وظایف
                    </a>

                    @hasanyrole('admin|management|employer|project_manager')
                    <a href="{{ route('reports.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('reports.*') ? 'bg-slate-100 text-blue-700' : '' }}">
                        <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        گزارش‌ها
                    </a>
                    @endhasanyrole

                    @hasanyrole('admin|project_manager|employer|management|supervisor|viewer')
                    <a href="{{ route('modules.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('modules.*') || request()->routeIs('stages.*') ? 'bg-slate-100 text-blue-700' : '' }}">
                        <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        ساختار ماژول‌ها
                    </a>

                    <a href="{{ route('wbs-phases.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('wbs-phases.*') ? 'bg-slate-100 text-blue-700' : '' }}">
                        <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        فازهای WBS
                    </a>
                    @endhasanyrole

                    @role('admin')
                    <div class="pt-4 mt-4 border-t border-slate-200">
                        <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">مدیریت سیستم</p>
                        <a href="{{ route('users.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('users.*') ? 'bg-slate-100 text-blue-700' : '' }}">
                            <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            کاربران سیستم
                        </a>
                        <a href="{{ route('settings.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 hover:bg-slate-50 {{ request()->routeIs('settings.*') ? 'bg-slate-100 text-blue-700' : '' }}">
                            <svg class="w-5 h-5 ml-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            تنظیمات سامانه
                        </a>
                    </div>
                    @endrole
                </nav>
            </nav>
            
            <div class="p-4 border-t border-slate-700">
                <div class="flex items-center px-4 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-600 flex items-center justify-center text-sm font-bold">
                        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                    </div>
                    <div class="mr-3 text-sm">
                        <p class="font-medium truncate">{{ auth()->user()->name ?? 'کاربر' }}</p>
                        <p class="text-slate-400 text-xs truncate">{{ auth()->user()->username ?? '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="w-full flex items-center px-4 py-2 text-sm text-red-400 hover:bg-slate-700 hover:text-red-300 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt w-6 text-center"></i>
                        <span class="mr-2">خروج</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            
            <!-- Top Header -->
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10">
                <div class="flex items-center">
                    <button class="md:hidden text-slate-500 hover:text-slate-700 focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-lg font-semibold text-slate-800 mr-4 md:mr-0">@yield('header_title', 'داشبورد')</h2>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 p-6">
                <!-- Flash Messages -->
                @if (session('status'))
                    <div class="mb-6 bg-green-50 border-r-4 border-green-500 p-4 rounded-lg shadow-sm flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500 text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <p class="text-sm font-medium text-green-800">{{ session('status') }}</p>
                        </div>
                    </div>
                @endif
                
                @if (session('error'))
                    <div class="mb-6 bg-red-50 border-r-4 border-red-500 p-4 rounded-lg shadow-sm flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border-r-4 border-red-500 p-4 rounded-lg shadow-sm flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                        </div>
                        <div class="mr-3">
                            <ul class="list-disc list-inside text-sm font-medium text-red-800">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <!-- Page Content -->
                @yield('content')
            </main>
            
        </div>
    </div>
    
    <!-- jQuery & Persian Datepicker -->
    <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('js/persianDatepicker/persianDatepicker.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            if ($.fn.persianDatepicker) {
                $('.datedown').persianDatepicker({
                    formatDate: "YYYY/MM/DD",
                    showGregorianDate: false,
                    autoClose: true
                });
                $('.datetop').persianDatepicker({
                    calendarPosition: { x: 0, y: -350 },
                    formatDate: "YYYY/MM/DD",
                    showGregorianDate: false,
                    autoClose: true
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
