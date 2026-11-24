<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useHttp } from '@inertiajs/vue3'
import { HttpCancelledError, HttpResponseError } from '@inertiajs/core'
import { Send, LoaderCircle } from '@lucide/vue'
import { Button } from '@/Components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog'
import { focusBookActions } from './focusBookActions'
import type { Book, KindleDelivery } from '@/types/books'

const props = defineProps<{ book: Book }>()
const emit = defineEmits<{ queued: [] }>()
const open = ref(false)
const failure = ref('')
const acknowledged = ref(false)
const delivery = ref<KindleDelivery | null>(props.book.kindle?.delivery ?? null)
const form = useHttp<{ idempotency_key: string }, { data: KindleDelivery }>({ idempotency_key: crypto.randomUUID() })
watch(() => props.book.kindle?.delivery, value => {
  if (!value) return
  const rank = (status: string) => status === 'queued' ? 0 : status === 'processing' ? 1 : 2
  if (!delivery.value || value.id > delivery.value.id || (value.id === delivery.value.id && rank(value.status) >= rank(delivery.value.status))) delivery.value = value
}, { deep: true })
const active = computed(() => ['queued', 'processing'].includes(delivery.value?.status ?? ''))
const reason = computed(() => {
  if (!props.book.file) return 'Este cadastro não possui um arquivo para envio.'
  if (!props.book.kindle?.configured) return 'Configure o Gmail remetente para habilitar o envio.'
  if (props.book.file.processing_status !== 'completed') return 'O arquivo precisa concluir o processamento antes do envio.'
  if (props.book.file.size_bytes > props.book.kindle.max_attachment_bytes) return 'O limite de envio pelo Gmail nesta etapa é de 18 MB.'
  return ''
})
const label = computed(() => delivery.value?.status === 'queued' ? 'Envio na fila'
  : delivery.value?.status === 'processing' ? 'Enviando…'
  : delivery.value?.status === 'submitted' ? 'Enviar novamente' : 'Enviar ao Kindle')
const statusText = computed(() => delivery.value?.status === 'submitted'
  ? 'E-mail aceito pelo Gmail. Confira a chegada no Kindle.'
  : delivery.value?.status === 'failed'
    ? delivery.value.error_code === 'submission_unknown'
      ? 'Não foi possível confirmar o envio. Confira o Kindle antes de tentar novamente.'
      : 'Não foi possível enviar. Verifique o arquivo e a configuração do Gmail.'
    : delivery.value?.status === 'queued' ? 'Seu envio está na fila.'
      : delivery.value?.status === 'processing' ? 'Enviando o livro por e-mail…' : '')

function show() {
  if (reason.value || active.value || form.processing) return
  if (acknowledged.value) { form.idempotency_key = crypto.randomUUID(); acknowledged.value = false }
  failure.value = ''
  form.clearErrors()
  open.value = true
}
function setOpen(value: boolean) { if (!form.processing) open.value = value }
async function submit() {
  if (form.processing || active.value || reason.value) return
  failure.value = ''
  try {
    const response = await form.post(`/api/books/${props.book.id}/kindle`)
    if (!response || form.hasErrors) return
    delivery.value = response.data
    acknowledged.value = true
    open.value = false
    emit('queued')
  } catch (error) {
    if (error instanceof HttpCancelledError) return
    const status = error instanceof HttpResponseError ? error.response.status : undefined
    failure.value = status === 401 || status === 419 ? 'Sua sessão expirou. Entre novamente para continuar.'
      : status === 429 ? 'Aguarde um minuto antes de solicitar outro envio.'
      : 'Não foi possível confirmar a solicitação. Confira o estado do envio na biblioteca antes de tentar novamente.'
    emit('queued')
  }
}
onBeforeUnmount(() => form.cancel())
</script>

<template>
  <div class="mt-2">
    <slot :show="show" :reason="reason" :active="active" :processing="form.processing" :label="label">
      <Button type="button" variant="outline" size="sm" class="h-auto w-full justify-start whitespace-normal rounded-lg border-border px-2 py-2 text-left text-xs" :disabled="!!reason || active || form.processing" :aria-label="`${label}: ${book.title}`" @click="show">
        <LoaderCircle v-if="active || form.processing" class="size-4 shrink-0 motion-safe:animate-spin" aria-hidden="true" /><Send v-else class="size-4 shrink-0" aria-hidden="true" />{{ label }}
      </Button>
    </slot>
    <p v-if="statusText" role="status" class="mt-2 text-xs leading-5" :class="delivery?.status === 'failed' ? 'text-destructive' : 'text-muted-foreground'">{{ statusText }}</p>
    <p v-else-if="reason && !$slots.default" class="mt-2 text-[11px] leading-4 text-muted-foreground">{{ reason }}</p>
    <Dialog :open="open" @update:open="setOpen">
      <DialogContent @close-auto-focus="focusBookActions($event, book.id)" class="rounded-2xl border-border bg-white" :show-close-button="!form.processing" @escape-key-down="form.processing && $event.preventDefault()" @interact-outside="form.processing && $event.preventDefault()">
        <DialogHeader><DialogTitle class="font-serif text-2xl">Enviar ao Kindle</DialogTitle><DialogDescription class="break-words">{{ book.title }}</DialogDescription></DialogHeader>
        <form class="space-y-5" @submit.prevent="submit">
          <p class="text-sm leading-6 text-muted-foreground">O livro será enviado por e-mail ao Kindle configurado. O endereço Gmail remetente precisa estar autorizado na sua conta Amazon.</p>
          <p class="text-sm leading-6 text-muted-foreground">O aceite do e-mail não confirma a chegada ao dispositivo.</p>
          <p v-if="failure || Object.keys(form.errors).length" role="alert" class="text-sm text-destructive">{{ failure || Object.values(form.errors)[0] }}</p>
          <div class="flex flex-wrap justify-end gap-3">
            <Button type="button" variant="outline" class="rounded-full border-border" :disabled="form.processing" @click="setOpen(false)">Cancelar</Button>
            <Button type="submit" class="rounded-full" :disabled="form.processing || active || !!reason">{{ form.processing ? 'Solicitando…' : 'Confirmar envio' }}</Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>
