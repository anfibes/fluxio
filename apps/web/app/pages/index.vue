<template>
  <div class="dashboard-placeholder">
    <p class="placeholder-label">{{ $t('nav.dashboard') }}</p>
  </div>
</template>

<style scoped>
.dashboard-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 12rem;
}

.placeholder-label {
  font-size: 0.875rem;
  color: var(--color-text-muted);
}
</style>
