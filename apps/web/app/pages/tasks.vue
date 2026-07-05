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

  <!-- ── Tasks list ───────────────────────────────────────── -->
  <div v-else class="h-full overflow-y-auto">
    <div class="mx-auto flex max-w-3xl flex-col gap-3 px-4 py-4 lg:px-6 lg:py-6">
      <h1 class="text-sm font-semibold text-text">
        {{ $t('nav.tasks') }}
      </h1>

      <div class="rounded-xl border border-border bg-surface px-4 py-1">
        <div v-if="loading" class="py-6 text-center text-xs text-muted">
          {{ $t('common.loading') }}
        </div>

        <div v-else-if="error" class="flex flex-col items-center gap-2 py-4">
          <p class="text-xs text-red-400">{{ error }}</p>
          <button
            type="button"
            class="rounded border border-border px-2 py-1 text-xs text-muted transition-colors hover:text-text-muted"
            @click="refresh"
          >
            {{ $t('common.retry') }}
          </button>
        </div>

        <ContextList v-else :items="[...items]" :empty-label="$t('tasks.empty')" />
      </div>
    </div>
  </div>
</template>
