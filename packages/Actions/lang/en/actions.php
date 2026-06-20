<?php

return [
    'interpreted' => 'Command interpreted successfully.',
    'confirmed' => 'Action proposal confirmed successfully.',
    'cannot_confirm' => 'This action proposal cannot be confirmed.',
    'executed' => 'Action executed successfully.',
    'cannot_execute' => 'This action proposal cannot be executed.',
    'ambiguous_lead' => 'Multiple leads match the given name. Please clarify.',
    'unknown_lead' => 'No lead matches the given name.',
    'ambiguous_assignee' => 'Multiple users match the given name. Please clarify.',
    'unknown_assignee' => 'No user matches the given assignee name.',
    'ambiguous_task' => 'Multiple tasks match the given title. Please clarify.',
    'unknown_task' => 'No task matches the given title.',
    'invalid_task_status' => 'The target status is not a valid task status.',
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

    // Canonical command phrases (Intent Narration — slice 1).
    // Placeholders MUST be requirement keys of the matching IntentDefinition.
    // Values are filled deterministically from resolved proposal state; an
    // incomplete proposal yields no phrase (the projection returns null).
    'narration' => [
        'create_task' => [
            'canonical' => 'Create a task for :lead.',
        ],
        'schedule_call' => [
            'canonical' => 'Schedule a call with :lead on :date at :time.',
        ],
        'schedule_meeting' => [
            'canonical' => 'Schedule a meeting with :lead on :date at :time.',
        ],
        'assign_lead' => [
            'canonical' => 'Assign :lead to :assignee.',
        ],
        'prepare_contract_from_quote' => [
            'canonical' => 'Prepare a contract for :lead from quote :quote.',
        ],
        'update_task_status' => [
            'canonical' => 'Mark task :task as :state.',
        ],
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
            'task' => 'Task',
            'state' => 'Status',
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
