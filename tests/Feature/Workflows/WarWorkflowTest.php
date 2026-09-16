<?php

namespace Tests\Feature\Workflows;

use App\Models\WeeklyAccomplishmentReport;

/**
 * workflows.md §3 — Weekly Accomplishment Report (BR-8: one document per
 * student per month, four independently-reviewed week-sections; BR-7:
 * each week is reviewed separately).
 */
class WarWorkflowTest extends WorkflowTestCase
{
    public function test_show_lazily_creates_the_current_months_war(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war');

        $this->actingAs($student->user)
            ->get(route('student.war.show'))
            ->assertOk();

        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->assertNotNull($war);
        $this->assertEquals(
            now()->startOfMonth()->toDateString(),
            $war->month_period->format('Y-m-d')
        );
    }

    public function test_week_content_can_be_saved_before_submit(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war');

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)
            ->patch(route('student.war.week.update', $war), [
                'week' => 1,
                'activities' => 'Week one activities.',
                'hours' => 8,
            ])
            ->assertRedirect(route('student.war.show'));

        $this->assertSame('Week one activities.', $war->fresh()->week1_activities);
        $this->assertEquals(8, (float) $war->fresh()->week1_hours);
        $this->assertSame('Draft', $war->fresh()->week1_status);
    }

    public function test_submitting_week_pair_1_2_marks_both_pending(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war');
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)
            ->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id])
            ->assertRedirect(route('student.war.show'));

        $fresh = $war->fresh();
        $this->assertSame($cycle->id, $fresh->cycle1_id);
        $this->assertNull($fresh->cycle2_id);
        $this->assertSame('Pending', $fresh->week1_status);
        $this->assertSame('Pending', $fresh->week2_status);
        $this->assertSame('Draft', $fresh->week3_status);

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Submit']);
    }

    public function test_second_submit_uses_the_second_cycle_slot(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war');
        $cycle1 = $this->makeCycle($coordinator, ['cycle_name' => 'Cycle One']);
        $cycle2 = $this->makeCycle($coordinator, ['cycle_name' => 'Cycle Two']);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle1->id]);
        $this->actingAs($student->user)
            ->post(route('student.war.submit', $war), ['cycle_id' => $cycle2->id])
            ->assertRedirect(route('student.war.show'));

        $fresh = $war->fresh();
        $this->assertSame($cycle2->id, $fresh->cycle2_id);
        $this->assertSame('Pending', $fresh->week3_status);
        $this->assertSame('Pending', $fresh->week4_status);
    }

    public function test_third_submit_is_rejected(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war');
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id]);
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id]);

        $this->actingAs($student->user)
            ->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id])
            ->assertSessionHas('status', 'This month\'s WAR already has both week-pairs submitted.');
    }

    public function test_coordinator_reviews_a_single_week_branch(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war', ['required_hours' => 20]);
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->patch(route('student.war.week.update', $war), [
            'week' => 1,
            'activities' => 'Reviewed week.',
            'hours' => 8,
        ]);
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id]);

        $this->actingAs($coordinator->user)
            ->patch(route('coordinator.war.review.act', $war), [
                'week' => 1,
                'decision' => 'approve',
                'coordinator_comment' => 'Good work.',
            ])
            ->assertRedirect(route('coordinator.war.review', $cycle));

        $fresh = $war->fresh();
        $this->assertSame('Approved', $fresh->week1_status);
        $this->assertSame('Pending', $fresh->week2_status); // BR-7: weeks are independent
        $this->assertEquals(8, (float) $fresh->student->completed_hours); // BR-10 recalc

        $this->assertDatabaseHas('audit_logs', ['action_type' => 'Approve']);
    }

    public function test_wrong_coordinator_cannot_review_a_week(): void
    {
        $coordinatorA = $this->makeCoordinator('coord.a', 'Coordinator A');
        $coordinatorB = $this->makeCoordinator('coord.b', 'Coordinator B');
        $student = $this->makeStudent($coordinatorA, 'student.war');
        $cycle = $this->makeCycle($coordinatorA);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id]);

        // Wrong coordinator cannot even open the review screen for A's cycle.
        $this->actingAs($coordinatorB->user)
            ->get(route('coordinator.war.review', $cycle))
            ->assertForbidden();

        // Nor act on the WAR row directly.
        $this->actingAs($coordinatorB->user)
            ->patch(route('coordinator.war.review.act', $war), [
                'week' => 1,
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->assertSame('Pending', $war->fresh()->week1_status);
    }

    public function test_week_cannot_be_edited_after_submit(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war');
        $cycle = $this->makeCycle($coordinator);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();
        $this->actingAs($student->user)->post(route('student.war.submit', $war), ['cycle_id' => $cycle->id]);

        $this->actingAs($student->user)
            ->patch(route('student.war.week.update', $war), [
                'week' => 1,
                'activities' => 'Too late to edit.',
                'hours' => 10,
            ])
            ->assertForbidden();
    }

    public function test_completed_student_cannot_edit_week_content(): void
    {
        $coordinator = $this->makeCoordinator();
        $student = $this->makeStudent($coordinator, 'student.war', ['ojt_status' => 'Completed']);

        $this->actingAs($student->user)->get(route('student.war.show'));
        $war = WeeklyAccomplishmentReport::where('student_id', $student->id)->first();

        $this->actingAs($student->user)
            ->patch(route('student.war.week.update', $war), [
                'week' => 1,
                'activities' => 'Blocked edit.',
                'hours' => 8,
            ])
            ->assertSessionHasErrors('activities');
    }
}