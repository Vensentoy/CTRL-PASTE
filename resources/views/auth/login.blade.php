{{-- QR-gated access: the form always renders (GET /login passes the gate),
but POST /login still requires a verified QR session for student usernames —
only coordinator usernames may POST without one (bootstrap, see
EnsureQrAccess). The banner below tells each visitor which case applies. --}}
<x-guest-layout>
    @if (session('qr_verified_at'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-xs text-green-800 text-center">
            QR verified — expires {{ \Carbon\Carbon::parse(session('qr_verified_expires_at'))->diffForHumans() }}. Proceed to log in.
        </div>
    @else
        <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800 text-center">
            Student access requires a coordinator QR scan — ask your coordinator to click Generate QR on their dashboard.
            Coordinators can log in directly below.
        </div>
    @endif

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
