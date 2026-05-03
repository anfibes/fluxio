<script setup lang="ts">
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const { login } = useAuth()

async function handleSubmit() {
  if (!email.value || !password.value) return
  loading.value = true
  error.value = null
  try {
    await login(email.value, password.value)
  }
  catch (err: unknown) {
    error.value = err instanceof Error ? err.message : 'Login failed'
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-sm rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-8">
    <div class="mb-8 flex flex-col gap-1">
      <h1 class="text-lg font-semibold tracking-tight text-[var(--color-text)]">Fluxio</h1>
      <p class="text-sm text-[var(--color-muted)]">{{ $t('auth.sign_in_to_continue') }}</p>
    </div>

    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-medium text-[var(--color-text-muted)]">
          {{ $t('auth.email') }}
        </label>
        <input
          v-model="email"
          type="email"
          autocomplete="email"
          :disabled="loading"
          class="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-raised)] px-3 py-2 text-sm text-[var(--color-text)] outline-none placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] disabled:opacity-60"
        >
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-medium text-[var(--color-text-muted)]">
          {{ $t('auth.password') }}
        </label>
        <input
          v-model="password"
          type="password"
          autocomplete="current-password"
          :disabled="loading"
          class="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-raised)] px-3 py-2 text-sm text-[var(--color-text)] outline-none placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] disabled:opacity-60"
        >
      </div>

      <div
        v-if="error"
        class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-400"
      >
        {{ error }}
      </div>

      <button
        type="submit"
        :disabled="loading || !email || !password"
        class="mt-2 w-full rounded-lg py-2.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
        :class="(!loading && email && password)
          ? 'bg-[var(--color-accent)] text-white hover:bg-[var(--color-accent-hover)]'
          : 'bg-[var(--color-surface-raised)] text-[var(--color-muted)]'"
      >
        {{ loading ? $t('auth.logging_in') : $t('auth.login') }}
      </button>
    </form>
  </div>
</template>
