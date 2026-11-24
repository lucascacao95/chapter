import { computed, onBeforeUnmount, ref } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError } from '@inertiajs/core'
import type { CollectionPage, LibraryCollection } from '@/types/collections'

export function useCollections() {
  const request = useHttp<Record<string, never>, CollectionPage>({})
  const items = ref<LibraryCollection[]>([])
  const meta = ref<CollectionPage['meta'] | null>(null)
  const loading = ref(false)
  const error = ref('')
  const loadedPages = ref(0)
  let pending: Promise<void> | null = null
  let disposed = false
  const hasMore = computed(() => !meta.value || loadedPages.value < meta.value.last_page)

  function run(reset: boolean): Promise<void> {
    if (disposed) return Promise.resolve()
    if (pending) return pending.then(() => run(reset))
    if (!reset && !hasMore.value) return Promise.resolve()
    pending = (async () => {
      loading.value = true
      error.value = ''
      try {
        const start = reset ? 1 : loadedPages.value + 1
        const end = reset ? Math.max(1, loadedPages.value) : start
        let updated = reset ? [] : [...items.value]
        for (let number = start; number <= end; number++) {
          const response = await request.get(`/api/collections?page=${number}`)
          if (!response) throw new Error('Resposta indisponível')
          updated = [...new Map([...updated, ...response.data].map(item => [item.id, item])).values()]
          meta.value = response.meta
          loadedPages.value = number
          if (number >= response.meta.last_page) break
        }
        items.value = updated
      } catch (failure) {
        if (!(failure instanceof HttpCancelledError)) error.value = 'Não foi possível carregar as coleções. Tente novamente.'
      } finally {
        loading.value = false
        pending = null
      }
    })()
    return pending
  }

  async function ensure(id: number) {
    if (pending) await pending
    if (!meta.value) await run(false)
    while (!disposed && !items.value.some(item => item.id === id) && hasMore.value && !error.value) await run(false)
    return items.value.find(item => item.id === id)
  }

  onBeforeUnmount(() => { disposed = true; request.cancel() })
  return { items, meta, loading, error, hasMore, ensure, loadMore: () => run(false), refresh: () => run(true) }
}
