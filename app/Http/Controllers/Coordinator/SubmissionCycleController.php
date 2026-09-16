<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionCycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * BR-5 — the only place in the app (outside DevTestSeeder, which is
 * dev-only) that creates a SubmissionCycle. Everything is scoped through
 * $request->user()->coordinator so a coordinator only ever sees/creates
 * cycles under their own name (BR-11).
 */
class SubmissionCycleController extends Controller
{
    public function index(): View
    {
        $coordinator = request()->user()->coordinator;

        $cycles = $coordinator->submissionCycles()
            ->withCount('dailyAccomplishmentReports')
            ->orderByDesc('deadline_date')
            ->get();

        return view('coordinator.cycles.index', compact('cycles'));
    }

    public function create(): View
    {
        return view('coordinator.cycles.create');
    }

    public function store(StoreSubmissionCycleRequest $request): RedirectResponse
    {
        $coordinator = $request->user()->coordinator;

        $cycle = $coordinator->submissionCycles()->create($request->validated());

        return redirect()
            ->route('coordinator.cycles.index')
            ->with('status', "Cycle \"{$cycle->cycle_name}\" created.");
    }
}
