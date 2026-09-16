{{--
    Already username-based before the LoginRequest bug was found this
    session — this view is why the bug was non-obvious at first glance
    (it looked correct in isolation while the backend still expected
    'email'). Included here as-is, confirmed working once paired with
    the fixed LoginRequest.php.
--}}
<x-guest-layout>
    {{-- Phase 1: QR shortcut. Encodes request()->root() (the current
        request's root URL — never APP_URL) so it stays valid when the
        LAN IP changes. Not an auth mechanism: it only opens this page. --}}
    <div class="flex flex-col items-center mb-6">
        <div class="bg-white p-3 rounded-lg shadow border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            {!! QrCode::size(140)->generate(request()->root()); !!}
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Scan to open this page.') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Username -->
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
