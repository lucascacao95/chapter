<script setup lang="ts">
import { ref } from 'vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import AddBookPanel from './AddBookPanel.vue'
import type { Book } from '@/types/books'

defineProps<{ open: boolean; collectionId?: number | null }>()
const emit = defineEmits<{ 'update:open': [open: boolean]; saved: [book: Book] }>()
const busy = ref(false)
function setOpen(open: boolean) { if (!busy.value) emit('update:open', open) }
function saved(book: Book) { busy.value = false; emit('saved', book); emit('update:open', false) }
</script>

<template>
  <Dialog :open="open" @update:open="setOpen">
    <DialogContent class="max-h-[90dvh] overflow-y-auto rounded-2xl border-border bg-white sm:max-w-2xl" :show-close-button="!busy" @escape-key-down="busy && $event.preventDefault()" @interact-outside="busy && $event.preventDefault()">
      <DialogHeader><DialogTitle class="font-serif text-2xl">Adicionar livro</DialogTitle><DialogDescription>Envie um arquivo para sua biblioteca ou cadastre os detalhes manualmente.</DialogDescription></DialogHeader>
      <AddBookPanel :collection-id="collectionId" @busy="busy = $event" @cancel="setOpen(false)" @saved="saved" />
    </DialogContent>
  </Dialog>
</template>
