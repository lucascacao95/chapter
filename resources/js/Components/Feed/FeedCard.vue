<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { MoreHorizontal, Trash2 } from '@lucide/vue'
import { Button } from '@/Components/ui/button'
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import RatingStars from '@/Components/Books/RatingStars.vue'
import BookPreview from './BookPreview.vue'
import type { FeedActivity } from '@/types/feed'
const props = defineProps<{ activity: FeedActivity }>()
const emit = defineEmits<{ removed: [id: number] }>()
const expanded = ref(false)
const open = ref(false)
const failure = ref('')
const request = useHttp<Record<string, never>, unknown>({})
const trigger = ref<InstanceType<typeof Button> | null>(null)
const date = (value: string) => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
function setOpen(value: boolean) { if (!request.processing) { open.value = value; if (value) failure.value = '' } }
function restoreFocus(event: Event) {
  event.preventDefault()
  if (trigger.value?.$el?.isConnected) trigger.value.$el.focus()
  else document.getElementById('feed-title')?.focus()
}
async function withdraw() {
  if (request.processing) return
  failure.value = ''
  try {
    await request.delete(`/api/feed-activities/${props.activity.id}`)
    open.value = false
    emit('removed', props.activity.id)
  } catch (error) {
    if (error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    if (status === 404) { open.value = false; emit('removed', props.activity.id); return }
    failure.value = status === 401 || status === 419 ? 'Sua sessão expirou. Entre novamente para continuar.' : 'Não foi possível confirmar a retirada. Tente novamente.'
  }
}
onBeforeUnmount(() => request.cancel())
</script>
<template>
  <article data-testid="feed-card" :data-activity-id="activity.id" class="min-w-0 rounded-2xl border border-border bg-white p-5 sm:p-6">
    <header class="flex items-start gap-3">
      <span class="grid size-9 shrink-0 place-items-center rounded-full bg-accent text-sm font-semibold text-primary" aria-hidden="true">{{ activity.actor.name.slice(0, 1).toLocaleUpperCase('pt-BR') }}</span>
      <div class="min-w-0 flex-1"><p class="break-words text-sm leading-6"><span class="font-semibold">{{ activity.actor.name }}</span> {{ activity.type === 'book_rated' ? 'avaliou um livro' : 'adicionou um livro' }}</p><time :datetime="activity.occurred_at" class="mt-1 block text-xs text-muted-foreground">{{ date(activity.occurred_at) }}</time></div>
      <DropdownMenu v-if="activity.can_withdraw"><DropdownMenuTrigger as-child><Button ref="trigger" variant="ghost" size="icon-sm" :aria-label="`Opções da atividade ${activity.id}`"><MoreHorizontal aria-hidden="true" /></Button></DropdownMenuTrigger><DropdownMenuContent align="end"><DropdownMenuItem @select="setOpen(true)"><Trash2 aria-hidden="true" />Retirar do feed</DropdownMenuItem></DropdownMenuContent></DropdownMenu>
    </header>
    <div class="mt-5 flex min-w-0 items-start gap-4 rounded-xl bg-accent/20 p-3 sm:gap-5 sm:p-4">
      <div class="w-16 shrink-0 sm:w-20"><BookPreview :book="activity.book" /></div>
      <div class="min-w-0 py-1"><h3 class="break-words font-serif text-lg leading-6 sm:text-xl">{{ activity.book.title }}</h3><p class="mt-2 break-words text-xs leading-5 text-muted-foreground">{{ activity.book.authors?.join(', ') || 'Autoria não informada' }}</p><div v-if="activity.rating" class="mt-3"><RatingStars :rating="activity.rating.value" /></div></div>
    </div>
    <div v-if="activity.rating?.review" class="mt-4"><p class="whitespace-pre-wrap break-words text-sm leading-7 text-foreground/85">{{ expanded || activity.rating.review.length <= 500 ? activity.rating.review : `${activity.rating.review.slice(0, 500)}…` }}</p><Button v-if="activity.rating.review.length > 500" variant="link" size="sm" class="mt-1 h-auto px-0 py-1" :aria-expanded="expanded" @click="expanded = !expanded">{{ expanded ? 'Ler menos' : 'Ler mais' }}</Button></div>
    <Dialog :open="open" @update:open="setOpen"><DialogContent class="rounded-2xl border-border bg-white" :show-close-button="!request.processing" @close-auto-focus="restoreFocus" @escape-key-down="request.processing && $event.preventDefault()" @interact-outside="request.processing && $event.preventDefault()"><DialogHeader><DialogTitle>Retirar do feed?</DialogTitle><DialogDescription>Esta atividade deixará de aparecer para você e seus seguidores. O livro e suas avaliações continuam na sua biblioteca.</DialogDescription></DialogHeader><p v-if="failure" role="alert" class="text-sm text-destructive">{{ failure }}</p><div class="flex flex-wrap justify-end gap-3"><Button variant="outline" :disabled="request.processing" @click="setOpen(false)">Cancelar</Button><Button :disabled="request.processing" @click="withdraw">{{ request.processing ? 'Retirando…' : 'Confirmar retirada' }}</Button></div></DialogContent></Dialog>
  </article>
</template>
