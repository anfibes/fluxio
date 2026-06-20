import type { ContextItem } from '~/components/context/ContextList.vue'

interface LeadData {
  id: number
  name: string
  company: string | null
  status: string
}

function leadToContextItem(lead: LeadData): ContextItem {
  return {
    id: lead.id,
    title: lead.name,
    subtitle: lead.company ?? undefined,
    meta: lead.status.charAt(0).toUpperCase() + lead.status.slice(1),
  }
}

export function useLeads() {
  const api = useApi()

  const items = ref<ContextItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetch(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.paginated<LeadData>('/leads', { per_page: 50 })
      if (response.success) {
        items.value = response.data.map(leadToContextItem)
      }
      else {
        error.value = response.message
      }
    }
    catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Failed to load leads'
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
