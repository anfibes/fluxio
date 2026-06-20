<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Support\TaskStatusNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * TaskStatusNormalizer recognizes a target status ONLY from an explicit status slot
 * ("as"/"to <status>") or a leading status-changing verb — never from a status word
 * that merely appears inside a task title.
 */
class TaskStatusNormalizerTest extends TestCase
{
    private TaskStatusNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new TaskStatusNormalizer;
    }

    // ── Explicit status slot ("as"/"to") ──────────────────────────────────────

    public function test_mark_as_completed(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Mark the Rossi follow-up task as completed'));
    }

    public function test_mark_as_done_is_completed(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Mark the follow-up task as done'));
    }

    public function test_set_to_pending(): void
    {
        $this->assertSame('pending', $this->normalizer->normalize('Set the follow-up task to pending'));
    }

    public function test_move_to_in_progress(): void
    {
        $this->assertSame('in_progress', $this->normalizer->normalize('Move the onboarding task to in progress'));
    }

    public function test_move_to_in_progress_hyphenated(): void
    {
        $this->assertSame('in_progress', $this->normalizer->normalize('Move the onboarding task to in-progress'));
    }

    public function test_move_back_to_pending(): void
    {
        $this->assertSame('pending', $this->normalizer->normalize('Move the invoice task back to pending'));
    }

    // ── Leading status-changing verb ──────────────────────────────────────────

    public function test_complete_task(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Complete the follow-up task'));
    }

    public function test_finish_task_is_completed(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Finish the onboarding task'));
    }

    public function test_close_task_is_completed(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Close the support task'));
    }

    public function test_cancel_task(): void
    {
        $this->assertSame('cancelled', $this->normalizer->normalize('Cancel the onboarding task'));
    }

    public function test_reopen_task_is_pending(): void
    {
        $this->assertSame('pending', $this->normalizer->normalize('Reopen the billing task'));
    }

    // ── Negative: status word only inside the task title ──────────────────────

    public function test_update_the_cancel_subscription_task_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the Cancel subscription task'));
    }

    public function test_update_the_pending_invoice_task_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the Pending invoice task'));
    }

    public function test_update_the_completed_orders_task_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the Completed orders task'));
    }

    public function test_find_the_cancelled_contract_task_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Find the cancelled contract task'));
    }

    public function test_review_the_completed_orders_task_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Review the completed orders task'));
    }

    // ── Title status word ignored, explicit slot wins ─────────────────────────

    public function test_mark_cancel_subscription_task_as_completed_is_completed(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Mark the Cancel subscription task as completed'));
    }

    public function test_set_pending_invoice_task_to_completed_is_completed(): void
    {
        $this->assertSame('completed', $this->normalizer->normalize('Set the Pending invoice task to completed'));
    }

    // ── No status signal at all ───────────────────────────────────────────────

    public function test_update_task_without_status_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the follow-up task'));
    }

    public function test_unrelated_command_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Schedule a meeting with Rossi tomorrow'));
    }
}
