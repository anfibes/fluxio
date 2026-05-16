<?php

namespace Fluxio\Actions\EntityResolution\Repositories;

class InMemoryLeadRepository
{
    /** @var array<int, array{id: int, type: string, label: string, description: string|null}> */
    private const LEADS = [
        ['id' => 1,  'type' => 'person',  'label' => 'Mario Rossi',  'description' => 'Individual lead'],
        ['id' => 5,  'type' => 'company', 'label' => 'Rossini',      'description' => 'Rossini & Partners'],
        ['id' => 7,  'type' => 'company', 'label' => 'Rossi SRL',    'description' => 'Company lead'],
        ['id' => 12, 'type' => 'company', 'label' => 'Studio Rossi', 'description' => 'Professional studio'],
    ];

    /**
     * @param array<int, array{id: int|string, type: string, label: string, description: string|null}>|null $overrides
     *   Pass a custom dataset to override the default leads (useful in unit tests).
     */
    public function __construct(private readonly ?array $overrides = null) {}

    /** @return array<int, array{id: int|string, type: string, label: string, description: string|null}> */
    public function all(): array
    {
        return $this->overrides ?? self::LEADS;
    }
}
