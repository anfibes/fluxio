<template>
  <div class="app-shell">
    <LayoutAppSidebar />
    <div class="app-main">
      <LayoutAppTopbar />
      <main class="app-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  height: 100dvh;
  overflow: hidden;
  background-color: var(--color-bg);
}

.app-main {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.app-content {
  flex: 1;
  overflow: hidden; /* pages manage their own scroll columns */
}
</style>
