<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Support\LeadStatusNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * LeadStatusNormalizer recognizes a target lead status ONLY from an explicit status
 * slot ("as"/"to <status>") or a leading lifecycle verb — never from a status word
 * that merely appears inside a lead name/title.
 */
class LeadStatusNormalizerTest extends TestCase
{
    private LeadStatusNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new LeadStatusNormalizer;
    }

    // ── Explicit status slot ("as"/"to") ──────────────────────────────────────

    public function test_mark_as_contacted(): void
    {
        $this->assertSame('contacted', $this->normalizer->normalize('Mark Rossi as contacted'));
    }

    public function test_set_to_qualified(): void
    {
        $this->assertSame('qualified', $this->normalizer->normalize('Set Rossi to qualified'));
    }

    public function test_move_to_won(): void
    {
        $this->assertSame('won', $this->normalizer->normalize('Move Rossi to won'));
    }

    public function test_mark_lead_as_lost(): void
    {
        $this->assertSame('lost', $this->normalizer->normalize('Mark the Rossi lead as lost'));
    }

    public function test_set_to_new(): void
    {
        $this->assertSame('new', $this->normalizer->normalize('Set the Rossi lead to new'));
    }

    // ── Leading lifecycle verb ────────────────────────────────────────────────

    public function test_qualify_lead(): void
    {
        $this->assertSame('qualified', $this->normalizer->normalize('Qualify Rossi'));
    }

    public function test_contacted_lead(): void
    {
        $this->assertSame('contacted', $this->normalizer->normalize('Contacted Rossi yesterday'));
    }

    // ── Negative: status word only inside the lead name/title ──────────────────

    public function test_update_the_lost_account_lead_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the Lost account lead'));
    }

    public function test_review_the_qualified_pipeline_lead_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Review the Qualified pipeline lead'));
    }

    public function test_find_the_contacted_leads_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Find the Contacted leads'));
    }

    public function test_won_account_in_name_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the Won deal lead'));
    }

    // ── Title status word ignored, explicit slot wins ─────────────────────────

    public function test_mark_lost_account_lead_as_contacted_is_contacted(): void
    {
        $this->assertSame('contacted', $this->normalizer->normalize('Mark the Lost account lead as contacted'));
    }

    // ── No status signal at all ───────────────────────────────────────────────

    public function test_update_lead_without_status_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Update the Rossi lead'));
    }

    public function test_assign_command_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Assign Rossini to Marco'));
    }

    public function test_task_command_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('Mark the follow-up task as completed'));
    }

    // ── Only canonical Lead statuses are produced ─────────────────────────────

    public function test_only_produces_lead_statuses(): void
    {
        $allowed = ['new', 'contacted', 'qualified', 'lost', 'won'];

        foreach ([
            'Mark Rossi as new',
            'Set Rossi to contacted',
            'Move Rossi to qualified',
            'Mark Rossi as lost',
            'Set Rossi to won',
            'Qualify Rossi',
        ] as $phrase) {
            $result = $this->normalizer->normalize($phrase);
            $this->assertContains($result, $allowed, "Unexpected status for [{$phrase}].");
        }
    }
}
