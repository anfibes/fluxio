<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Support\LeadReferenceExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9E.1 — the lead reference span extractor, tested in isolation (it was
 * extracted from RuleBasedIntentResolver so the span-extraction contract is a small,
 * single-responsibility unit). It returns the user-facing span only — never an
 * identity — and is the deterministic baseline a future provider's span extraction
 * can be compared against.
 */
class LeadReferenceExtractorTest extends TestCase
{
    private LeadReferenceExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new LeadReferenceExtractor;
    }

    public function test_create_task_preserves_full_person_span(): void
    {
        $this->assertSame(
            'Mario Rossi',
            $this->extractor->extract('Create a high priority follow-up task for Mario Rossi tomorrow at 10', 'create_task'),
        );
    }

    public function test_create_task_preserves_company_span(): void
    {
        $this->assertSame(
            'Rossi SRL',
            $this->extractor->extract('Create a follow-up task for Rossi SRL tomorrow', 'create_task'),
        );
    }

    public function test_schedule_meeting_trims_at_next_weekday(): void
    {
        $this->assertSame(
            'Rossi SRL',
            $this->extractor->extract('Schedule a meeting with Rossi SRL next Friday at 3pm', 'schedule_meeting'),
        );
    }

    public function test_schedule_call_with_anchor_takes_precedence_over_call_verb(): void
    {
        $this->assertSame(
            'Mario Rossi',
            $this->extractor->extract('Schedule a call with Mario Rossi tomorrow at 10', 'schedule_call'),
        );
    }

    public function test_schedule_call_falls_back_to_call_verb_anchor(): void
    {
        $this->assertSame('Rossini', $this->extractor->extract('Call Rossini', 'schedule_call'));
        $this->assertSame('Rossi', $this->extractor->extract('Call Rossi tomorrow at 10', 'schedule_call'));
    }

    public function test_assign_lead_takes_span_between_assign_and_to(): void
    {
        $this->assertSame('Mario Rossi', $this->extractor->extract('Assign Mario Rossi to Marco', 'assign_lead'));
        $this->assertSame('Rossini', $this->extractor->extract('Assign Rossini', 'assign_lead'));
    }

    public function test_preserves_original_casing(): void
    {
        $this->assertSame('rossini', $this->extractor->extract('Create a task for rossini', 'create_task'));
        $this->assertSame('ROSSINI', $this->extractor->extract('CREATE A TASK FOR ROSSINI', 'create_task'));
    }

    public function test_returns_null_when_no_reference(): void
    {
        $this->assertNull($this->extractor->extract('Create a task for tomorrow', 'create_task'));
        $this->assertNull($this->extractor->extract('Create a task', 'create_task'));
    }
}
