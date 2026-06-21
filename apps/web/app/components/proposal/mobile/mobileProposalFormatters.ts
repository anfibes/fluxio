/**
 * Shared formatting helpers for the mobile proposal experience.
 *
 * These are pure functions with no reactive dependencies.
 * Keep this file small — only add helpers actually shared by
 * at least two mobile components.
 */

/**
 * Safe value-to-string formatter with recursive array support.
 *
 * - null / undefined / '' → '—'
 * - array → recursively format each item, comma-join
 * - object → JSON.stringify (guarded by try/catch)
 * - scalar → String(value)
 */
export function formatFieldValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (Array.isArray(value)) {
    return value.length
      ? value.map(item => formatFieldValue(item)).join(', ')
      : '—'
  }
  if (typeof value === 'object') {
    try { return JSON.stringify(value) } catch { return '—' }
  }
  return String(value)
}
