<template>
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="brand-name">Fluxio</span>
    </div>
    <nav class="sidebar-nav">
      <!-- nav items will go here -->
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  width: 14rem;
  background-color: var(--color-surface);
  border-right: 1px solid var(--color-border);
  height: 100dvh;
  flex-shrink: 0;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  height: 3.5rem;
  padding: 0 1.25rem;
  border-bottom: 1px solid var(--color-border);
}

.brand-name {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text);
  letter-spacing: -0.01em;
}

.sidebar-nav {
  flex: 1;
  padding: 0.75rem 0;
  overflow-y: auto;
}
</style>
