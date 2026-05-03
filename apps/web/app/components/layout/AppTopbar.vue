<script setup lang="ts">
const { user, isAuthenticated, logout } = useAuth()
</script>

<template>
  <header class="topbar">
    <div class="topbar-left">
      <slot name="title" />
    </div>
    <div class="topbar-right">
      <template v-if="isAuthenticated">
        <span v-if="user" class="text-xs text-[var(--color-text-muted)]">{{ user.email }}</span>
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
</style>
