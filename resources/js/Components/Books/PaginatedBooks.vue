<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { ArrowLeft, ArrowRight, BookOpen, Plus, RefreshCw } from '@lucide/vue'
import { Button } from '@/Components/ui/button'
import { Skeleton } from '@/Components/ui/skeleton'
import BookCard from './BookCard.vue'
import type { Book, BookPage } from '@/types/books'

const props = withDefaults(defineProps<{ filter?: string; ratingBand?: string; active: boolean; page: number; revision: number }>(), { filter: undefined })
const emit = defineEmits<{ page: [number: number]; organize: [book: Book]; rate: [book: Book]; create: [filter?: string] }>()
const query = useHttp<Record<string, never>, BookPage>({})
const failure = ref('')
const loading = ref(false)
const loadedRevision = ref(-1)
const result = computed(() => query.response)
const isCollection = computed(() => props.filter !== undefined)
const isRating = computed(() => props.ratingBand !== undefined)
let poll: ReturnType<typeof setTimeout> | undefined
let sequence = 0
let pending = Promise.resolve()

function load(silent = false) {
  const current = ++sequence
  query.cancel()
  pending = pending.then(() => performLoad(current, silent))
  return pending
}

async function performLoad(current: number, silent: boolean) {
  if (current !== sequence) return
  loading.value = !silent
  if (!silent) failure.value = ''
  const revision = props.revision
  let target = props.page
  try {
    const url = (number: number) => `/api/books?page=${number}${props.filter === undefined ? '' : `&collection_id=${encodeURIComponent(props.filter)}`}${props.ratingBand === undefined ? '' : `&rating_band=${encodeURIComponent(props.ratingBand)}`}`
    let response = await query.get(url(target))
    if (current !== sequence || !response) return
    if (target > response.meta.last_page) {
      target = response.meta.last_page
      response = await query.get(url(target))
      if (current !== sequence || !response) return
    }
    loadedRevision.value = revision
    if (target !== props.page) emit('page', target)
  } catch (error) {
    if (current !== sequence || error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    if (silent && status !== 401 && status !== 419) return
    failure.value = status === 401 || status === 419 ? 'Sua sessão expirou. Entre novamente para continuar.'
      : status === 422 ? 'Este grupo não está disponível. Escolha outro grupo.'
      : 'Não foi possível carregar seus livros. Verifique sua conexão e tente novamente.'
  } finally {
    if (current === sequence) { loading.value = false; schedulePoll() }
  }
}

watch(() => [props.active, props.page, props.revision], () => {
  if (props.active && (!result.value || result.value.meta.current_page !== props.page || loadedRevision.value !== props.revision)) void load()
}, { immediate: true })
function schedulePoll() {
  clearTimeout(poll)
  if (props.active && !failure.value && result.value?.data.some(book => ['pending', 'processing'].includes(book.file?.processing_status ?? '') || ['queued', 'processing'].includes(book.kindle?.delivery?.status ?? '') || book.feed_activity?.status === 'pending' || book.latest_rating?.feed_activity?.status === 'pending')) {
    poll = setTimeout(() => { if (props.active) void load(true) }, 3000)
  }
}
watch(() => props.active, schedulePoll)
onBeforeUnmount(() => { clearTimeout(poll); sequence++; query.cancel() })
</script>

<template>
  <section :aria-busy="loading" aria-label="Livros do grupo">
    <div v-if="loading" role="status">
      <p class="mb-5 text-xs text-muted-foreground">Carregando sua biblioteca…</p>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" aria-hidden="true">
        <div v-for="item in 5" :key="item" class="space-y-3"><Skeleton class="aspect-[2/3] w-full rounded-xl" /><Skeleton class="h-4 w-4/5 rounded" /></div>
      </div>
    </div>
    <div v-else-if="failure" class="rounded-2xl border border-border bg-white p-5">
      <p role="alert" class="text-sm text-muted-foreground">{{ failure }}</p>
      <Button variant="outline" class="mt-4 rounded-full border-border" @click="load()"><RefreshCw aria-hidden="true" />Tentar novamente</Button>
    </div>
    <template v-else-if="result">
      <p class="mb-5 text-xs font-medium text-muted-foreground">{{ result.meta.total }} {{ result.meta.total === 1 ? 'livro' : 'livros' }} {{ isCollection || isRating ? 'neste grupo' : 'na sua biblioteca' }}</p>
      <div v-if="result.data.length" class="grid grid-cols-2 items-start gap-x-4 gap-y-7 sm:grid-cols-3 sm:gap-x-5 lg:grid-cols-4 xl:grid-cols-5">
        <BookCard v-for="book in result.data" :key="book.id" :book="book" @organize="emit('organize', $event)" @rate="emit('rate', $event)" @kindle-queued="load(true)" />
      </div>
      <div v-else class="flex flex-col items-center rounded-3xl border border-dashed border-[#cdd2c5] bg-white/60 px-5 py-12 text-center">
        <div class="grid size-12 place-items-center rounded-2xl bg-[#e7eddf] text-primary"><BookOpen class="size-5" aria-hidden="true" /></div>
        <h2 class="mt-5 font-serif text-2xl">{{ isCollection || isRating ? 'Nenhum livro neste grupo' : 'Sua próxima leitura começa aqui' }}</h2>
        <p class="mt-3 max-w-sm text-sm leading-6 text-muted-foreground">{{ isRating ? 'Os livros aparecem aqui conforme a avaliação mais recente. Avalie um livro pelo menu Ações.' : isCollection ? 'Adicione um livro aqui ou use Ações → Organizar nos cartões de Todos os livros para mudar a coleção.' : 'Envie seu primeiro EPUB ou PDF e comece a montar sua biblioteca.' }}</p>
        <Button class="mt-6 rounded-full" @click="emit('create', filter)"><Plus aria-hidden="true" />{{ isCollection ? 'Adicionar livro neste grupo' : 'Adicionar primeiro livro' }}</Button>
      </div>
      <nav v-if="result.meta.last_page > 1" aria-label="Paginação dos livros" class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-border pt-5">
        <p class="text-xs text-muted-foreground" aria-live="polite">Página {{ result.meta.current_page }} de {{ result.meta.last_page }}</p>
        <div class="flex gap-2"><Button variant="outline" class="rounded-full border-border" :disabled="loading || result.meta.current_page <= 1" @click="emit('page', result.meta.current_page - 1)"><ArrowLeft aria-hidden="true" />Anterior</Button><Button variant="outline" class="rounded-full border-border" :disabled="loading || result.meta.current_page >= result.meta.last_page" @click="emit('page', result.meta.current_page + 1)">Próxima<ArrowRight aria-hidden="true" /></Button></div>
      </nav>
    </template>
  </section>
</template>
