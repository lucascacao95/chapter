<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { BookOpen, Check, ChevronDown, Plus, X } from '@lucide/vue'
import ShareToFeedSwitch from '@/Components/Feed/ShareToFeedSwitch.vue'
import BookField from '@/Components/Books/BookField.vue'
import CollectionPicker from '@/Components/Collections/CollectionPicker.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import type { Book } from '@/types/books'

const props = defineProps<{ collectionId?: number | null }>()
const emit = defineEmits<{ saved: [book: Book]; cancel: []; busy: [value: boolean] }>()

interface BookForm {
  share_to_feed: boolean
  title: string
  authors: string[]
  page_count: string
  publisher: string
  edition: string
  description: string
  language: string
  published_year: string
  isbn: string
  collection_id: number | null
}

const form = useHttp<BookForm, { data: Book }>({
  share_to_feed: false, title: '', authors: [''], page_count: '', publisher: '', edition: '',
  description: '', language: '', published_year: '', isbn: '', collection_id: null,
})
form.collection_id = props.collectionId ?? null
const detailsOpen = ref(false)
const saved = ref(false)
const failure = ref('')
const formElement = ref<HTMLFormElement | null>(null)
const authorKeys = ref([0])
let nextAuthorKey = 1
const busy = computed(() => form.processing || saved.value)
watch(busy, value => emit('busy', value), { immediate: true })
const errors = computed(() => form.errors as Record<string, string | undefined>)
const details = [
  { key: 'page_count', label: 'Quantidade de páginas', type: 'number', placeholder: 'Ex.: 320', min: 1, max: 2147483647 },
  { key: 'publisher', label: 'Editora', type: 'text', placeholder: 'Nome da editora', maxlength: 255 },
  { key: 'edition', label: 'Edição', type: 'text', placeholder: 'Ex.: 2ª edição revisada', maxlength: 255 },
  { key: 'language', label: 'Idioma', type: 'text', placeholder: 'Ex.: pt-BR', maxlength: 35 },
  { key: 'published_year', label: 'Ano de publicação', type: 'number', placeholder: 'Ex.: 2024', min: 1, max: 9999 },
  { key: 'isbn', label: 'ISBN', type: 'text', placeholder: 'ISBN, se conhecido', maxlength: 32 },
] as const

async function addAuthor() {
  if (form.authors.length >= 100 || busy.value) return
  form.authors.push('')
  authorKeys.value.push(nextAuthorKey++)
  await nextTick()
  document.getElementById(`author-${authorKeys.value.at(-1)}`)?.focus()
}

function removeAuthor(index: number) {
  if (busy.value || form.authors.length <= 1) return
  form.authors.splice(index, 1)
  authorKeys.value.splice(index, 1)
  const authorErrors = Object.keys(form.errors).filter(key => key === 'authors' || key.startsWith('authors.'))
  form.clearErrors(...authorErrors as Array<`authors.${number}` | 'authors'>)
}

async function focusErrors() {
  if (Object.keys(form.errors).some(key => key !== 'share_to_feed' && key !== 'title' && key !== 'authors' && key !== 'collection_id' && !key.startsWith('authors.'))) detailsOpen.value = true
  await nextTick()
  // The collapsible's presence state settles after Vue's first DOM update.
  await new Promise<void>(resolve => requestAnimationFrame(() => resolve()))
  formElement.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
}

