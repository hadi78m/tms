<header class="fixed top-0 right-0 left-0 z-50 h-[8.4375rem] bg-[url('/public/img/header.png')]">
    <div class="bg-[#00000042]  h-full">
        <div class="container mx-auto h-full flex flex-col justify-center items-center relative">
            <!-- لوگو در مرکز هدر -->
            <div class=" flex items-center justify-center mb-2">
                {{-- <img src="{{ asset('img/kar-white.png') }}" alt="logo" class="h-[8rem] w-50" loading="lazy"> --}}
                {{-- <h1 class="text-[25px] font-bold text-white">@lang('app.title')</h1> --}}


                {{-- <span class="text-white text-xl font-bold">لوگو</span> --}}
            </div>
            {{-- <h1 class="text-white text-2xl md:text-3xl font-bold">عنوان سایت</h1> --}}

            <!-- دکمه منو برای موبایل -->
            <button class="lg:hidden absolute left-4 top-1/2 transform -translate-y-1/2"
                @click="mobileMenuOpen = !mobileMenuOpen">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>
</header>
