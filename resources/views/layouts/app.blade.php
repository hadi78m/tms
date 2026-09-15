<!DOCTYPE html>
<html dir="rtl" lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-bind:class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script>

        <!-- Custom Styles -->
        <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
        <link rel="stylesheet" href="{{ asset('css/fonts.css') }}" />
        <link href="{{ asset('dist/dataTables/2.3.0/css/dataTables.dataTables.min.css') }}" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="{{ asset('dist/dataTables/buttons/3.2.3/css/buttons.dataTables.min.css') }}">
        <link href="{{asset('css/select2/4.1.0/select2.min.css')}}" rel="stylesheet" />
        <link rel="stylesheet" href="{{asset('css/persianDatepicker/persianDatepicker-default.css')}}" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-sans antialiased"
        x-data="{ mobileMenuOpen: false, darkMode: false }" :class="{ 'dark': darkMode }">
        
        <!-- هدر قدیمی -->
        <x-elements.header />

        {{-- loading --}}
        <div id="global-loading"
            class="fixed inset-0 z-[9999] hidden items-center justify-center bg-white/70 backdrop-blur-sm">
            <div class="flex flex-col items-center gap-4">
                <div class="h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                <span class="text-sm text-gray-600">در حال پردازش...</span>
            </div>
        </div>
        {{-- end loading --}}

        <!-- Page Content -->
        <div class="main-content">
            <div class="mx-auto p-4 bg-white py-6">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </div>
        
        <!-- Custom Scripts -->
        <script src="{{asset('js/jalaali-js/jalaali.min.js')}}"></script>
        <script src="{{ asset('js/select2/4.1.0/select2.min.js') }}"></script>
        <script src="{{ asset('js/sweetalert/sweetalert2@11.14.4.js') }}"></script>
        <script src="{{ asset('dist/dataTables/2.3.0/js/dataTables.min.js') }}"></script>
        <script type="text/javascript" charset="utf8" src="{{ asset('dist/dataTables/buttons/3.2.3/js/dataTables.buttons.min.js') }}"></script>
        <script type="text/javascript" charset="utf8" src="{{ asset('dist/dataTables/buttons/3.2.3/js/buttons.print.min.js') }}"></script>
        <script type="text/javascript" charset="utf8" src="{{ asset('dist/dataTables/buttons/3.2.3/js/buttons.html5.min.js') }}"></script>
        <script type="text/javascript" charset="utf8" src="{{ asset('dist/dataTables/js/jszip.min.js') }}"></script>
        <script src="{{ asset('dist/dataTables/rowgroup/1.5.1/js/dataTables.rowGroup.min.js') }}"></script>
        <script src="{{ asset('dist/dataTables/js/dataTables.rowsGroup.js') }}"></script>
        <script src="{{ asset('js/chartJs/4.4.9/chart.js') }}"></script>
        <script src="{{ asset('js/persianDatepicker/persianDatepicker.js') }}"></script>
        <script src="{{ asset('js/persianDatepicker/persianDatepicker.blade.js') }}"></script>
        <script src="{{asset('js/custom/showalertProduction.js')}}"></script>
        <script src="{{ asset('js/custom/ajaxRequest.js') }}"></script>

        {{-- Laramina --}}
        @include('laramina::laramina')

        <script src="{{ asset('js/jquery-sortable-min.js') }}"></script>
        @yield('scripts')
    </body>
</html>
