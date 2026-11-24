<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Check, Folder, FolderPlus, Plus, Star } from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PaginatedBooks from '@/Components/Books/PaginatedBooks.vue'
import RateBookDialog from '@/Components/Books/RateBookDialog.vue'
import OrganizeBookDialog from '@/Components/Books/OrganizeBookDialog.vue'
import AddBookDialog from '@/Components/Books/AddBookDialog.vue'
import CreateCollectionDialog from '@/Components/Collections/CreateCollectionDialog.vue'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion'
import { Button } from '@/Components/ui/button'
import { useRatingGroups } from '@/composables/useRatingGroups'
import { useCollections } from '@/composables/useCollections'
import { sharingLabel } from '@/types/feed'
import type { Book } from '@/types/books'
import type { LibraryCollection } from '@/types/collections'

const page = usePage()
const catalog = useCollections()
const ratings = useRatingGroups()
const { items: ratingGroups, total: ratingTotal, loading: ratingsLoading, error: ratingsError } = ratings
const { items, meta, loading, error, hasMore } = catalog
const params = computed(() => new URL(page.url, 'http://localhost').searchParams)
type LibraryMode = 'all' | 'collections' | 'ratings'
const mode = computed<LibraryMode>(() => { const view = params.value.get('view'); return view === 'collections' || view === 'ratings' ? view : 'all' })
const ratingBand = computed(() => { const band = params.value.get('rating_band'); return band && ['0', '1', '2', '3', '4', '5', 'none'].includes(band) ? band : 'none' })
const lastRatingBand = ref('none')
const group = computed(() => params.value.get('collection') || 'none')
const pages = reactive<Record<string, number>>({ all: 1, none: 1 })
const visited = reactive(new Set<string>())
const lastGroup = ref('none')
const revision = ref(0)
const createOpen = ref(false)
const addOpen = ref(false)
const addCollection = ref<number | null>(null)
const organizing = ref<Book | null>(null)
const ratingBook = ref<Book | null>(null)
const notice = ref('')
const navigating = ref(false)
const ready = ref(false)
const resolving = ref(false)
const success = computed(() => notice.value || (typeof page.flash?.success === 'string' ? page.flash.success : ''))
const bookCount = (count: number) => `${count} ${count === 1 ? 'livro' : 'livros'}`
const unavailable = computed(() => ready.value && !loading.value && !resolving.value && !error.value && group.value !== 'none' && !items.value.some(item => String(item.id) === group.value))

watch(() => page.url, () => {
  const key = mode.value === 'all' ? 'all' : mode.value === 'ratings' ? `rating:${ratingBand.value}` : group.value
  const number = Number(params.value.get('page'))
  pages[key] = Number.isSafeInteger(number) && number > 0 ? number : 1
  visited.add(key)
  if (mode.value === 'collections') lastGroup.value = group.value
  if (mode.value === 'ratings') lastRatingBand.value = ratingBand.value
}, { immediate: true })

watch([mode, group, ready], async () => {
  if (!ready.value || mode.value !== 'collections' || group.value === 'none') return
  const id = Number(group.value)
  if (!Number.isSafeInteger(id) || id <= 0) return
  resolving.value = true
  try { await catalog.ensure(id) } finally { resolving.value = false }
}, { immediate: true })

function navigate(view: LibraryMode, selected = lastGroup.value, number = 1) {
  if (navigating.value) return
  const query: Record<string, string | number> = {}
  if (view === 'collections') { query.view = view; query.collection = selected }
  if (view === 'ratings') { query.view = view; query.rating_band = selected }
  if (number > 1) query.page = number
  router.get('/books', query, {
    preserveState: true, preserveScroll: true,
    onStart: () => { navigating.value = true },
    onFinish: () => { navigating.value = false },
  })
}
function changePage(key: string, number: number) {
  if (key.startsWith('rating:')) navigate('ratings', key.slice(7), number)
  else navigate(key === 'all' ? 'all' : 'collections', key, number)
}
function openGroup(value: string | string[] | undefined) {
  if (typeof value === 'string') navigate('collections', value, pages[value] || 1)
}
function openRatingGroup(value: string | string[] | undefined) {
  if (typeof value === 'string') navigate('ratings', value, pages[`rating:${value}`] || 1)
}
function rated(shared = false) {
  notice.value = shared ? 'Avaliação salva. O compartilhamento está aguardando processamento ou publicação.' : 'Biblioteca atualizada. Confira sua avaliação no histórico.'
  revision.value++
  void ratings.refresh()
}
async function created(collection: LibraryCollection) {
  notice.value = 'Coleção criada com sucesso.'
  await catalog.refresh()
  navigate('collections', String(collection.id), 1)
}
function addBook(filter?: string) {
  addCollection.value = filter && filter !== 'none' ? Number(filter) : null
  addOpen.value = true
}
function bookAdded(book: Book) {
  void ratings.refresh()
  notice.value = book.file ? 'Arquivo recebido. Os detalhes serão extraídos em segundo plano.' : 'Livro cadastrado com sucesso.'
  if (book.feed_activity) notice.value += ` ${sharingLabel(book.feed_activity)}`
  revision.value++
  void catalog.refresh()
  navigate(book.collection_id === null ? 'all' : 'collections', String(book.collection_id ?? 'all'), 1)
}
function moved() {
  organizing.value = null
  notice.value = 'Organização do livro atualizada.'
  revision.value++
  void catalog.refresh()
}
onMounted(async () => { await Promise.all([catalog.refresh(), ratings.refresh()]); ready.value = true })
</script>

