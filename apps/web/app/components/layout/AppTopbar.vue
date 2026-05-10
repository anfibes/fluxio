<script setup lang="ts">
import type { ThemePreference } from '~/composables/useTheme'

const { user, isAuthenticated, logout } = useAuth()
const { themePreference, setThemePreference } = useTheme()

const themeOptions: { value: ThemePreference; labelKey: string }[] = [
  { value: 'system', labelKey: 'settings.theme.system' },
  { value: 'light',  labelKey: 'settings.theme.light' },
  { value: 'dark',   labelKey: 'settings.theme.dark' },
]
</script>

<template>
  <header class="topbar">
    <div class="topbar-left">
      <slot name="title" />
    </div>
    <div class="topbar-right">
      <!-- Theme control -->
      <div class="flex items-center gap-px rounded-md border border-[var(--color-border)] p-0.5">
        <button
          v-for="option in themeOptions"
          :key="option.value"
          type="button"
          class="rounded px-2 py-1 text-xs transition-colors"
          :class="themePreference === option.value
            ? 'bg-[var(--color-surface-raised)] text-[var(--color-text-muted)]'
            : 'text-[var(--color-muted)] hover:text-[var(--color-text-muted)]'"
          @click="setThemePreference(option.value)"
        >
          {{ $t(option.labelKey) }}
        </button>
      </div>

      <template v-if="isAuthenticated">
        <!-- Hide email on small screens to avoid overflow -->
        <span v-if="user" class="hidden text-xs text-[var(--color-text-muted)] md:inline">{{ user.email }}</span>
        <button
          type="button"
          class="rounded-md border border-[var(--color-border)] px-3 py-1.5 text-xs text-[var(--color-muted)] transition-colors hover:border-[var(--color-border-subtle)] hover:text-[var(--color-text-muted)]"
          @click="logout"
        >
          {{ $t('auth.logout') }}
        </button>
      </template>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 3.5rem;
  padding: 0 1.5rem;
  background-color: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}

.topbar-left,
.topbar-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

@media (max-width: 767px) {
  .topbar {
    padding: 0 0.875rem;
  }

  .topbar-right {
    gap: 0.5rem;
  }
}
</style>
