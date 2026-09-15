{{-- Laramina full --}}
<div id="app"></div>

<style>
    .bg-gradient-blue {
        background: linear-gradient(to right, #2563eb, #1e40af);
        color: white;
    }
</style>
{{-- show alert and Ajax --}}
<script src="{{asset('js/sweetalert/sweetalert2@11.14.4.js')}}"></script>
<script src="{{asset('js/custom/showalertProduction.js')}}"></script>

{{-- Laramina --}}
<script type="module" src="{{ asset('js/laramina/bootstrap/laramina.js') }}">
</script>

{{-- ارسال role کاربر به JS برای laramina --}}
@php
    $user = auth()->user();
    $roles = [];
    $permissions = [];
    if ($user) {
        if (method_exists($user, 'roles')) {
            $roles = $user->roles->pluck('name')->toArray();
        }
        if (method_exists($user, 'getAllPermissions')) {
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        }
    }
@endphp

<script>
    window.AdminUser = {
        roles: @json($roles),
        permissions: @json($permissions)
    };
</script>



{{-- 1️⃣ ساخت route export در Laravel برای laramina --}}

<script>
    window.LaravelRoutes = @json(
        collect(Route::getRoutes())->mapWithKeys(function ($route) {
            if ($name = $route->getName()) {
                return [$name => url($route->uri())];
            }
            return [];
        })
    );
</script>

{{-- تزریق ترجمه زبان --}}

<script>
    window.AdminLang = window.AdminLang || {};
    window.AdminLang.locale = "{{ app()->getLocale() }}";

    /* اگر یک زبان داریم */
    // window.AdminLang.messages = @json(trans('adminUI'));
    /*  اگر چند زبان داشته باشیم  */
    window.AdminLang.messages = {
        "fa": @json(trans('laramina::adminUI', [], 'fa')),
        "en": @json(trans('laramina::adminUI', [], 'en')),
    };
</script>



<script type="module" src="{{ asset('js/laramina/admin-lang.js') }}">
</script>