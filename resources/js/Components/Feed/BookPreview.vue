<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BookOpen } from '@lucide/vue'
import type { SharedBook } from '@/types/feed'
const props = defineProps<{ book: SharedBook }>()
const failed = ref(false)
watch(() => props.book.cover_url, () => { failed.value = false })
const hasCover = computed(() => props.book.cover_url && !failed.value)
</script>
<template>
  <div class="relative flex aspect-[2/3] w-full items-center justify-center overflow-hidden rounded-lg border border-primary/10 bg-[#e2e9dd] p-2 text-[#354c3d]">
    <img v-if="hasCover" :src="book.cover_url!" :alt="`Capa de ${book.title}`" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover" @error="failed = true" />
    <div v-else class="flex h-full min-w-0 flex-col items-center justify-center gap-2 text-center" aria-hidden="true"><BookOpen class="size-4 shrink-0 opacity-60" /><span class="line-clamp-4 break-words font-serif text-xs leading-4 sm:text-sm">{{ book.title }}</span></div>
  </div>
</template>
