<script setup lang="ts">
import { primaryNavigation } from '~/components/layout/primaryNavigation'

const route = useRoute()
</script>

<template>
  <nav class="mobile-nav" :aria-label="$t('nav.primary')">
    <NuxtLink
      v-for="item in primaryNavigation"
      :key="item.to"
      :to="item.to"
      class="mobile-nav-item"
      :class="{ 'mobile-nav-item--active': route.path === item.to }"
      :aria-current="route.path === item.to ? 'page' : undefined"
    >
      <span class="mobile-nav-icon" aria-hidden="true">{{ item.icon }}</span>
      <span class="mobile-nav-label">{{ $t(item.mobileLabelKey ?? item.labelKey) }}</span>
    </NuxtLink>
  </nav>
</template>

<style scoped>
.mobile-nav {
  display: flex;
  align-items: stretch;
  justify-content: space-around;
  flex-shrink: 0;
  background-color: var(--color-surface);
  border-top: 1px solid var(--color-border);
  padding-bottom: env(safe-area-inset-bottom);
}

@media (min-width: 1024px) {
  .mobile-nav {
    display: none;
  }
}

.mobile-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.125rem;
  flex: 1;
  max-width: 8rem;
  padding: 0.375rem 0.75rem 0.4375rem;
  color: var(--color-muted);
  text-decoration: none;
  transition: color 0.15s ease;
}

.mobile-nav-item--active {
  color: var(--color-accent);
}

.mobile-nav-icon {
  font-size: 1rem;
  line-height: 1.25rem;
}

.mobile-nav-label {
  font-size: 0.625rem;
  font-weight: 500;
  letter-spacing: 0.01em;
}
</style>
