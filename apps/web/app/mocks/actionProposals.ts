import type { ActionProposal } from '~/types/actions'

export const mockProposalReady: ActionProposal = {
  id: 'prop_001',
  intent: 'create_task',
  status: 'ready',
  confidence: 0.91,
  source_text: 'Create a follow-up task for Rossini tomorrow at 10am',
  entities: {
    title: 'Follow-up task for Rossini',
    lead: 'Rossini',
    due_at: '2026-05-04T10:00:00Z',
  },
  missing: [],
  warnings: [],
  editable_fields: [
    { key: 'title', label: 'Title', value: 'Follow-up task for Rossini', source: 'detected', required: true },
    { key: 'lead',  label: 'Lead',  value: 'Rossini',                   source: 'detected', required: false },
    { key: 'due_at', label: 'Due at', value: '2026-05-04T10:00:00Z',   source: 'detected', required: false },
  ],
  changes: [
    {
      type: 'create',
      label: 'Create Task',
      module: 'tasks',
      payload: { title: 'Follow-up task for Rossini', lead: 'Rossini', due_at: '2026-05-04T10:00:00Z' },
    },
  ],
  needs_confirmation: true,
  confirmed_at: null,
  executed_at: null,
  failed_at: null,
  failure_reason: null,
  execution_failure: null,
  execution_result: null,
  last_refinement: null,
  ambiguities: [],
}

export const mockProposalDraft: ActionProposal = {
  id: 'prop_002',
  intent: 'create_lead',
  status: 'draft',
  confidence: 0.61,
  source_text: 'Add a new lead from the marketing conference',
  entities: {
    source: 'Marketing Conference',
  },
  missing: [
    { key: 'name', label: 'Full Name', required: true, message: 'Who is the lead? Provide a name to continue.' },
    { key: 'email', label: 'Email Address', required: false },
  ],
  warnings: ['Low confidence — more details needed to complete this proposal.'],
  editable_fields: [
    { key: 'name', label: 'Full Name', value: null, source: 'missing', required: true },
    { key: 'email', label: 'Email Address', value: null, source: 'missing', required: false },
    { key: 'source', label: 'Source', value: 'Marketing Conference', source: 'detected', required: false },
  ],
  changes: [
    {
      type: 'create',
      label: 'Create Lead',
      module: 'leads',
      payload: { source: 'Marketing Conference' },
    },
  ],
  needs_confirmation: true,
  confirmed_at: null,
  executed_at: null,
  failed_at: null,
  failure_reason: null,
  execution_failure: null,
  execution_result: null,
  last_refinement: null,
  ambiguities: [],
}

export const mockProposalExecuted: ActionProposal = {
  id: 'prop_003',
  intent: 'create_task',
  status: 'executed',
  confidence: 0.95,
  source_text: 'Schedule a product demo with TechStart next Monday',
  entities: {
    title: 'Product demo with TechStart',
    due_date: '2026-05-06',
    priority: 'high',
  },
  missing: [],
  warnings: [],
  editable_fields: [
    { key: 'title', label: 'Title', value: 'Product demo with TechStart', source: 'detected', required: true },
    { key: 'due_date', label: 'Due Date', value: '2026-05-06', source: 'detected', required: false },
    { key: 'priority', label: 'Priority', value: 'high', source: 'inferred', required: false },
  ],
  changes: [
    {
      type: 'create',
      label: 'Create Task',
      module: 'tasks',
      payload: { title: 'Product demo with TechStart', due_date: '2026-05-06', priority: 'high' },
    },
  ],
  needs_confirmation: true,
  confirmed_at: '2026-05-03T10:28:00Z',
  executed_at: '2026-05-03T10:28:05Z',
  failed_at: null,
  failure_reason: null,
  execution_failure: null,
  execution_result: {
    summary: 'Task created successfully.',
    details: {
      module: 'tasks',
      action: 'created',
      resource_type: 'task',
      resource_id: 42,
    },
  },
  last_refinement: null,
  ambiguities: [],
}
