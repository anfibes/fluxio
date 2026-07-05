// Single source of truth for the app's primary navigation. The desktop
// sidebar and the mobile bottom nav are two projections of this list.
//
// Only sections with a real route belong here — no placeholders. Calendar
// joins once it has a page; Settings lives in the Account menu, never here.
export interface PrimaryNavItem {
  to: string
  icon: string
  labelKey: string
  /** Mobile projection may use a different label (e.g. Proposal vs Actions). */
  mobileLabelKey?: string
}

export const primaryNavigation: PrimaryNavItem[] = [
  { to: '/', icon: '⚡', labelKey: 'nav.actions', mobileLabelKey: 'nav.proposal' },
  { to: '/leads', icon: '◎', labelKey: 'nav.leads' },
  { to: '/tasks', icon: '✓', labelKey: 'nav.tasks' },
]
