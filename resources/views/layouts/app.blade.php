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
<body class="bg-surface-page text-ink font-sans antialiased">
    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <x-elements.sidebar>
            <x-sidebars.main />
        </x-elements.sidebar>

        <!-- Main Content Wrapper -->
        <div class="flex flex-1 flex-col overflow-hidden">

            <!-- Top Header -->
            <x-elements.app-header />

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-surface-page p-4 sm:p-6">
                <!-- Flash Messages -->
                @if (session('status'))
                    <div class="mb-6 rounded-lg border-r-4 border-success-500 bg-success-50 p-4 shadow-ui-soft flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-success-500 text-lg" aria-hidden="true"></i>
                        </div>
                        <div class="ms-3">
                            <p class="text-sm font-medium text-success-800">{{ session('status') }}</p>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-lg border-r-4 border-danger-500 bg-danger-50 p-4 shadow-ui-soft flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-danger-500 text-lg" aria-hidden="true"></i>
                        </div>
                        <div class="ms-3">
                            <p class="text-sm font-medium text-danger-800">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border-r-4 border-danger-500 bg-danger-50 p-4 shadow-ui-soft flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-danger-500 text-lg" aria-hidden="true"></i>
                        </div>
                        <div class="ms-3">
                            <ul class="list-disc list-inside text-sm font-medium text-danger-800">
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
