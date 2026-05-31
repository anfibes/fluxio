export type ActionProposalStatus = 'draft' | 'ready' | 'confirmed' | 'executed' | 'failed'

export interface ProposalCapabilityField {
  key: string
  label: string
}

export interface ProposalMutationCapability {
  operation: string
  operation_label?: string
  fields: ProposalCapabilityField[]
  collection: boolean
}

export type ProposalRefinementCapability =
  | 'replace_field'
  | 'clear_field'
  | 'append_collection_item'
  | 'remove_collection_item'
  | 'replace_collection_item'
  | 'resolve_ambiguity'
  | 'contextual_reference'

export interface ProposalRefinementCapabilityItem {
  key: ProposalRefinementCapability
  label: string
}

export interface ProposalCapabilities {
  supports_contextual_references: boolean
  supports_ambiguity_resolution: boolean
  supports_collection_mutations: boolean
  mutations: ProposalMutationCapability[]
  refinements: ProposalRefinementCapabilityItem[]
}

export interface AmbiguityCandidate {
  id: string | number
  type: string
  label: string
  description?: string
  confidence?: number
}

export interface ProposalAmbiguity {
  key: string
  label: string
  reason: string
  blocking: boolean
  query?: string
  selected_candidate_id: string | number | null
  candidates: AmbiguityCandidate[]
}

export type EditableFieldSource = 'detected' | 'inferred' | 'guessed' | 'computed' | 'edited' | 'missing'

/**
 * Optional, parser-local explainability for a field — explains HOW the value
 * entered the proposal (e.g. a date resolved from "tomorrow", a time inferred
 * from "afternoon"). `confidence` is temporal parse confidence only: it does
 * not imply execution safety and never bypasses validation, readiness,
 * confirmation, or ambiguity handling. `source` carries parser-local
 * provenance (e.g. 'explicit' | 'relative' | 'weekday' | 'day_part').
 */
export interface EditableFieldExplanation {
  source: string
  expression?: string | null
  confidence?: number | null
  message: string
}

export interface EditableField {
  key: string
  label: string
  value: unknown
  source: EditableFieldSource
  required: boolean
  explanation?: EditableFieldExplanation | null
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

export interface ActionProposalRefinementChange {
  field: string
  label: string
  from: unknown
  to: unknown
  /**
   * Descriptive operational meaning of the change (backend Phase 7C–7D), e.g.
   * 'shift_time' | 'replace_time' | 'replace_date' | 'add_participant' |
   * 'remove_participant' | 'replace_participant' | 'unknown'. Optional and kept
   * as a plain string: the backend can add more types without a frontend change.
   */
  semantic_type?: string
  /** Structural collection operation when present (e.g. 'append' | 'remove' | 'replace'). */
  operation?: string
  /** Targeted collection item for a targeted replace/remove. */
  target?: unknown
}

export interface ActionProposalRefinement {
  text: string
  effective_text?: string
  summary: string
  changes: ActionProposalRefinementChange[]
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
  last_refinement: ActionProposalRefinement | null
  ambiguities: ProposalAmbiguity[]
  capabilities?: ProposalCapabilities
}
