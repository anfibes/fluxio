const MAX = 5

export function useCommandHistory() {
  const history = ref<string[]>([])

  function push(text: string) {
    const trimmed = text.trim()
    if (!trimmed) return
    history.value = [trimmed, ...history.value.filter(h => h !== trimmed)].slice(0, MAX)
  }

  return { history: readonly(history), push }
}
