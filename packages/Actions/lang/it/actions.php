<?php

return [
    'interpreted' => 'Comando interpretato correttamente.',
    'confirmed' => 'Proposta di azione confermata correttamente.',
    'cannot_confirm' => 'Questa proposta di azione non può essere confermata.',
    'executed' => 'Azione eseguita correttamente.',
    'cannot_execute' => 'Questa proposta di azione non può essere eseguita.',
    'ambiguous_lead' => 'Più lead corrispondono al nome indicato. Si prega di chiarire.',
    'unknown_lead' => 'Nessun lead corrisponde al nome indicato.',
    'ambiguous_assignee' => 'Più utenti corrispondono al nome indicato. Si prega di chiarire.',
    'unknown_assignee' => 'Nessun utente corrisponde al nome dell\'assegnatario.',
    'ambiguous_task' => 'Più attività corrispondono al titolo indicato. Si prega di chiarire.',
    'unknown_task' => 'Nessuna attività corrisponde al titolo indicato.',
    'invalid_task_status' => 'Lo stato di destinazione non è uno stato valido per l\'attività.',
    'refined' => 'Proposta di azione affinata correttamente.',
    'cannot_refine' => 'Questa proposta di azione non può essere affinata.',
    'refinement_not_recognized' => 'L\'affinamento non è stato applicato. La proposta è rimasta invariata.',
    'ambiguity_still_unresolved' => 'Il chiarimento corrisponde a più candidati. Sii più specifico.',
    'ambiguity_narrowed_type' => 'Ci sono ancora più :type corrispondenti: :candidates. Selezionane una.',
    'candidate_types' => [
        'company' => 'aziende',
        'person' => 'persone',
    ],
    'mutation_not_allowed' => 'Una o più modifiche richieste non sono supportate per questo tipo di azione.',
    'ambiguity_resolution_not_supported' => 'Questo tipo di azione non supporta la risoluzione delle ambiguità.',
    'cannot_shift_time_no_current_time' => 'Impossibile spostare l\'orario perché la proposta non ha un orario attuale.',

    'execution_failure' => [
        'unsupported_intent' => 'Questo tipo di azione non può essere eseguito.',
        'execution_failed' => 'Non è stato possibile completare l\'azione. Nessuna modifica è stata applicata.',
    ],

    // Frasi canoniche dei comandi (Intent Narration — slice 1).
    // I segnaposto DEVONO essere chiavi di requisito della relativa IntentDefinition.
    // I valori sono riempiti in modo deterministico dallo stato risolto della
    // proposta; una proposta incompleta non produce alcuna frase (la proiezione
    // restituisce null).
    'narration' => [
        'create_task' => [
            'canonical' => 'Crea un\'attività per :lead.',
        ],
        'schedule_call' => [
            'canonical' => 'Pianifica una chiamata con :lead il :date alle :time.',
        ],
        'schedule_meeting' => [
            'canonical' => 'Pianifica un appuntamento con :lead il :date alle :time.',
        ],
        'assign_lead' => [
            'canonical' => 'Assegna :lead a :assignee.',
        ],
        'prepare_contract_from_quote' => [
            'canonical' => 'Prepara un contratto per :lead dal preventivo :quote.',
        ],
        'update_task_status' => [
            'canonical' => 'Imposta l\'attività :task come :state.',
        ],
    ],

    'capabilities' => [
        'operations' => [
            'replace' => 'Aggiorna',
            'clear' => 'Svuota',
            'append' => 'Aggiungi',
            'remove' => 'Rimuovi',
        ],
        'fields' => [
            'date' => 'Data',
            'time' => 'Ora',
            'priority' => 'Priorità',
            'lead' => 'Lead',
            'location' => 'Luogo',
            'participants' => 'Partecipanti',
            'assignee' => 'Assegnatario',
            'quote' => 'Preventivo',
            'task' => 'Attività',
            'state' => 'Stato',
        ],
        'refinements' => [
            'replace_field' => 'Aggiorna campi',
            'clear_field' => 'Svuota campi',
            'append_collection_item' => 'Aggiungi elementi',
            'remove_collection_item' => 'Rimuovi elementi',
            'replace_collection_item' => 'Sostituisci elementi',
            'resolve_ambiguity' => 'Risolvi ambiguità',
            'contextual_reference' => 'Usa riferimenti contestuali',
        ],
        'intents' => [
            'schedule_meeting' => [
                'refinements' => [
                    'append_collection_item' => 'Aggiungi partecipanti',
                    'remove_collection_item' => 'Rimuovi partecipanti',
                    'replace_collection_item' => 'Sostituisci partecipanti',
                ],
            ],
            'schedule_call' => [
                'refinements' => [
                    'append_collection_item' => 'Aggiungi partecipanti',
                    'remove_collection_item' => 'Rimuovi partecipanti',
                    'replace_collection_item' => 'Sostituisci partecipanti',
                ],
            ],
        ],
    ],
];
