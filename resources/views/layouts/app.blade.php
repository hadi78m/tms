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
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
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
                    <i class="fas fa-home w-6 text-center text-slate-300 group-hover:text-white"></i>
                    <span class="mr-3 font-medium">داشبورد</span>
                </a>

                <div class="pt-4 pb-2">
                    <p class="px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">وظایف</p>
                </div>
                
                <a href="{{ route('tasks.index') }}" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-lg group transition-colors">
                    <i class="fas fa-tasks w-6 text-center text-slate-400 group-hover:text-white"></i>
                    <span class="mr-3 font-medium">فهرست وظایف</span>
                </a>
                
                @if(auth()->user()->hasRole(['manager', 'supervisor', 'admin']))
                <a href="{{ route('tasks.create') }}" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-lg group transition-colors">
                    <i class="fas fa-plus w-6 text-center text-slate-400 group-hover:text-white"></i>
                    <span class="mr-3 font-medium">وظیفه جدید</span>
                </a>
                @endif

                @if(auth()->user()->hasRole('admin'))
                <div class="pt-4 pb-2">
                    <p class="px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">مدیریت دسترسی</p>
                </div>
                <a href="{{ route('users.index') }}" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-lg group transition-colors">
                    <i class="fas fa-users-cog w-6 text-center text-slate-400 group-hover:text-white"></i>
                    <span class="mr-3 font-medium">مدیریت کاربران</span>
                </a>
                @endif
                
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
    
    @stack('scripts')
</body>
</html>
