<x-guest-layout>
    <div class="text-center py-8">
        <div class="mx-auto w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-800">Scan the QR to continue</h2>
        <p class="mt-2 text-sm text-gray-600">This site is available only via a coordinator-generated QR code. Please ask your coordinator to click <span class="font-medium">Generate QR</span> on their dashboard and scan the code with your device.</p>
        <p class="mt-1 text-xs text-gray-500">Direct URL entry without scanning is blocked. QR codes expire in 2 minutes and are single-use.</p>
        @if ($errors->has('qr'))
            <p class="mt-3 text-sm text-red-600">{{ $errors->first('qr') }}</p>
        @endif
    </div>
</x-guest-layout>
