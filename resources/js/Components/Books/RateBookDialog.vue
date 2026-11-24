<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import ShareToFeedSwitch from '@/Components/Feed/ShareToFeedSwitch.vue'
import { sharingLabel } from '@/types/feed'
import RatingInput from './RatingInput.vue'
import RatingStars from './RatingStars.vue'
import { focusBookActions } from './focusBookActions'
import type { Book, BookRating, RatingPage } from '@/types/books'
let lastBookId: number | null = null
const props = defineProps<{ book: Book | null }>()
const emit = defineEmits<{ close: []; saved: [shared?: boolean] }>()
const tab = ref('rating')
const form = useHttp<{ rating: number | null; review: string; share_to_feed: boolean }, { data: BookRating }>({ rating: null, review: '', share_to_feed: false })
const history = useHttp<Record<string, never>, RatingPage>({})
const entries = ref<RatingPage | null>(null)
const historyLoading = ref(false)
const historyError = ref('')
const failure = ref('')
const uncertain = ref(false)
let sequence = 0
const date = (value: string) => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
function errorMessage(error: unknown, fallback: string) {
  const status = error instanceof HttpResponseError ? error.response.status : undefined
  return status === 401 || status === 419 ? 'Sua sessão expirou. Entre novamente para continuar.'
    : status === 404 ? 'Este livro não está mais disponível.' : fallback
}
watch(() => props.book, book => {
  if (book) lastBookId = book.id
  sequence++
  history.cancel()
  entries.value = null
  historyLoading.value = false
  historyError.value = ''
  tab.value = 'rating'
  failure.value = ''
  uncertain.value = false
  form.clearErrors()
  form.share_to_feed = false
  form.rating = book?.latest_rating?.rating ?? null
  form.review = book?.latest_rating?.review ?? ''
})
watch(tab, value => { if (value === 'history' && !entries.value) void loadHistory() })
async function loadHistory(page = 1) {
  if (!props.book || historyLoading.value) return
  const current = ++sequence
  historyLoading.value = true
  historyError.value = ''
  try {
    const response = await history.get(`/api/books/${props.book.id}/ratings?page=${page}`)
    if (current === sequence && response) entries.value = response
  } catch (error) {
    if (current === sequence && !(error instanceof HttpCancelledError)) historyError.value = errorMessage(error, 'Não foi possível carregar o histórico. Tente novamente.')
  } finally { if (current === sequence) historyLoading.value = false }
}
function setOpen(open: boolean) { if (!open && !form.processing) emit('close') }
async function submit() {
  if (!props.book || form.processing || form.rating === null || uncertain.value) return
  failure.value = ''
  try {
    const response = await form.post(`/api/books/${props.book.id}/ratings`)
    if (!response || form.hasErrors) return
    emit('saved', form.share_to_feed)
    emit('close')
  } catch (error) {
    if (error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    uncertain.value = !status || status >= 500
    failure.value = errorMessage(error, uncertain.value
      ? 'Não foi possível confirmar o salvamento. Confira o histórico antes de registrar outra avaliação.'
      : 'Não foi possível salvar a avaliação. Seus campos foram preservados; tente novamente.')
    if (uncertain.value) { entries.value = null; emit('saved') }
  }
}
onBeforeUnmount(() => { sequence++; form.cancel(); history.cancel() })
</script>
<template>
  <Dialog :open="!!book" @update:open="setOpen">
    <DialogContent @close-auto-focus="focusBookActions($event, lastBookId)" class="max-h-[90dvh] overflow-y-auto rounded-2xl border-border bg-white" :show-close-button="!form.processing" @escape-key-down="form.processing && $event.preventDefault()" @interact-outside="form.processing && $event.preventDefault()">
      <DialogHeader><DialogTitle class="font-serif text-2xl">Avaliar livro</DialogTitle><DialogDescription class="break-words">{{ book?.title }}</DialogDescription></DialogHeader>
      <Tabs v-model="tab">
        <TabsList class="mb-4"><TabsTrigger value="rating">Sua avaliação</TabsTrigger><TabsTrigger value="history">Histórico</TabsTrigger></TabsList>
        <TabsContent value="rating">
          <form class="space-y-5" @submit.prevent="submit">
            <RatingInput v-model="form.rating" :disabled="form.processing || uncertain" :invalid="!!form.errors.rating" />
            <p id="rating-error" role="alert" class="text-sm text-destructive">{{ form.errors.rating }}</p>
            <div class="space-y-2"><Label for="rating-review">Comentário (opcional)</Label><Textarea id="rating-review" v-model="form.review" :disabled="form.processing || uncertain" maxlength="10000" rows="4" :aria-invalid="!!form.errors.review" aria-describedby="rating-review-error" /><p id="rating-review-error" role="alert" class="text-sm text-destructive">{{ form.errors.review }}</p></div>
            <ShareToFeedSwitch v-model="form.share_to_feed" kind="rating" :disabled="form.processing || uncertain" :error="form.errors.share_to_feed" />
            <p class="text-xs leading-5 text-muted-foreground">Cada salvamento registra uma nova avaliação. Suas avaliações anteriores ficam preservadas no histórico. Avaliações já compartilhadas continuam no feed até serem retiradas.</p>
            <p v-if="failure" role="alert" class="text-sm text-destructive">{{ failure }}</p>
            <Button v-if="uncertain" type="button" variant="outline" @click="tab = 'history'">Conferir histórico</Button>
            <div class="flex flex-wrap justify-end gap-3"><Button type="button" variant="outline" class="rounded-full border-border" :disabled="form.processing" @click="setOpen(false)">Cancelar</Button><Button type="submit" class="rounded-full" :disabled="form.processing || form.rating === null || uncertain">{{ form.processing ? 'Salvando…' : 'Salvar avaliação' }}</Button></div>
          </form>
        </TabsContent>
        <TabsContent value="history" class="space-y-4" :aria-busy="historyLoading">
          <p v-if="historyLoading" role="status" class="text-sm text-muted-foreground">Carregando histórico…</p>
          <div v-if="historyError"><p role="alert" class="text-sm text-destructive">{{ historyError }}</p><Button variant="outline" class="mt-3" @click="loadHistory(entries?.meta.current_page)">Tentar novamente</Button></div>
          <template v-else-if="entries && !historyLoading">
            <p v-if="!entries.data.length" class="text-sm text-muted-foreground">Este livro ainda não recebeu avaliações.</p>
            <ol v-else class="space-y-4"><li v-for="entry in entries.data" :key="entry.id" class="space-y-2 rounded-xl border border-border p-4"><RatingStars :rating="entry.rating" /><p class="text-xs text-muted-foreground"><time :datetime="entry.created_at">{{ date(entry.created_at) }}</time> · Avaliação #{{ entry.id }}</p><p v-if="entry.review" class="whitespace-pre-wrap break-words text-sm leading-6">{{ entry.review }}</p><p v-else class="text-xs text-muted-foreground">Sem comentário.</p><p class="text-xs leading-5 text-muted-foreground">{{ sharingLabel(entry.feed_activity) }}</p></li></ol>
            <nav v-if="entries.meta.last_page > 1" aria-label="Paginação do histórico" class="flex flex-wrap items-center justify-between gap-3"><Button variant="outline" :disabled="entries.meta.current_page <= 1" @click="loadHistory(entries.meta.current_page - 1)">Anterior</Button><span class="text-xs">{{ entries.meta.current_page }} de {{ entries.meta.last_page }}</span><Button variant="outline" :disabled="entries.meta.current_page >= entries.meta.last_page" @click="loadHistory(entries.meta.current_page + 1)">Próxima</Button></nav>
          </template>
        </TabsContent>
      </Tabs>
    </DialogContent>
  </Dialog>
</template>
