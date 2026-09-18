<x-guest-layout>
    <div class="text-center py-8">
        <h2 class="text-lg font-semibold text-gray-800">QR expired or already used</h2>
        <p class="mt-2 text-sm text-gray-600">{{ $reason ?? 'This QR code is no longer valid.' }}</p>
        <p class="mt-1 text-xs text-gray-500">Ask your coordinator to Generate a new QR (2-minute window, single-use).</p>
        @auth
            <p class="mt-3 text-sm text-gray-700">You're still logged in as <span class="font-medium">{{ auth()->user()->username }}</span> — the expired QR changed nothing.</p>
            <a href="{{ route('dashboard') }}" class="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Continue to dashboard</a>
        @else
            <a href="{{ route('login') }}" class="mt-4 inline-block text-sm text-blue-600 hover:underline">Back to login</a>
        @endauth
    </div>
</x-guest-layout>
