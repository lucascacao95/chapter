<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Check, ChevronDown, Plus } from '@lucide/vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { Button } from '@/Components/ui/button'
import CreateCollectionDialog from './CreateCollectionDialog.vue'
import { useCollections } from '@/composables/useCollections'
import type { LibraryCollection } from '@/types/collections'

const props = withDefaults(defineProps<{ modelValue: number | null; inputId: string; disabled?: boolean; allowCreate?: boolean; invalid?: boolean; describedBy?: string }>(), { allowCreate: false })
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()
const catalog = useCollections()
const { items, loading, error, hasMore } = catalog
const opened = ref(false)
const createOpen = ref(false)
const missing = ref(false)
const selected = computed(() => items.value.find(item => item.id === props.modelValue))
async function resolveSelection() {
  missing.value = false
  if (props.modelValue !== null) missing.value = !(await catalog.ensure(props.modelValue)) && !error.value
}
onMounted(async () => { await catalog.loadMore(); await resolveSelection() })
watch(() => props.modelValue, resolveSelection)
function select(value: number | null) { emit('update:modelValue', value); opened.value = false }
async function created(collection: LibraryCollection) {
  emit('update:modelValue', collection.id)
  await catalog.refresh()
  await resolveSelection()
}
</script>

<template>
  <div class="space-y-2">
    <Popover v-model:open="opened">
      <PopoverTrigger as-child>
        <Button :id="inputId" type="button" variant="outline" class="h-11 w-full justify-between rounded-lg border-input bg-white text-left" :disabled="disabled" :aria-invalid="invalid || missing" :aria-describedby="[`${inputId}-value`, describedBy].filter(Boolean).join(' ')">
          <span :id="`${inputId}-value`" class="truncate">{{ modelValue === null ? 'Sem coleção' : selected ? `${selected.name} · #${selected.id}` : loading ? 'Carregando coleção…' : 'Selecione uma coleção' }}</span><ChevronDown class="size-4 shrink-0" aria-hidden="true" />
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" class="w-[var(--reka-popover-trigger-width)] min-w-56 max-w-[calc(100vw-2rem)] rounded-xl border-border bg-white p-2" aria-label="Escolher coleção">
        <div class="max-h-64 space-y-1 overflow-y-auto">
          <Button type="button" variant="ghost" class="w-full justify-between" :aria-pressed="modelValue === null" @click="select(null)">Sem coleção<Check v-if="modelValue === null" aria-hidden="true" /></Button>
          <Button v-for="collection in items" :key="collection.id" type="button" variant="ghost" class="h-auto min-h-10 w-full justify-between whitespace-normal text-left" :aria-pressed="modelValue === collection.id" @click="select(collection.id)">
            <span class="min-w-0 break-words">{{ collection.name }} <span class="text-xs text-muted-foreground">#{{ collection.id }}</span></span><Check v-if="modelValue === collection.id" class="shrink-0" aria-hidden="true" />
          </Button>
        </div>
        <p v-if="loading" role="status" class="p-2 text-xs text-muted-foreground">Carregando coleções…</p>
        <p v-if="error" role="alert" class="p-2 text-xs text-destructive">{{ error }}</p>
        <Button v-if="error" type="button" variant="ghost" class="w-full text-xs" :disabled="loading" @click="catalog.refresh()">Tentar novamente</Button>
        <Button v-else-if="hasMore" type="button" variant="ghost" class="w-full text-xs" :disabled="loading" @click="catalog.loadMore()">Carregar mais coleções</Button>
      </PopoverContent>
    </Popover>
    <p v-if="missing" role="alert" class="text-sm text-destructive">Esta coleção não está disponível. Escolha outra coleção ou Sem coleção.</p>
    <Button v-if="allowCreate" type="button" variant="link" size="sm" class="h-auto px-0 text-xs" :disabled="disabled" @click="createOpen = true"><Plus aria-hidden="true" />Nova coleção</Button>
    <CreateCollectionDialog v-if="allowCreate" v-model:open="createOpen" @created="created" />
  </div>
</template>
