<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Http\Resources\ActionProposalResource;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Registry\IntentNarrationRegistry;
use Fluxio\Actions\Registry\IntentRegistry;
use Fluxio\Actions\Services\NarrationProjectionService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Intent Narration — slice 1 (canonical phrase foundation).
 *
 * Invariants:
 * 1. Every registered free-text intent has narration metadata.
 * 2. Every narration entry resolves to an existing EN + IT translation.
 * 3. Every canonical-template placeholder is a requirement key of its intent.
 * 4. The projection returns null when required data is missing.
 * 5. The projection returns the expected canonical phrase (EN + IT) when data is present.
 * 6. Narration is NOT exposed via ActionProposalResource (slice boundary).
 *
 * These tests build ActionProposal instances in memory (no persistence) because the
 * projection is pure and read-only.
 */
class IntentNarrationTest extends TestCase
{
    private function narrationRegistry(): IntentNarrationRegistry
    {
        return $this->app->make(IntentNarrationRegistry::class);
    }

    private function intentRegistry(): IntentRegistry
    {
        return $this->app->make(IntentRegistry::class);
    }

    private function projection(): NarrationProjectionService
    {
        return $this->app->make(NarrationProjectionService::class);
    }

    // ── 1. Coverage: every registered intent is narratable ────────────────────

    public function test_every_registered_intent_has_narration_metadata(): void
    {
        $narration = $this->narrationRegistry();

        foreach ($this->intentRegistry()->all() as $intent => $definition) {
            $this->assertNotNull(
                $narration->find($intent),
                "Expected narration metadata for registered intent [{$intent}]."
            );
        }
    }

    public function test_narration_registry_covers_the_five_mvp_intents(): void
    {
        $narration = $this->narrationRegistry();

        foreach ([
            'create_task',
            'schedule_call',
            'schedule_meeting',
            'assign_lead',
            'prepare_contract_from_quote',
        ] as $intent) {
            $this->assertNotNull($narration->find($intent), "Missing narration for [{$intent}].");
        }
    }

    public function test_unknown_intent_has_no_narration_metadata(): void
    {
        $this->assertNull($this->narrationRegistry()->find('does_not_exist'));
    }

    // ── 2. Translation keys exist for EN + IT ─────────────────────────────────

    public function test_every_canonical_template_key_resolves_in_english_and_italian(): void
    {
        foreach ($this->narrationRegistry()->all() as $definition) {
            foreach (['en', 'it'] as $locale) {
                $template = trans($definition->canonicalTemplateKey, [], $locale);

                $this->assertNotSame(
                    $definition->canonicalTemplateKey,
                    $template,
                    "Missing [{$locale}] translation for {$definition->canonicalTemplateKey}."
                );
                $this->assertIsString($template);
                $this->assertNotSame('', trim($template));
            }
        }
    }

    // ── 3. Placeholder compatibility with intent requirements ─────────────────

    public function test_every_placeholder_is_a_requirement_key_of_its_intent(): void
    {
        $intentRegistry = $this->intentRegistry();

        foreach ($this->narrationRegistry()->all() as $definition) {
            $intentDefinition = $intentRegistry->find($definition->intent);

            $this->assertNotNull(
                $intentDefinition,
                "Narration declared for unregistered intent [{$definition->intent}]."
            );

            // Authoritative allowed placeholders come from the runtime intent
            // definition, not from a re-declared slot map.
            $allowed = $intentDefinition->requirementKeys();

            foreach (['en', 'it'] as $locale) {
                $template = trans($definition->canonicalTemplateKey, [], $locale);

                preg_match_all('/:(\w+)/', (string) $template, $matches);

                foreach ($matches[1] as $placeholder) {
                    $this->assertContains(
                        $placeholder,
                        $allowed,
                        "Placeholder [:{$placeholder}] in {$definition->intent} ({$locale}) is not a requirement key. Allowed: ".implode(', ', $allowed)
                    );
                }
            }
        }
    }

    // ── 4. Incomplete proposals produce no false complete phrase ──────────────