async function submit() {
  if (busy.value) return
  failure.value = ''
  try {
    const response = await form.transform(data => ({
      ...data,
      authors: data.authors.every(author => !author.trim()) ? null : data.authors,
      page_count: data.page_count === '' ? null : data.page_count,
      publisher: data.publisher.trim() || null,
      edition: data.edition.trim() || null,
      description: data.description.trim() || null,
      language: data.language.trim() || null,
      published_year: data.published_year === '' ? null : data.published_year,
      isbn: data.isbn.trim() || null,
    })).post('/api/books')

    // useHttp resolves validation failures without a response payload.
    if (!response || form.hasErrors) {
      await focusErrors()
      return
    }
    saved.value = true
    emit('saved', response.data)
  } catch (error) {
    if (error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    failure.value = status === 401 || status === 419
      ? 'Sua sessão expirou. Entre novamente em outra aba e tente salvar este formulário mais uma vez.'
      : 'Não foi possível confirmar o cadastro. Seus campos foram preservados. Verifique sua conexão e tente novamente.'
  }
}

onBeforeUnmount(() => form.cancel())
</script>

<template>
      <form ref="formElement" novalidate @submit.prevent="submit">
        <p v-if="failure" role="alert" class="mb-5 rounded-xl border border-destructive/25 bg-red-50 p-4 text-sm leading-6 text-destructive">{{ failure }}</p>
        <p v-if="saved" role="status" class="mb-5 flex flex-wrap items-center gap-2 rounded-xl bg-[#edf4e9] p-4 text-sm text-primary"><Check class="size-4" aria-hidden="true" />Livro cadastrado com sucesso.</p>
        <Card class="gap-0 rounded-2xl border-border bg-white p-5 shadow-none sm:p-8">
          <fieldset :disabled="busy" class="min-w-0 space-y-7">
            <legend class="sr-only">Dados do livro</legend>
            <div class="flex items-center gap-3 border-b border-border pb-5">
              <span class="grid size-10 place-items-center rounded-xl bg-[#edf1e9] text-primary"><BookOpen class="size-5" aria-hidden="true" /></span>
              <div><h2 class="text-sm font-semibold">Informações básicas</h2><p class="mt-1 text-xs text-muted-foreground">Somente o título é obrigatório.</p></div>
            </div>

            <BookField v-slot="{ attributes }" input-id="title" label="Título" required :error="errors.title">
              <Input v-model="form.title" v-bind="attributes" maxlength="255" placeholder="Qual é o título do livro?" class="h-11 rounded-lg" />
            </BookField>

            <fieldset class="min-w-0 space-y-4">
              <legend class="mb-1 text-sm font-medium">Autores <span class="font-normal text-muted-foreground">(opcional)</span></legend>
              <p class="text-xs text-muted-foreground">Adicione um nome por campo.</p>
              <p v-if="errors.authors" id="authors-error" role="alert" class="text-sm text-destructive">{{ errors.authors }}</p>
              <div v-for="(key, index) in authorKeys" :key="key" class="flex items-start gap-2">
                <BookField v-slot="{ attributes }" class="flex-1" :input-id="`author-${key}`" :label="`Autor ${index + 1}`" :error="errors[`authors.${index}`]">
                  <Input v-model="form.authors[index]" v-bind="attributes" maxlength="255" autocomplete="off" placeholder="Nome do autor ou autora" class="h-11 rounded-lg" />
                </BookField>
                <Button v-if="authorKeys.length > 1" type="button" variant="ghost" size="icon" class="mt-6 size-11 rounded-lg text-muted-foreground" :aria-label="`Remover autor ${index + 1}`" @click="removeAuthor(index)"><X aria-hidden="true" /></Button>
              </div>
              <Button type="button" variant="outline" size="sm" class="rounded-full" :disabled="busy || form.authors.length >= 100" @click="addAuthor"><Plus aria-hidden="true" />Adicionar autor</Button>
            </fieldset>

            <BookField input-id="book-collection" label="Coleção (opcional)" :error="errors.collection_id">
              <CollectionPicker v-model="form.collection_id" input-id="book-collection" allow-create :disabled="busy" :invalid="!!errors.collection_id" :described-by="errors.collection_id ? 'book-collection-error' : undefined" />
            </BookField>

            <Collapsible v-model:open="detailsOpen" class="border-t border-border pt-5">
              <CollapsibleTrigger as-child>
                <Button type="button" variant="ghost" class="h-auto w-full justify-between rounded-lg px-0 py-2 text-left hover:bg-transparent">
                  <span><span class="block text-sm font-semibold">Mais detalhes</span><span class="mt-1 block whitespace-normal text-xs font-normal leading-5 text-muted-foreground">Editora, páginas e outros metadados opcionais.</span></span>
                  <ChevronDown :class="['ml-3 size-4 shrink-0 transition-transform', { 'rotate-180': detailsOpen }]" aria-hidden="true" />
                </Button>
              </CollapsibleTrigger>
              <CollapsibleContent class="pt-6">
                <div class="grid gap-6 sm:grid-cols-2">
                  <BookField v-for="field in details" :key="field.key" v-slot="{ attributes }" :input-id="field.key" :label="field.label" :error="errors[field.key]">
                    <Input v-bind="attributes" :model-value="form[field.key]" @update:model-value="form[field.key] = String($event)" :type="field.type" :placeholder="field.placeholder" :min="'min' in field ? field.min : undefined" :max="'max' in field ? field.max : undefined" :maxlength="'maxlength' in field ? field.maxlength : undefined" :step="field.type === 'number' ? 1 : undefined" class="h-11 rounded-lg" />
                  </BookField>
                  <BookField v-slot="{ attributes }" input-id="description" label="Descrição" :error="errors.description" class="sm:col-span-2">
                    <Textarea v-model="form.description" v-bind="attributes" maxlength="10000" rows="4" placeholder="Uma sinopse ou descrição do livro" class="min-h-32 rounded-lg" />
                  </BookField>
                </div>
              </CollapsibleContent>
            </Collapsible>
            <ShareToFeedSwitch v-model="form.share_to_feed" kind="book" :disabled="busy" :error="form.errors.share_to_feed" />
          </fieldset>
        </Card>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" size="lg" class="rounded-full" :disabled="busy" @click="emit('cancel')">Cancelar</Button>
          <Button type="submit" size="lg" class="rounded-full px-6" :disabled="busy">{{ form.processing ? 'Cadastrando…' : saved ? 'Cadastrado' : 'Cadastrar livro' }}</Button>
        </div>
      </form>
</template>
