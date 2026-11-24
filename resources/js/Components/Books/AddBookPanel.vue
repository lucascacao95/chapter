<script setup lang="ts">
import { ref } from 'vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import UploadBookForm from './UploadBookForm.vue'
import ManualBookForm from './ManualBookForm.vue'
import type { Book } from '@/types/books'

defineProps<{ collectionId?: number | null }>()
const emit = defineEmits<{ saved: [book: Book]; cancel: []; busy: [value: boolean] }>()
const busy = ref(false)
function setBusy(value: boolean) { busy.value = value; emit('busy', value) }
</script>

<template>
  <Tabs default-value="upload" class="min-w-0 gap-6">
    <TabsList class="grid h-auto w-full grid-cols-2 rounded-xl bg-accent p-1">
      <TabsTrigger value="upload" :disabled="busy" class="rounded-lg px-2 py-2.5">Enviar arquivo</TabsTrigger>
      <TabsTrigger value="manual" :disabled="busy" class="rounded-lg px-2 py-2.5">Cadastro manual</TabsTrigger>
    </TabsList>
    <TabsContent value="upload" force-mount class="min-w-0 data-[state=inactive]:hidden">
      <UploadBookForm :collection-id="collectionId" @busy="setBusy" @cancel="emit('cancel')" @saved="emit('saved', $event)" />
    </TabsContent>
    <TabsContent value="manual" force-mount class="min-w-0 data-[state=inactive]:hidden">
      <ManualBookForm :collection-id="collectionId" @busy="setBusy" @cancel="emit('cancel')" @saved="emit('saved', $event)" />
    </TabsContent>
  </Tabs>
</template>
