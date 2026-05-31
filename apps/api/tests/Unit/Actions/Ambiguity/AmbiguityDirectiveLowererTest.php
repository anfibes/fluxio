<?php

namespace Tests\Unit\Actions\Ambiguity;

use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\LabelSelector;
use Fluxio\Actions\DTO\Ambiguity\OrdinalSelector;
use Fluxio\Actions\DTO\Ambiguity\SemanticAmbiguityClarification;
use Fluxio\Actions\Exceptions\CannotLowerAmbiguityClarificationException;
use Fluxio\Actions\Support\AmbiguityDirectiveLowerer;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8C: the ambiguity clarification lowerer validates selector FORM only and
 * rejects malformed clarifications explicitly — including the identity-dimension
 * backdoor. It never resolves candidates. Pure unit — no DB, no runtime.
 */
class AmbiguityDirectiveLowererTest extends TestCase
{
    private AmbiguityDirectiveLowerer $lowerer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lowerer = new AmbiguityDirectiveLowerer;
    }

    public function test_lowers_a_valid_attribute_clarification(): void
    {
        $directive = $this->lowerer->lower(new SemanticAmbiguityClarification(
            ambiguityKey: 'lead',
            selector: new AttributeSelector('type', 'company'),
        ));

        $this->assertSame('lead', $directive->ambiguityKey);
        $this->assertInstanceOf(AttributeSelector::class, $directive->selector);
        $this->assertSame('type', $directive->selector->dimension);
        $this->assertSame('company', $directive->selector->value);
    }

    public function test_lowers_a_valid_ordinal_and_label_clarification(): void
    {
        $ordinal = $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new OrdinalSelector(1)));
        $label = $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new LabelSelector('Rossi SRL')));

        $this->assertInstanceOf(OrdinalSelector::class, $ordinal->selector);
        $this->assertInstanceOf(LabelSelector::class, $label->selector);
    }

    public function test_empty_ambiguity_key_is_rejected(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('   ', new OrdinalSelector(1)));
    }

    public function test_non_positive_ordinal_is_rejected(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new OrdinalSelector(0)));
    }

    public function test_empty_label_is_rejected(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new LabelSelector('   ')));
    }

    public function test_empty_attribute_value_is_rejected(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new AttributeSelector('type', '')));
    }

    public function test_identity_dimension_is_rejected_no_candidate_id_backdoor(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new AttributeSelector('id', '7')));
    }

    public function test_selected_candidate_id_dimension_is_rejected(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new AttributeSelector('selected_candidate_id', '12')));
    }

    public function test_candidate_id_dimension_is_rejected(): void
    {
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new AttributeSelector('candidate_id', '7')));
    }

    public function test_padded_identity_dimension_is_rejected_after_normalization(): void
    {
        // " id " must not bypass the forbidden-dimension check.
        $this->expectException(CannotLowerAmbiguityClarificationException::class);
        $this->lowerer->lower(new SemanticAmbiguityClarification('lead', new AttributeSelector(' id ', '7')));
    }
}
