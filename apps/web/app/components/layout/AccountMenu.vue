<script setup lang="ts">
import type { ThemePreference } from '~/composables/useTheme'

const { user, logout } = useAuth()
const { themePreference, setThemePreference } = useTheme()

const themeOptions: { value: ThemePreference; labelKey: string }[] = [
  { value: 'system', labelKey: 'settings.theme.system' },
  { value: 'light',  labelKey: 'settings.theme.light' },
  { value: 'dark',   labelKey: 'settings.theme.dark' },
]

const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)

// user can be null while authenticated (token cookie survives reloads, user doesn't)
const initials = computed(() => {
  const name = user.value?.name?.trim()
  if (name) {
    const parts = name.split(/\s+/)
    const first = parts[0]?.[0] ?? ''
    const last = parts.length > 1 ? parts[parts.length - 1]?.[0] ?? '' : ''
    return (first + last).toUpperCase()
  }
  return user.value?.email?.[0]?.toUpperCase() ?? ''
})

function close(): void {
  open.value = false
}

function onDocumentPointerDown(e: PointerEvent): void {
  if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
    close()
  }
}

function onDocumentKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') {
    close()
    triggerRef.value?.focus()
  }
}

watch(open, (isOpen) => {
  if (import.meta.server) return
  if (isOpen) {
    document.addEventListener('pointerdown', onDocumentPointerDown)
    document.addEventListener('keydown', onDocumentKeydown)
  }
  else {
    document.removeEventListener('pointerdown', onDocumentPointerDown)
    document.removeEventListener('keydown', onDocumentKeydown)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDown)
  document.removeEventListener('keydown', onDocumentKeydown)
})

async function handleLogout(): Promise<void> {
  close()
  await logout()
}
</script>

<template>
  <div ref="rootRef" class="account">
    <button
      ref="triggerRef"
      type="button"
      class="account-trigger"
      :aria-label="$t('account.label')"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span v-if="initials" aria-hidden="true">{{ initials }}</span>
      <svg v-else aria-hidden="true" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 1.5c-2.9 0-5.25 1.7-5.25 3.8 0 .4.32.7.72.7h9.06c.4 0 .72-.3.72-.7 0-2.1-2.35-3.8-5.25-3.8Z" />
      </svg>
    </button>

    <div v-if="open" class="account-panel" role="menu">
      <div v-if="user" class="account-identity">
        <span class="account-name">{{ user.name }}</span>
        <span class="account-email">{{ user.email }}</span>
      </div>

      <button type="button" class="account-item" role="menuitem" disabled>
        <span>{{ $t('nav.settings') }}</span>
        <span class="account-soon">{{ $t('account.soon') }}</span>
      </button>

      <div class="account-section">
        <span class="account-section-label">{{ $t('account.theme') }}</span>
        <div class="theme-options" role="group" :aria-label="$t('account.theme')">
          <button
            v-for="option in themeOptions"
            :key="option.value"
            type="button"
            class="theme-option"
            :class="{ 'theme-option--active': themePreference === option.value }"
            :aria-pressed="themePreference === option.value"
            @click="setThemePreference(option.value)"
          >
            {{ $t(option.labelKey) }}
          </button>
        </div>
      </div>

      <div class="account-divider" />

      <button type="button" class="account-item" role="menuitem" @click="handleLogout">
        {{ $t('auth.logout') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.account {
  position: relative;
}

.account-trigger {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 9999px;
  border: 1px solid var(--color-border);
  background-color: var(--color-surface-raised);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.account-trigger:hover,
.account-trigger[aria-expanded='true'] {
  border-color: var(--color-border-subtle);
  color: var(--color-text);
}

.account-panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: 13.5rem;
  padding: 0.375rem;
  border-radius: 0.625rem;
  border: 1px solid var(--color-border);
  background-color: var(--color-surface);
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.18);
}

.account-identity {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.5rem 0.625rem 0.625rem;
  border-bottom: 1px solid var(--color-border);
  margin-bottom: 0.375rem;
  min-width: 0;
}

.account-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-email {
  font-size: 0.6875rem;
  color: var(--color-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  text-align: left;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.account-item:hover:not(:disabled) {
  background-color: var(--color-surface-raised);
  color: var(--color-text);
}

.account-item:disabled {
  color: var(--color-muted);
  cursor: default;
  opacity: 0.7;
}

.account-soon {
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-muted);
  border: 1px solid var(--color-border);
  border-radius: 0.25rem;
  padding: 0.0625rem 0.3125rem;
}

.account-section {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.5rem 0.625rem;
}

.account-section-label {
  font-size: 0.6875rem;
  color: var(--color-muted);
}

.theme-options {
  display: flex;
  gap: 1px;
  padding: 0.125rem;
  border-radius: 0.375rem;
  border: 1px solid var(--color-border);
}

.theme-option {
  flex: 1;
  padding: 0.25rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.6875rem;
  color: var(--color-muted);
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.theme-option:hover {
  color: var(--color-text-muted);
}

.theme-option--active {
  background-color: var(--color-surface-raised);
  color: var(--color-text-muted);
}

.account-divider {
  height: 1px;
  margin: 0.375rem 0;
  background-color: var(--color-border);
}
</style>
