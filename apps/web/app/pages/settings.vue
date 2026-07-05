<script setup lang="ts">
import type { ThemePreference } from '~/composables/useTheme'

const { isAuthenticated } = useAuth()
const { themePreference, setThemePreference } = useTheme()

const themeOptions: { value: ThemePreference; labelKey: string }[] = [
  { value: 'system', labelKey: 'settings.theme.system' },
  { value: 'light',  labelKey: 'settings.theme.light' },
  { value: 'dark',   labelKey: 'settings.theme.dark' },
]
</script>

<template>
  <!-- ── Auth gate ────────────────────────────────────────── -->
  <div v-if="!isAuthenticated" class="flex h-full items-center justify-center bg-bg">
    <AuthLoginPanel />
  </div>

  <!-- ── Settings ─────────────────────────────────────────── -->
  <div v-else class="h-full overflow-y-auto">
    <div class="mx-auto flex max-w-3xl flex-col gap-3 px-4 py-4 lg:px-6 lg:py-6">
      <header class="flex flex-col gap-0.5">
        <h1 class="text-sm font-semibold text-text">
          {{ $t('nav.settings') }}
        </h1>
        <p class="text-xs text-muted">
          {{ $t('settings.subtitle') }}
        </p>
      </header>

      <section class="rounded-xl border border-border bg-surface p-4">
        <h2 class="text-xs font-medium uppercase tracking-wide text-muted">
          {{ $t('settings.appearance') }}
        </h2>

        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-col gap-0.5">
            <span class="text-sm text-text">{{ $t('settings.theme.title') }}</span>
            <span class="text-xs text-muted">{{ $t('settings.theme.description') }}</span>
          </div>

          <div
            class="flex shrink-0 gap-px self-start rounded-lg border border-border p-0.5 sm:self-auto"
            role="group"
            :aria-label="$t('settings.theme.title')"
          >
            <button
              v-for="option in themeOptions"
              :key="option.value"
              type="button"
              class="rounded-md px-3 py-1.5 text-xs transition-colors"
              :class="themePreference === option.value
                ? 'bg-surface-raised text-text'
                : 'text-muted hover:text-text-muted'"
              :aria-pressed="themePreference === option.value"
              @click="setThemePreference(option.value)"
            >
              {{ $t(option.labelKey) }}
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
