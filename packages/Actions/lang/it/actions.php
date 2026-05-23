<?php

return [
    'interpreted' => 'Comando interpretato correttamente.',
    'confirmed' => 'Proposta di azione confermata correttamente.',
    'cannot_confirm' => 'Questa proposta di azione non può essere confermata.',
    'executed' => 'Azione eseguita correttamente.',
    'cannot_execute' => 'Questa proposta di azione non può essere eseguita.',
    'ambiguous_lead' => 'Più lead corrispondono al nome indicato. Si prega di chiarire.',
    'refined' => 'Proposta di azione affinata correttamente.',
    'cannot_refine' => 'Questa proposta di azione non può essere affinata.',
    'refinement_not_recognized' => 'L\'affinamento non è stato applicato. La proposta è rimasta invariata.',
    'ambiguity_still_unresolved'         => 'Il chiarimento corrisponde a più candidati. Sii più specifico.',
    'mutation_not_allowed'               => 'Una o più modifiche richieste non sono supportate per questo tipo di azione.',
    'ambiguity_resolution_not_supported' => 'Questo tipo di azione non supporta la risoluzione delle ambiguità.',

    'capabilities' => [
        'operations' => [
            'replace' => 'Aggiorna',
            'clear'   => 'Svuota',
            'append'  => 'Aggiungi',
            'remove'  => 'Rimuovi',
        ],
        'fields' => [
            'date'         => 'Data',
            'time'         => 'Ora',
            'priority'     => 'Priorità',
            'lead'         => 'Lead',
            'location'     => 'Luogo',
            'participants' => 'Partecipanti',
            'assignee'     => 'Assegnatario',
            'quote'        => 'Preventivo',
        ],
        'refinements' => [
            'replace_field'            => 'Aggiorna campi',
            'clear_field'              => 'Svuota campi',
            'append_collection_item'   => 'Aggiungi elementi',
            'remove_collection_item'   => 'Rimuovi elementi',
            'replace_collection_item'  => 'Sostituisci elementi',
            'resolve_ambiguity'        => 'Risolvi ambiguità',
            'contextual_reference'     => 'Usa riferimenti contestuali',
        ],
        'intents' => [
            'schedule_meeting' => [
                'refinements' => [
                    'append_collection_item'  => 'Aggiungi partecipanti',
                    'remove_collection_item'  => 'Rimuovi partecipanti',
                    'replace_collection_item' => 'Sostituisci partecipanti',
                ],
            ],
            'schedule_call' => [
                'refinements' => [
                    'append_collection_item'  => 'Aggiungi partecipanti',
                    'remove_collection_item'  => 'Rimuovi partecipanti',
                    'replace_collection_item' => 'Sostituisci partecipanti',
                ],
            ],
        ],
    ],
];
