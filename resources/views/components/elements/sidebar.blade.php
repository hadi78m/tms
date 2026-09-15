<style>
    .main-content {
        margin-right: 16rem !important;
    }

    @media (max-width: 1023px) {
        .main-content {
            margin-right: 0 !important;
        }
    }
</style>


<aside
    class="fixed right-0 top-[8.4375rem] h-[calc(100vh-8.4375rem)] w-64 bg-white shadow-lg z-40 transition-all duration-300"
    :class="{ 'right-0': mobileMenuOpen, '-right-64 lg:right-0': !mobileMenuOpen }" x-data="{ openDropdown: false }">
    <div class="h-full flex flex-col">
        <!-- هدر سایدبار -->
        <div class="p-4 mb-4 border-b">
            {{-- <h3 class="font-bold text-lg">منوی سایدبار</h3> --}}
        </div>
        <div class="flex-1 overflow-y-auto p-4 ">
            <ul class="space-y-2">
                <li class="">
                    <a href="/"
                        class="flex items-center block p-2 hover:bg-blue-50 hover:text-red-400 rounded text-gray-700">
                        <x-bi-pie-chart-fill />
                        <span class="mr-3">
                            @lang('app.dashboard')
                        </span>
                    </a>
                </li>

                {{ $slot }}
                <li><a href="{{ route('logout') }}"
                        class="flex items-center block p-2 hover:text-red-400 hover:bg-blue-50 rounded text-gray-700">
                        <x-bi-box-arrow-left />
                        <span class="mr-3">
                            @lang('app.logout')
                        </span>
                    </a>
                </li>
            </ul>

    </div>
</aside>
