export type ThemePreference = 'system' | 'light' | 'dark'
export type ResolvedTheme = 'light' | 'dark'

const STORAGE_KEY = 'fluxio.theme'

export function useTheme() {
  // useState ensures shared state across all composable calls within the same Nuxt instance.
  const themePreference = useState<ThemePreference>('fluxio.theme-preference', () => 'system')
  const resolvedTheme = useState<ResolvedTheme>('fluxio.theme-resolved', () => 'dark')

  function getSystemTheme(): ResolvedTheme {
    if (import.meta.server) return 'dark'
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }

  function resolveTheme(preference: ThemePreference): ResolvedTheme {
    return preference === 'system' ? getSystemTheme() : preference
  }

  function applyTheme(theme: ResolvedTheme): void {
    if (import.meta.server) return
    document.documentElement.dataset.theme = theme
    document.documentElement.style.colorScheme = theme
  }

  function setThemePreference(preference: ThemePreference): void {
    themePreference.value = preference
    if (import.meta.client) {
      localStorage.setItem(STORAGE_KEY, preference)
    }
    const resolved = resolveTheme(preference)
    resolvedTheme.value = resolved
    applyTheme(resolved)
  }

  function toggleTheme(): void {
    setThemePreference(resolvedTheme.value === 'dark' ? 'light' : 'dark')
  }

  function initializeTheme(): void {
    if (import.meta.server) return

    const stored = localStorage.getItem(STORAGE_KEY) as ThemePreference | null
    const preference: ThemePreference = stored ?? 'system'

    themePreference.value = preference
    const resolved = resolveTheme(preference)
    resolvedTheme.value = resolved
    applyTheme(resolved)

    // Track system preference changes when preference is 'system'.
    // Guard inside handler prevents stale listener side-effects.
    if (preference === 'system') {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (themePreference.value === 'system') {
          const r: ResolvedTheme = e.matches ? 'dark' : 'light'
          resolvedTheme.value = r
          applyTheme(r)
        }
      })
    }
  }

  return {
    themePreference: readonly(themePreference),
    resolvedTheme: readonly(resolvedTheme),
    setThemePreference,
    toggleTheme,
    initializeTheme,
  }
}
