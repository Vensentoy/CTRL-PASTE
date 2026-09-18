{{--
    BR-1/BR-11: every student here comes through $coordinator->students()
    — this coordinator's currently-assigned students only. Reassignment
    (moving a student to a different coordinator) happens on the
    per-student show page, not from this list.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Students
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-md p-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="border rounded-md divide-y">
            @forelse ($students as $student)
                <a href="{{ route('coordinator.students.show', $student) }}"
                   class="p-4 text-sm flex flex-wrap justify-between items-center hover:bg-gray-50 gap-x-3 gap-y-1">
                    <div>
                        <p class="font-medium">{{ $student->fullName() }}</p>
                        <p class="text-xs text-gray-500">{{ $student->student_id_number }} &middot; {{ $student->course }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm">{{ number_format($student->completed_hours, 1) }}/{{ $student->required_hours }}h</p>
                        <p class="text-xs text-gray-500">{{ $student->ojt_status }}</p>
                    </div>
                </a>
            @empty
                <p class="p-4 text-sm text-gray-500">No students currently assigned to you.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
