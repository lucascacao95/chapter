<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BookOpen, Folder, LoaderCircle, Clock3, MoreHorizontal, Star, Send } from '@lucide/vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { sharingLabel } from '@/types/feed'
import RatingStars from './RatingStars.vue'
import SendToKindleButton from './SendToKindleButton.vue'
import type { Book } from '@/types/books'

const props = defineProps<{ book: Book }>()
const emit = defineEmits<{ organize: [book: Book]; rate: [book: Book]; kindleQueued: [] }>()
const palettes = ['bg-[#e2e9dd] text-[#354c3d]', 'bg-[#eee2d5] text-[#73533f]', 'bg-[#dfe8ea] text-[#3d5963]', 'bg-[#e8e2eb] text-[#63526b]']
const palette = computed(() => palettes[props.book.id % palettes.length])
const authors = computed(() => props.book.authors?.length ? props.book.authors.join(', ') : 'Autoria não informada')
const processing = computed(() => ['pending', 'processing'].includes(props.book.file?.processing_status ?? ''))
const queued = computed(() => props.book.file?.processing_status === 'pending')
const coverFailed = ref(false)
watch(() => props.book.cover_url, () => { coverFailed.value = false })
const hasCover = computed(() => !!props.book.cover_url && !coverFailed.value)
</script>

<template>
  <article data-testid="book-card" class="min-w-0" :aria-busy="processing">
    <Card class="gap-0 overflow-hidden rounded-2xl border-border p-2 shadow-none">
      <div :class="['relative flex aspect-[2/3] min-w-0 flex-col justify-between overflow-hidden rounded-xl', palette]">
        <img v-if="hasCover" :src="book.cover_url!" :alt="`Capa de ${book.title}`" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover" @error="coverFailed = true" />
        <div v-else class="flex h-full flex-1 flex-col justify-between p-4 sm:p-5">
          <div class="flex items-center justify-between gap-2 border-b border-current/15 pb-3">
            <BookOpen class="size-4 shrink-0 opacity-75" aria-hidden="true" />
            <span class="text-[10px] font-medium uppercase tracking-widest">{{ processing ? 'Preparando' : 'Sem capa' }}</span>
          </div>
          <p class="my-4 line-clamp-5 break-words font-serif text-xl leading-snug tracking-tight sm:text-2xl" aria-hidden="true">{{ book.title }}</p>
          <p class="line-clamp-2 break-words border-t border-current/15 pt-3 text-[11px] leading-4" aria-hidden="true">{{ authors }}</p>
        </div>
        <span class="pointer-events-none absolute inset-y-0 left-0 w-1.5 bg-black/5" aria-hidden="true" />
        <div v-if="processing" class="absolute inset-x-2 bottom-2 flex items-center justify-center gap-2 rounded-lg border border-border bg-white/95 px-2 py-2.5 text-center text-xs font-medium text-primary shadow-sm">
          <Clock3 v-if="queued" class="size-4 shrink-0" aria-hidden="true" />
          <LoaderCircle v-else class="size-4 shrink-0 motion-safe:animate-spin" aria-hidden="true" />
          <span>{{ queued ? 'Aguardando processamento' : 'Processando arquivo…' }}</span>
        </div>
      </div>
      <div class="px-1.5 pb-3 pt-4">
        <h2 class="break-words text-sm font-semibold leading-5">{{ book.title }}</h2>
        <p class="mt-1.5 break-words text-xs leading-5 text-muted-foreground">{{ authors }}</p>
        <p v-if="book.published_year || book.page_count" class="mt-2 flex flex-wrap gap-x-2 text-[11px] leading-5 text-muted-foreground">
          <span v-if="book.published_year">{{ book.published_year }}</span>
          <span v-if="book.published_year && book.page_count" aria-hidden="true">·</span>
          <span v-if="book.page_count">{{ book.page_count }} {{ book.page_count === 1 ? 'página' : 'páginas' }}</span>
        </p>
        <div v-if="book.file" class="mt-2 text-xs leading-5 text-muted-foreground">
          <span class="font-medium uppercase">{{ book.file.format }}</span>
          <p v-if="processing" role="status" aria-live="polite">Buscando título, autoria e capa.</p>
          <p v-else-if="book.file.processing_status === 'failed'" role="status" class="text-destructive">Não foi possível extrair os detalhes. Seu arquivo foi preservado.</p>
        </div>
        <div class="mt-3 text-muted-foreground"><RatingStars v-if="book.latest_rating" :rating="book.latest_rating.rating" /><span v-else class="text-xs">Sem avaliação</span></div>
        <p v-if="book.feed_activity" class="mt-2 text-[11px] leading-4 text-muted-foreground">{{ sharingLabel(book.feed_activity) }}</p>
        <SendToKindleButton :book="book" @queued="emit('kindleQueued')" v-slot="kindle">
          <DropdownMenu>
            <DropdownMenuTrigger as-child><Button type="button" variant="outline" size="sm" class="w-full justify-between rounded-lg border-border text-xs" :data-book-actions="book.id" :aria-label="`Ações de ${book.title}`">Ações<MoreHorizontal aria-hidden="true" /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-60">
              <DropdownMenuItem @select="emit('rate', book)"><Star aria-hidden="true" />Avaliar livro</DropdownMenuItem>
              <DropdownMenuItem @select="emit('organize', book)"><Folder aria-hidden="true" />Organizar</DropdownMenuItem>
              <DropdownMenuItem :disabled="!!kindle.reason || kindle.active || kindle.processing" @select="kindle.show"><Send aria-hidden="true" />{{ kindle.label }}</DropdownMenuItem>
              <p v-if="kindle.reason" class="px-3 pb-2 pt-1 text-xs leading-5 text-muted-foreground">{{ kindle.reason }}</p>
            </DropdownMenuContent>
          </DropdownMenu>
        </SendToKindleButton>
      </div>
    </Card>
  </article>
</template>
