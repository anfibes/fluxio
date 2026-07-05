<script setup lang="ts">
const { isAuthenticated } = useAuth()
</script>

<template>
  <div class="app-shell">
    <LayoutAppSidebar />
    <div class="app-main">
      <LayoutAppTopbar />
      <main class="app-content">
        <slot />
      </main>
      <!-- Takes real layout space (not an overlay), so the proposal
           workspace's sticky CTA lands directly above it. -->
      <LayoutAppMobilePrimaryNav v-if="isAuthenticated" />
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
