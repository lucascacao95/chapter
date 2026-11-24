import { onBeforeUnmount, ref } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError } from '@inertiajs/core'
import type { RatingGroup } from '@/types/books'

export function useRatingGroups() {
  const request = useHttp<Record<string, never>, { data: RatingGroup[]; meta: { total_books_count: number } }>({})
  const items = ref<RatingGroup[]>([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref('')
  let pending: Promise<void> | null = null
  let disposed = false

  function refresh(): Promise<void> {
    if (disposed) return Promise.resolve()
    if (pending) return pending.then(refresh)
    pending = (async () => {
      loading.value = true
      error.value = ''
      try {
        const response = await request.get('/api/book-rating-groups')
        if (response) { items.value = response.data; total.value = response.meta.total_books_count }
      } catch (failure) {
        if (!(failure instanceof HttpCancelledError)) error.value = 'Não foi possível carregar as classificações. Tente novamente.'
      } finally { loading.value = false; pending = null }
    })()
    return pending
  }
  onBeforeUnmount(() => { disposed = true; request.cancel() })
  return { items, total, loading, error, refresh }
}
