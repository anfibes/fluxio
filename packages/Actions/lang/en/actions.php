<?php

return [
    'interpreted' => 'Command interpreted successfully.',
    'confirmed' => 'Action proposal confirmed successfully.',
    'cannot_confirm' => 'This action proposal cannot be confirmed.',
    'executed' => 'Action executed successfully.',
    'cannot_execute' => 'This action proposal cannot be executed.',
    'ambiguous_lead' => 'Multiple leads match the given name. Please clarify.',
    'refined' => 'Action proposal refined successfully.',
    'cannot_refine' => 'This action proposal cannot be refined.',
    'refinement_not_recognized' => 'The refinement could not be applied. The proposal was left unchanged.',
    'ambiguity_still_unresolved' => 'The clarification matches multiple candidates. Please be more specific.',
    'ambiguity_narrowed_type' => 'Multiple :type candidates still match: :candidates. Please choose one.',
    'candidate_types' => [
        'company' => 'company',
        'person' => 'person',
    ],
    'mutation_not_allowed' => 'One or more requested changes are not supported for this action type.',
    'ambiguity_resolution_not_supported' => 'This action type does not support ambiguity resolution.',
    'cannot_shift_time_no_current_time' => 'Cannot shift the time because the proposal has no current time.',

    'execution_failure' => [
        'unsupported_intent' => 'This action type cannot be executed.',
        'execution_failed' => 'The action could not be completed. No changes were made.',
    ],

    'capabilities' => [
        'operations' => [
            'replace' => 'Update',
            'clear' => 'Clear',
            'append' => 'Add',
            'remove' => 'Remove',
        ],
        'fields' => [
            'date' => 'Date',
            'time' => 'Time',
            'priority' => 'Priority',
            'lead' => 'Lead',
            'location' => 'Location',
            'participants' => 'Participants',
            'assignee' => 'Assignee',
            'quote' => 'Quote',
        ],
        'refinements' => [
            'replace_field' => 'Update fields',
            'clear_field' => 'Clear fields',
            'append_collection_item' => 'Add items',
            'remove_collection_item' => 'Remove items',
            'replace_collection_item' => 'Replace items',
            'resolve_ambiguity' => 'Resolve ambiguity',
            'contextual_reference' => 'Use contextual references',
        ],
        'intents' => [
            'schedule_meeting' => [
                'refinements' => [
                    'append_collection_item' => 'Add participants',
                    'remove_collection_item' => 'Remove participants',
                    'replace_collection_item' => 'Replace participants',
                ],
            ],
            'schedule_call' => [
                'refinements' => [
                    'append_collection_item' => 'Add participants',
                    'remove_collection_item' => 'Remove participants',
                    'replace_collection_item' => 'Replace participants',
                ],
            ],
        ],
    ],
];
