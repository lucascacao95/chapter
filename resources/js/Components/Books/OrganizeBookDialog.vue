<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError } from '@inertiajs/core'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import CollectionPicker from '@/Components/Collections/CollectionPicker.vue'
import { focusBookActions } from './focusBookActions'
import type { Book } from '@/types/books'

let lastBookId: number | null = null
const props = defineProps<{ book: Book | null }>()
const emit = defineEmits<{ close: []; saved: [book: Book, previousCollectionId: number | null] }>()
const form = useHttp<{ collection_id: number | null }, { data: Book }>({ collection_id: null })
const failure = ref('')
const saved = ref(false)
watch(() => props.book, book => {
  if (book) { lastBookId = book.id; form.collection_id = book.collection_id; form.clearErrors(); failure.value = ''; saved.value = false }
})
function setOpen(open: boolean) { if (!open && !form.processing) emit('close') }
async function submit() {
  if (!props.book || form.processing || saved.value || form.collection_id === props.book.collection_id) return
  const previous = props.book.collection_id
  failure.value = ''
  try {
    const result = await form.patch(`/api/books/${props.book.id}`)
    if (!result || form.hasErrors) {
      await nextTick()
      document.getElementById('organize-collection')?.focus()
      return
    }
    saved.value = true
    emit('saved', result.data, previous)
    emit('close')
  } catch (error) {
    if (!(error instanceof HttpCancelledError)) failure.value = 'Não foi possível confirmar a alteração. Sua escolha foi preservada; tente novamente.'
  }
}
onBeforeUnmount(() => form.cancel())
</script>

<template>
  <Dialog :open="!!book" @update:open="setOpen">
    <DialogContent @close-auto-focus="focusBookActions($event, lastBookId)" class="rounded-2xl border-border bg-white" :show-close-button="!form.processing">
      <DialogHeader><DialogTitle class="font-serif text-2xl">Organizar livro</DialogTitle><DialogDescription class="break-words">{{ book?.title }}</DialogDescription></DialogHeader>
      <form class="space-y-5" @submit.prevent="submit">
        <div class="space-y-2">
          <Label for="organize-collection">Coleção</Label>
          <CollectionPicker v-if="book" :key="book.id" v-model="form.collection_id" input-id="organize-collection" :disabled="form.processing" :invalid="!!form.errors.collection_id" :described-by="form.errors.collection_id ? 'organize-error' : undefined" />
          <p v-if="form.errors.collection_id" id="organize-error" role="alert" class="text-sm text-destructive">{{ form.errors.collection_id }}</p>
          <p class="text-xs leading-5 text-muted-foreground">O livro pertence a uma coleção por vez. Escolha Sem coleção para remover o vínculo.</p>
        </div>
        <p v-if="failure" role="alert" class="text-sm text-destructive">{{ failure }}</p>
        <div class="flex flex-wrap justify-end gap-3">
          <Button type="button" variant="outline" class="rounded-full border-border" :disabled="form.processing" @click="setOpen(false)">Cancelar</Button>
          <Button type="submit" class="rounded-full" :disabled="form.processing || saved || form.collection_id === book?.collection_id">{{ form.processing ? 'Salvando…' : 'Salvar organização' }}</Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