    public function test_projection_returns_null_when_required_value_is_missing(): void
    {
        // schedule_meeting needs lead + date + time; time is absent here.
        $proposal = new ActionProposal([
            'intent' => 'schedule_meeting',
            'status' => 'draft',
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi SRL', 'source' => 'resolved', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-11', 'source' => 'detected', 'required' => true],
            ],
        ]);

        $this->assertNull($this->projection()->canonicalPhrase($proposal, 'en'));
    }

    public function test_projection_returns_null_when_no_editable_fields_present(): void
    {
        $proposal = new ActionProposal([
            'intent' => 'schedule_meeting',
            'status' => 'draft',
            'editable_fields' => [],
        ]);

        $this->assertNull($this->projection()->canonicalPhrase($proposal, 'en'));
    }

    public function test_projection_returns_null_for_unregistered_intent(): void
    {
        $proposal = new ActionProposal([
            'intent' => 'archive_record',
            'status' => 'draft',
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi SRL', 'source' => 'resolved', 'required' => true],
            ],
        ]);

        $this->assertNull($this->projection()->canonicalPhrase($proposal, 'en'));
    }

    public function test_projection_treats_empty_string_value_as_missing(): void
    {
        $proposal = new ActionProposal([
            'intent' => 'create_task',
            'status' => 'draft',
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => '   ', 'source' => 'detected', 'required' => false],
            ],
        ]);

        $this->assertNull($this->projection()->canonicalPhrase($proposal, 'en'));
    }

    // ── 5. Complete proposals produce the expected canonical phrase ───────────

    public function test_projection_returns_expected_english_phrase_for_schedule_meeting(): void
    {
        $proposal = $this->fullScheduleMeeting();

        $this->assertSame(
            'Schedule a meeting with Rossi SRL on 2026-05-11 at 10:00.',
            $this->projection()->canonicalPhrase($proposal, 'en'),
        );
    }

    public function test_projection_returns_expected_italian_phrase_for_schedule_meeting(): void
    {
        $proposal = $this->fullScheduleMeeting();

        $this->assertSame(
            'Pianifica un appuntamento con Rossi SRL il 2026-05-11 alle 10:00.',
            $this->projection()->canonicalPhrase($proposal, 'it'),
        );
    }

    public function test_projection_uses_resolved_lead_label_for_create_task(): void
    {
        // create_task canonical needs only :lead; the resolved label must be used.
        $proposal = new ActionProposal([
            'intent' => 'create_task',
            'status' => 'ready',
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Studio Rossi SRL', 'source' => 'resolved', 'required' => false],
            ],
        ]);

        $this->assertSame(
            'Create a task for Studio Rossi SRL.',
            $this->projection()->canonicalPhrase($proposal, 'en'),
        );
    }

    public function test_projection_returns_expected_phrase_for_assign_lead(): void
    {
        $proposal = new ActionProposal([
            'intent' => 'assign_lead',
            'status' => 'ready',
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi SRL', 'source' => 'resolved', 'required' => true],
                ['key' => 'assignee', 'label' => 'Assignee', 'value' => 'Giulia', 'source' => 'detected', 'required' => true],
            ],
        ]);

        $this->assertSame(
            'Assign Rossi SRL to Giulia.',
            $this->projection()->canonicalPhrase($proposal, 'en'),
        );
    }

    public function test_projection_is_deterministic(): void
    {
        $proposal = $this->fullScheduleMeeting();

        $first = $this->projection()->canonicalPhrase($proposal, 'en');
        $second = $this->projection()->canonicalPhrase($proposal, 'en');

        $this->assertSame($first, $second);
    }

    public function test_projection_does_not_mutate_the_proposal(): void
    {
        $proposal = $this->fullScheduleMeeting();
        $before = $proposal->getAttributes();

        $this->projection()->canonicalPhrase($proposal, 'en');

        // Attribute set is unchanged by the projection (the model is unsaved, so
        // isDirty() is irrelevant here — equality of attributes is the real check).
        $this->assertSame($before, $proposal->getAttributes());
    }

    // ── 6. Slice boundary: narration is not exposed via the resource ──────────

    public function test_action_proposal_resource_does_not_expose_narration(): void
    {
        $proposal = $this->fullScheduleMeeting();

        $array = (new ActionProposalResource($proposal))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('narration', $array);
        $this->assertArrayNotHasKey('canonical_phrase', $array);
    }

    private function fullScheduleMeeting(): ActionProposal
    {
        return new ActionProposal([
            'intent' => 'schedule_meeting',
            'status' => 'ready',
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi SRL', 'source' => 'resolved', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-11', 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00', 'source' => 'detected', 'required' => true],
                ['key' => 'participants', 'label' => 'Participants', 'value' => ['Luca'], 'source' => 'detected', 'required' => false],
            ],
        ]);
    }
}
