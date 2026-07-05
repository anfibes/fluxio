<script setup lang="ts">
const { isAuthenticated } = useAuth()
const { items, loading, error, refresh } = useTasks()

// The composable fetches on init; if that happened while logged out
// (direct URL hit), refetch once auth lands instead of showing a stale 401.
watch(isAuthenticated, (authed) => {
  if (authed) void refresh()
})
</script>

<template>
  <!-- ── Auth gate ────────────────────────────────────────── -->
  <div v-if="!isAuthenticated" class="flex h-full items-center justify-center bg-bg">
    <AuthLoginPanel />
  </div>

  <!-- ── Tasks section ────────────────────────────────────── -->
  <SectionsOperationalSectionPage
    v-else
    :title="$t('nav.tasks')"
    :subtitle="$t('tasks.subtitle')"
    :items="items"
    :loading="loading"
    :error="error"
    :empty-label="$t('tasks.empty')"
    @retry="refresh"
  />
</template>
