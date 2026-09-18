<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            New Submission Cycle
        </h2>
    </x-slot>

    <div class="py-8 max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('coordinator.cycles.store') }}" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="cycle_name" value="Cycle Name" />
                <x-text-input id="cycle_name" name="cycle_name" type="text" class="block mt-1 w-full"
                              :value="old('cycle_name')" placeholder="e.g. Cycle 1 – September 2026" required />
                <x-input-error :messages="$errors->get('cycle_name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="coverage_start_date" value="Coverage Start" />
                    <x-text-input id="coverage_start_date" name="coverage_start_date" type="date"
                                  class="block mt-1 w-full" :value="old('coverage_start_date')" required />
                    <x-input-error :messages="$errors->get('coverage_start_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="coverage_end_date" value="Coverage End" />
                    <x-text-input id="coverage_end_date" name="coverage_end_date" type="date"
                                  class="block mt-1 w-full" :value="old('coverage_end_date')" required />
                    <x-input-error :messages="$errors->get('coverage_end_date')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="deadline_date" value="Deadline" />
                <x-text-input id="deadline_date" name="deadline_date" type="date"
                              class="block mt-1 w-full" :value="old('deadline_date')" required />
                <x-input-error :messages="$errors->get('deadline_date')" class="mt-2" />
                <p class="text-xs text-gray-500 mt-1">
                    BR-6: whether a report counts as Late depends only on this date, never on the
                    activity date being reported.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('coordinator.cycles.index') }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md">
                    Create Cycle
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