<template>
  <AppLayout title="Meus livros">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
      <div><p class="text-[11px] font-semibold uppercase tracking-[.18em] text-[#58745b]">Seu espaço de leitura</p><h1 class="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">Meus livros</h1><p class="mt-3 text-sm leading-6 text-muted-foreground">Suas leituras, reunidas em um lugar só.</p></div>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="lg" class="rounded-full border-border" @click="createOpen = true"><FolderPlus aria-hidden="true" />Nova coleção</Button>
        <Button size="lg" class="rounded-full" @click="addBook(mode === 'collections' ? group : undefined)"><Plus aria-hidden="true" />Adicionar livro</Button>
      </div>
    </div>
    <div v-if="success" role="status" class="mt-7 flex items-center gap-3 rounded-xl border border-[#cbdcc9] bg-[#edf4e9] p-4 text-sm text-primary"><Check class="size-4 shrink-0" aria-hidden="true" />{{ success }}</div>
    <div role="group" aria-label="Visualização da biblioteca" class="mb-7 mt-9 flex flex-wrap gap-2 border-b border-border pb-5">
      <Button :variant="mode === 'all' ? 'default' : 'outline'" :aria-pressed="mode === 'all'" :disabled="navigating" class="rounded-full border-border" @click="navigate('all', 'all', pages.all || 1)">Todos os livros</Button>
      <Button :variant="mode === 'collections' ? 'default' : 'outline'" :aria-pressed="mode === 'collections'" :disabled="navigating" class="rounded-full border-border" @click="navigate('collections', lastGroup, pages[lastGroup] || 1)">Por coleção</Button>
      <Button :variant="mode === 'ratings' ? 'default' : 'outline'" :aria-pressed="mode === 'ratings'" :disabled="navigating" class="rounded-full border-border" @click="navigate('ratings', lastRatingBand, pages[`rating:${lastRatingBand}`] || 1)">Por classificação</Button>
    </div>
    <PaginatedBooks v-if="visited.has('all')" v-show="mode === 'all'" :active="mode === 'all'" :page="pages.all || 1" :revision="revision" @page="changePage('all', $event)" @organize="organizing = $event" @rate="ratingBook = $event" @create="addBook($event)" />
    <section v-show="mode === 'collections'" aria-label="Coleções da biblioteca">
      <p v-if="meta" class="mb-5 text-xs text-muted-foreground">{{ bookCount(meta.total_books_count) }} em sua biblioteca · {{ meta.total }} {{ meta.total === 1 ? 'coleção' : 'coleções' }}</p>
      <p v-if="loading && !meta || resolving" role="status" class="mb-5 text-sm text-muted-foreground">Carregando coleções…</p>
      <div v-if="error" class="mb-5 rounded-xl border border-border bg-white p-4"><p role="alert" class="text-sm text-destructive">{{ error }}</p><Button variant="outline" class="mt-3 border-border" :disabled="loading" @click="catalog.refresh()">Tentar novamente</Button></div>
      <div v-if="unavailable" role="alert" class="mb-5 rounded-xl border border-border bg-white p-4 text-sm">Esta coleção não está disponível.<Button variant="link" @click="navigate('collections', 'none')">Ver livros sem coleção</Button></div>
      <Accordion v-if="meta" type="single" :model-value="group" @update:model-value="openGroup" class="space-y-4">
        <AccordionItem value="none" class="rounded-2xl border border-border bg-white/50 px-4 sm:px-6">
          <AccordionTrigger :disabled="navigating" class="items-center py-5 hover:no-underline"><span class="flex min-w-0 items-center gap-3"><Folder class="size-5 shrink-0 text-primary" aria-hidden="true" /><span>Sem coleção <span class="ml-2 text-xs font-normal text-muted-foreground">{{ bookCount(meta.uncollected_books_count) }}</span></span></span></AccordionTrigger>
          <AccordionContent force-mount>
            <PaginatedBooks v-if="visited.has('none')" :active="mode === 'collections' && group === 'none'" filter="none" :page="pages.none || 1" :revision="revision" @page="changePage('none', $event)" @organize="organizing = $event" @rate="ratingBook = $event" @create="addBook($event)" />
          </AccordionContent>
        </AccordionItem>
        <AccordionItem v-for="collection in items" :key="collection.id" :value="String(collection.id)" class="rounded-2xl border border-border bg-white/50 px-4 sm:px-6">
          <AccordionTrigger :disabled="navigating" class="items-center py-5 hover:no-underline"><span class="flex min-w-0 items-center gap-3"><Folder class="size-5 shrink-0 text-primary" aria-hidden="true" /><span class="min-w-0 break-words">{{ collection.name }} <span class="ml-2 text-xs font-normal text-muted-foreground">#{{ collection.id }} · {{ bookCount(collection.books_count ?? 0) }}</span></span></span></AccordionTrigger>
          <AccordionContent force-mount>
            <PaginatedBooks v-if="visited.has(String(collection.id))" :active="mode === 'collections' && group === String(collection.id)" :filter="String(collection.id)" :page="pages[String(collection.id)] || 1" :revision="revision" @page="changePage(String(collection.id), $event)" @organize="organizing = $event" @rate="ratingBook = $event" @create="addBook($event)" />
          </AccordionContent>
        </AccordionItem>
      </Accordion>
      <Button v-if="hasMore && meta" variant="outline" class="mt-5 rounded-full border-border" :disabled="loading" @click="catalog.loadMore()">{{ loading ? 'Carregando…' : 'Carregar mais coleções' }}</Button>
    </section>
    <section v-show="mode === 'ratings'" aria-label="Classificações da biblioteca">
      <p class="mb-5 text-xs text-muted-foreground">{{ bookCount(ratingTotal) }} em sua biblioteca · Classificação pela avaliação mais recente</p>
      <p v-if="ratingsLoading" role="status" class="mb-5 text-sm text-muted-foreground">Carregando classificações…</p>
      <div v-if="ratingsError" class="mb-5 rounded-xl border border-border bg-white p-4"><p role="alert" class="text-sm text-destructive">{{ ratingsError }}</p><Button variant="outline" class="mt-3 border-border" :disabled="ratingsLoading" @click="ratings.refresh()">Tentar novamente</Button></div>
      <Accordion type="single" :model-value="ratingBand" @update:model-value="openRatingGroup" class="space-y-4">
        <AccordionItem v-for="band in ratingGroups" :key="band.key" :value="band.key" class="rounded-2xl border border-border bg-white/50 px-4 sm:px-6">
          <AccordionTrigger :disabled="navigating" class="items-center py-5 hover:no-underline"><span class="flex min-w-0 items-center gap-3"><Star class="size-5 shrink-0 text-primary" aria-hidden="true" /><span>{{ band.label }} <span class="ml-2 text-xs font-normal text-muted-foreground">{{ bookCount(band.books_count) }}</span></span></span></AccordionTrigger>
          <AccordionContent force-mount>
            <PaginatedBooks v-if="visited.has(`rating:${band.key}`)" :active="mode === 'ratings' && ratingBand === band.key" :rating-band="band.key" :page="pages[`rating:${band.key}`] || 1" :revision="revision" @page="changePage(`rating:${band.key}`, $event)" @organize="organizing = $event" @rate="ratingBook = $event" @create="addBook()" />
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </section>
    <RateBookDialog :book="ratingBook" @close="ratingBook = null" @saved="rated" />
    <AddBookDialog v-model:open="addOpen" :collection-id="addCollection" @saved="bookAdded" />
    <CreateCollectionDialog v-model:open="createOpen" @created="created" />
    <OrganizeBookDialog :book="organizing" @close="organizing = null" @saved="moved" />
  </AppLayout>
</template>
