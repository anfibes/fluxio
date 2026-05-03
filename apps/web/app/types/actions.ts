export type ActionProposalStatus = 'draft' | 'ready' | 'confirmed' | 'executed' | 'failed'

export type EditableFieldSource = 'detected' | 'inferred' | 'guessed' | 'computed' | 'edited' | 'missing'

export interface EditableField {
  key: string
  label: string
  value: unknown
  source: EditableFieldSource
  required: boolean
}

export interface ProposedChange {
  type: string
  label: string
  module: string
  payload: Record<string, unknown>
}

export interface MissingField {
  key: string
  label: string
  required: boolean
  message?: string
}

export interface ActionProposal {
  id: string
  intent: string
  status: ActionProposalStatus
  confidence: number
  source_text: string
  entities: Record<string, unknown>
  missing: MissingField[]
  warnings: string[]
  editable_fields: EditableField[]
  changes: ProposedChange[]
  needs_confirmation: boolean
  confirmed_at: string | null
  executed_at: string | null
  failed_at: string | null
  failure_reason: string | null
  execution_result: Record<string, unknown> | null
}
