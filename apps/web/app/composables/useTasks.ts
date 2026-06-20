import type { ContextItem } from '~/components/context/ContextList.vue'

interface TaskData {
  id: number
  title: string
  description: string | null
  status: string
  priority: string
  due_at: string | null
  lead_id: number | null
}

function taskToContextItem(task: TaskData): ContextItem {
  return {
    id: task.id,
    title: task.title,
    subtitle: task.priority.charAt(0).toUpperCase() + task.priority.slice(1),
    meta: task.due_at
      ? new Date(task.due_at).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
      : task.status.charAt(0).toUpperCase() + task.status.slice(1),
  }
}

export function useTasks() {
  const api = useApi()

  const items = ref<ContextItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetch(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.paginated<TaskData>('/tasks', { per_page: 50 })
      if (response.success) {
        items.value = response.data.map(taskToContextItem)
      }
      else {
        error.value = response.message
      }
    }
    catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Failed to load tasks'
    }
    finally {
      loading.value = false
    }
  }

  fetch()

  return {
    items: readonly(items),
    loading: readonly(loading),
    error: readonly(error),
    refresh: fetch,
  }
}
