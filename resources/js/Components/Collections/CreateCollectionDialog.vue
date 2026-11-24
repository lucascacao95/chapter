<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError } from '@inertiajs/core'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import BookField from '@/Components/Books/BookField.vue'
import type { LibraryCollection } from '@/types/collections'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ 'update:open': [open: boolean]; created: [collection: LibraryCollection] }>()
const form = useHttp<{ name: string }, { data: LibraryCollection }>({ name: '' })
const failure = ref('')
const saved = ref(false)
watch(() => props.open, open => { if (open) { form.resetAndClearErrors(); failure.value = ''; saved.value = false } })
function setOpen(open: boolean) { if (!form.processing) emit('update:open', open) }
async function submit() {
  if (form.processing || saved.value) return
  failure.value = ''
  try {
    const response = await form.post('/api/collections')
    if (!response || form.hasErrors) {
      await nextTick()
      document.getElementById('collection-name')?.focus()
      return
    }
    saved.value = true
    emit('created', response.data)
    emit('update:open', false)
  } catch (error) {
    if (!(error instanceof HttpCancelledError)) failure.value = 'Não foi possível confirmar a criação. Seu nome foi preservado; tente novamente.'
  }
}
onBeforeUnmount(() => form.cancel())
</script>

<template>
  <Dialog :open="open" @update:open="setOpen">
    <DialogContent class="rounded-2xl border-border bg-white" :show-close-button="!form.processing">
      <DialogHeader><DialogTitle class="font-serif text-2xl">Nova coleção</DialogTitle><DialogDescription>Crie um espaço para organizar suas leituras.</DialogDescription></DialogHeader>
      <form class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="failure" role="alert" class="text-sm text-destructive">{{ failure }}</p>
        <BookField v-slot="{ attributes }" input-id="collection-name" label="Nome da coleção" required :error="form.errors.name">
          <Input v-model="form.name" v-bind="attributes" maxlength="255" :disabled="form.processing" placeholder="Ex.: Leituras de férias" class="h-11 rounded-lg" />
        </BookField>
        <div class="flex flex-wrap justify-end gap-3">
          <Button type="button" variant="outline" class="rounded-full border-border" :disabled="form.processing" @click="setOpen(false)">Cancelar</Button>
          <Button type="submit" class="rounded-full" :disabled="form.processing || saved">{{ form.processing ? 'Criando…' : 'Criar coleção' }}</Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
