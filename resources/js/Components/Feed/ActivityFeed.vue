<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { LoaderCircle, RefreshCw, Users } from '@lucide/vue'
import { Button } from '@/Components/ui/button'
import FeedCard from './FeedCard.vue'
import type { FeedActivity, FeedPage } from '@/types/feed'
const request = useHttp<Record<string, never>, FeedPage>({})
const activities = ref<FeedActivity[]>([])
const next = ref<string | null>('/api/feed')
const loading = ref(false)
const loaded = ref(false)
const failure = ref('')
const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | undefined
let disposed = false
const hasMore = computed(() => next.value !== null)
async function loadMore() {
  if (loading.value || !next.value || disposed) return
  loading.value = true
  failure.value = ''
  try {
    const url = new URL(next.value, window.location.origin)
    const response = await request.get(url.pathname + url.search)
    if (disposed) return
    if (!response) {
      failure.value = request.hasErrors ? 'A página do feed não está mais disponível. Use Atualizar feed para recomeçar.' : 'Não foi possível carregar as atividades. Tente novamente.'
      return
    }
    activities.value = [...new Map([...activities.value, ...response.data].map(activity => [activity.id, activity])).values()]
    next.value = response.links.next
    loaded.value = true
  } catch (error) {
    if (error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    failure.value = status === 401 || status === 419 ? 'Sua sessão expirou. Entre novamente para continuar.'
      : status === 422 ? 'A página do feed não está mais disponível. Use Atualizar feed para recomeçar.' : 'Não foi possível carregar as atividades. Tente novamente.'
  } finally { loading.value = false }
}
function refresh() { if (!loading.value) { activities.value = []; next.value = '/api/feed'; loaded.value = false; void loadMore() } }
async function removed(id: number) {
  activities.value = activities.value.filter(activity => activity.id !== id)
  await nextTick()
  document.getElementById('feed-title')?.focus()
}
onMounted(() => {
  if (typeof IntersectionObserver !== 'undefined') observer = new IntersectionObserver(entries => { if (entries.some(entry => entry.isIntersecting) && loaded.value && hasMore.value && !failure.value) void loadMore() }, { rootMargin: '200px' })
  if (sentinel.value) observer?.observe(sentinel.value)
  void loadMore()
})
onBeforeUnmount(() => { disposed = true; observer?.disconnect(); request.cancel() })
</script>
<template>
  <section aria-labelledby="feed-title" :aria-busy="loading" class="mt-10 sm:mt-12">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 id="feed-title" tabindex="-1" class="rounded-sm font-serif text-2xl outline-none focus-visible:ring-2 focus-visible:ring-ring">Atualizações</h2><p class="mt-2 text-sm leading-6 text-muted-foreground">Leituras compartilhadas por você e por quem você segue.</p></div><Button variant="outline" size="sm" class="rounded-full border-border" :disabled="loading" @click="refresh"><RefreshCw aria-hidden="true" />Atualizar feed</Button></div>
    <div class="space-y-5"><FeedCard v-for="activity in activities" :key="activity.id" :activity="activity" @removed="removed" /></div>
    <div v-if="loaded && !activities.length && !loading && !failure" class="rounded-2xl border border-dashed border-border bg-white/50 px-5 py-12 text-center"><Users class="mx-auto size-8 text-primary" aria-hidden="true" /><h3 class="mt-4 font-serif text-xl">Seu feed começa com uma leitura</h3><p class="mx-auto mt-3 max-w-md text-sm leading-6 text-muted-foreground">As atividades que você compartilhar e as das pessoas que segue aparecerão aqui. Sua biblioteca permanece privada por padrão.</p></div>
    <div v-if="failure" class="mt-5 rounded-xl border border-border bg-white p-4"><p role="alert" class="text-sm text-destructive">{{ failure }}</p><Button variant="outline" class="mt-3" @click="loadMore">Tentar novamente</Button></div>
    <p v-if="loading" role="status" class="my-6 flex items-center justify-center gap-2 text-sm text-muted-foreground"><LoaderCircle class="size-4 motion-safe:animate-spin" aria-hidden="true" />Carregando atividades…</p>
    <div ref="sentinel" class="h-px" aria-hidden="true" />
    <div v-if="hasMore && loaded && !failure" class="mt-6 text-center"><Button variant="outline" class="rounded-full border-border" :disabled="loading" @click="loadMore">Carregar mais</Button></div>
    <p v-if="loaded && !hasMore && activities.length" role="status" class="mt-8 text-center text-xs text-muted-foreground">Você chegou ao fim das atualizações.</p>
  </section>
</template>
