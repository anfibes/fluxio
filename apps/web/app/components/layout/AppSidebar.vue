<template>
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="brand-mark">F</span>
      <span class="brand-name">Fluxio</span>
    </div>
    <nav class="sidebar-nav">
      <a class="nav-item nav-item--active" href="#">
        <span class="nav-icon">⚡</span>
        <span>{{ $t('nav.actions') }}</span>
      </a>
      <a class="nav-item" href="#">
        <span class="nav-icon">◎</span>
        <span>{{ $t('nav.leads') }}</span>
      </a>
      <a class="nav-item" href="#">
        <span class="nav-icon">✓</span>
        <span>{{ $t('nav.tasks') }}</span>
      </a>
      <a class="nav-item" href="#">
        <span class="nav-icon">⊞</span>
        <span>{{ $t('nav.calendar') }}</span>
      </a>
    </nav>
    <div class="sidebar-footer">
      <a class="nav-item" href="#">
        <span class="nav-icon">⊙</span>
        <span>{{ $t('nav.settings') }}</span>
      </a>
    </div>
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

@media (max-width: 1023px) {
  .sidebar {
    display: none;
  }
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  height: 3.5rem;
  padding: 0 1.125rem;
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}

.brand-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.625rem;
  height: 1.625rem;
  border-radius: 0.375rem;
  background: var(--color-accent);
  color: white;
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  flex-shrink: 0;
}

.brand-name {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
  letter-spacing: -0.015em;
}

.sidebar-nav {
  flex: 1;
  padding: 0.5rem 0.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.sidebar-footer {
  padding: 0.5rem;
  border-top: 1px solid var(--color-border);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--color-muted);
  text-decoration: none;
  transition: background-color 0.15s ease, color 0.15s ease;
  cursor: pointer;
}

.nav-item:hover {
  background-color: var(--color-surface-raised);
  color: var(--color-text-muted);
}

.nav-item--active {
  background-color: rgb(99 102 241 / 0.12);
  color: var(--color-accent);
}

.nav-icon {
  font-size: 0.875rem;
  width: 1.125rem;
  text-align: center;
  flex-shrink: 0;
  opacity: 0.8;
}
</style>
