<x-guest-layout>
    <div class="text-center py-8">
        <h2 class="text-lg font-semibold text-gray-800">QR expired or already used</h2>
        <p class="mt-2 text-sm text-gray-600">{{ $reason ?? 'This QR code is no longer valid.' }}</p>
        <p class="mt-1 text-xs text-gray-500">Ask your coordinator to Generate a new QR (2-minute window, single-use).</p>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-sm text-blue-600 hover:underline">Back to login</a>
    </div>
</x-guest-layout>
