<?php

namespace Tests\Feature\Workflows;

/**
 * Coordinator roster, reassignment (BR-1/BR-11), archiving guards, and
 * DAR review decisions (workflows.md §4) — the "coordinator clicks" half
 * of the click-through.
 */
class CoordinatorFlowTest extends WorkflowTestCase
{
    public function test_roster_shows_only_own_students(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $mine = $this->makeStudent($coordA, 'student.mine', ['surname' => 'Alpha']);
        $theirs = $this->makeStudent($coordB, 'student.theirs', ['surname' => 'Zulu']);

        $this->actingAs($coordA->user)
            ->get(route('coordinator.students.index'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Zulu');
    }

    public function test_student_show_is_denied_for_a_non_owning_coordinator(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordA, 'student.shared');

        $this->actingAs($coordA->user)
            ->get(route('coordinator.students.show', $student))
            ->assertOk();

        $this->actingAs($coordB->user)
            ->get(route('coordinator.students.show', $student))
            ->assertForbidden();
    }

    public function test_reassign_preserves_report_history_and_swaps_ownership(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordA, 'student.move');
        $cycle = $this->makeCycle($coordA);

        // History that must survive the move (BR-1): a submitted DAR tied
        // to a cycle that belongs to coordinator A.
        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($coordA->user)
            ->patch(route('coordinator.students.reassign', $student), [
                'coordinator_id' => $coordB->id,
            ])
            ->assertRedirect(route('coordinator.students.index'));

        $this->assertSame($coordB->id, $student->fresh()->coordinator_id);

        // History untouched: DAR still points at the same student + cycle,
        // and the cycle is still owned by coordinator A.
        $this->assertSame($student->id, $dar->fresh()->student_id);
        $this->assertSame($cycle->id, $dar->fresh()->cycle_id);
        $this->assertSame($coordA->id, $cycle->fresh()->coordinator_id);

        // Ownership moved: old coordinator loses access, new one gains it.
        $this->actingAs($coordA->user)
            ->get(route('coordinator.students.show', $student))
            ->assertForbidden();

        $this->actingAs($coordB->user)
            ->get(route('coordinator.students.show', $student))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'AccountChange']);
    }

    public function test_reassign_to_the_same_coordinator_is_rejected(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $student = $this->makeStudent($coordA, 'student.move');

        $this->actingAs($coordA->user)
            ->patch(route('coordinator.students.reassign', $student), [
                'coordinator_id' => $coordA->id,
            ])
            ->assertSessionHasErrors('coordinator_id');
    }

    public function test_reassign_is_blocked_for_archived_records(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordA, 'student.move');

        $student->user->update(['status' => 'Archived']);

        $this->actingAs($coordA->user)
            ->patch(route('coordinator.students.reassign', $student), [
                'coordinator_id' => $coordB->id,
            ])
            ->assertRedirect(route('coordinator.students.show', $student));

        $this->assertSame($coordA->id, $student->fresh()->coordinator_id);
    }

    public function test_dar_review_approve_updates_hours_and_audit_trail(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.review');
        $cycle = $this->makeCycle($coordinator);

        $dar = $this->makeDar($student, [
            'report_date' => now()->toDateString(),
            // 4h
            'activities' => [
                ['activity' => 'Review queue work.', 'time_started' => '08:00', 'time_ended' => '12:00'],
            ],
        ]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.dar.review.act', $dar), [
                'decision' => 'approve',
                'coordinator_comment' => 'Good work.',
            ])
            ->assertRedirect(route('coordinator.dar.review', $cycle));

        $fresh = $dar->fresh();
        $this->assertSame('Approved', $fresh->status);
        $this->assertSame($coordinator->user->id, $fresh->reviewed_by);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertEquals(4, (float) $student->fresh()->completed_hours);

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Approve']);
    }

    public function test_dar_review_return_requires_a_comment(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.review');
        $cycle = $this->makeCycle($coordinator);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.dar.review.act', $dar), [
                'decision' => 'return',
                'coordinator_comment' => '',
            ])
            ->assertSessionHasErrors('coordinator_comment');

        $this->assertSame('Pending', $dar->fresh()->status);
    }

    public function test_dar_review_return_sends_it_back_for_revision(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.review');
        $cycle = $this->makeCycle($coordinator);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.dar.review.act', $dar), [
                'decision' => 'return',
                'coordinator_comment' => 'Please add your supervisor remarks.',
            ])
            ->assertRedirect(route('coordinator.dar.review', $cycle));

        $this->assertSame('Returned', $dar->fresh()->status);
        $this->assertSame('Please add your supervisor remarks.', $dar->fresh()->coordinator_comment);

        // Student resubmits via update -> straight back to Pending.
        $this->actingAs($student->user)
            ->put(route('student.dar.update', $dar), [
                'report_date' => now()->toDateString(),
                'activities' => [
                    ['activity' => 'Revised with remarks.', 'time_started' => '08:00', 'time_ended' => '12:00'],
                    ['activity' => 'Added supervisor sign-off note.', 'time_started' => '13:00', 'time_ended' => '14:00'],
                ],
            ])
            ->assertRedirect(route('student.dar.index'));

        $fresh = $dar->fresh();
        $this->assertSame('Pending', $fresh->status);
        $this->assertNull($fresh->coordinator_comment);
    }

    public function test_a_draft_cannot_be_reviewed(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.review');

        $draft = $this->makeDar($student, ['report_date' => now()->toDateString()]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.dar.review.act', $draft), [
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->assertSame('Draft', $draft->fresh()->status);
    }

    public function test_non_owning_coordinator_cannot_review_anothers_student_dar(): void
    {
        $coordA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordA, 'student.review');
        $cycle = $this->makeCycle($coordA);

        $dar = $this->makeDar($student, ['report_date' => now()->toDateString()]);
        $this->actingAs($student->user)->post(route('student.dar.submit'), [
            'cycle_id' => $cycle->id,
            'dar_ids' => [$dar->id],
        ]);

        $this->actingAs($coordB->user)
            ->get(route('coordinator.dar.review', $cycle))
            ->assertForbidden();

        $this->actingAs($coordB->user)
            ->patch(route('coordinator.dar.review.act', $dar), [
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->assertSame('Pending', $dar->fresh()->status);
    }

    public function test_audit_log_viewer_is_reachable_only_by_coordinators(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.log');

        $this->actingAs($coordinator->user)
            ->get(route('coordinator.audit-log.index'))
            ->assertOk();

        $this->actingAs($student->user)
            ->get(route('coordinator.audit-log.index'))
            ->assertForbidden();
    }
}