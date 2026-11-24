<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { FileUp, Upload } from '@lucide/vue'
import { Button } from '@/Components/ui/button'
import CollectionPicker from '@/Components/Collections/CollectionPicker.vue'
import ShareToFeedSwitch from '@/Components/Feed/ShareToFeedSwitch.vue'
import BookField from './BookField.vue'
import type { Book } from '@/types/books'

const props = defineProps<{ collectionId?: number | null }>()
const emit = defineEmits<{ saved: [book: Book]; cancel: []; busy: [value: boolean] }>()
const form = useHttp<{ file: File | null; share_to_feed: boolean; collection_id: number | null }, { data: Book }>({ file: null, share_to_feed: false, collection_id: props.collectionId ?? null })
const input = ref<HTMLInputElement | null>(null)
const element = ref<HTMLFormElement | null>(null)
const failure = ref('')
const saved = ref(false)
const busy = computed(() => form.processing || saved.value)
const size = computed(() => form.file ? `${(form.file.size / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB` : '')
watch(busy, value => emit('busy', value), { immediate: true })

function select(event: Event) {
  form.file = (event.target as HTMLInputElement).files?.[0] ?? null
  form.clearErrors('file')
  failure.value = ''
}
async function submit() {
  if (busy.value) return
  failure.value = ''
  if (!form.file) {
    form.setError('file', 'Selecione um arquivo EPUB ou PDF.')
    input.value?.focus()
    return
  }
  if (form.file.size > 50 * 1024 * 1024) {
    form.setError('file', 'O arquivo deve ter no máximo 50 MB.')
    input.value?.focus()
    return
  }
  try {
    const response = await form.post('/api/books/upload')
    if (!response || form.hasErrors) {
      await nextTick()
      element.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
      return
    }
    saved.value = true
    emit('saved', response.data)
  } catch (error) {
    if (error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    failure.value = status === 401 || status === 419
      ? 'Sua sessão expirou. Entre novamente em outra aba e tente enviar mais uma vez.'
      : status === 413 ? 'O arquivo excede o limite de envio de 50 MB.'
      : 'Não foi possível confirmar o envio. Confira a biblioteca antes de tentar novamente para evitar um cadastro duplicado.'
  }
}
onBeforeUnmount(() => form.cancel())
</script>

<template>
  <form ref="element" class="space-y-6" novalidate @submit.prevent="submit">
    <p v-if="failure" role="alert" class="rounded-xl border border-destructive/25 bg-red-50 p-4 text-sm text-destructive">{{ failure }}</p>
    <fieldset :disabled="busy" class="min-w-0 space-y-6">
      <legend class="sr-only">Enviar arquivo de livro</legend>
      <div class="rounded-2xl border border-dashed border-border bg-accent/30 p-5 sm:p-7">
        <FileUp class="mb-4 size-8 text-primary" aria-hidden="true" />
        <label for="book-upload" class="block text-base font-semibold">Selecione seu livro</label>
        <p id="book-upload-help" class="mb-5 mt-2 text-sm leading-6 text-muted-foreground">EPUB ou PDF, um arquivo por envio, até 50 MB. Use arquivos que você tem autorização para enviar.</p>
        <input id="book-upload" ref="input" type="file" accept=".epub,.pdf,application/epub+zip,application/pdf" class="block w-full min-w-0 rounded-lg border border-input bg-white p-3 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-2 file:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :aria-invalid="!!form.errors.file" :aria-describedby="`book-upload-help${form.errors.file ? ' book-upload-error' : ''}`" @change="select" />
        <p v-if="form.file" class="mt-3 break-all text-xs text-muted-foreground">{{ form.file.name }} · {{ size }}</p>
        <p v-if="form.errors.file" id="book-upload-error" role="alert" class="mt-3 text-sm text-destructive">{{ form.errors.file }}</p>
      </div>
      <BookField input-id="upload-collection" label="Coleção (opcional)" :error="form.errors.collection_id">
        <CollectionPicker v-model="form.collection_id" input-id="upload-collection" allow-create :disabled="busy" :invalid="!!form.errors.collection_id" :described-by="form.errors.collection_id ? 'upload-collection-error' : undefined" />
      </BookField>
      <p class="text-sm leading-6 text-muted-foreground">Vamos buscar os detalhes disponíveis no arquivo. Se não houver título nos metadados, usaremos o nome do arquivo, sem a extensão.</p>
      <ShareToFeedSwitch v-model="form.share_to_feed" kind="book" :disabled="busy" :error="form.errors.share_to_feed" />
    </fieldset>
    <div v-if="form.processing" role="status" class="space-y-2 text-sm text-muted-foreground">
      <template v-if="form.progress?.percentage !== undefined && form.progress.percentage < 100">
        <label for="upload-progress">Enviando arquivo: {{ form.progress.percentage }}%</label>
        <progress id="upload-progress" :value="form.progress.percentage" max="100" class="h-2 w-full accent-primary" />
      </template>
      <p v-else>Validando e armazenando seu arquivo…</p>
    </div>
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <Button type="button" variant="outline" class="rounded-full border-border" :disabled="busy" @click="emit('cancel')">Cancelar</Button>
      <Button type="submit" class="rounded-full" :disabled="busy"><Upload aria-hidden="true" />{{ form.processing ? 'Enviando…' : saved ? 'Arquivo recebido' : 'Enviar arquivo' }}</Button>
    </div>
  </form>
</template>
