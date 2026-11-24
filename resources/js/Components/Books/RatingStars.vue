<script setup lang="ts">
import { Star } from '@lucide/vue'
import { formatRating } from '@/types/books'
defineProps<{ rating: number }>()
</script>
<template>
  <span class="inline-flex flex-wrap items-center gap-2" :aria-label="`${formatRating(rating)} de 5 estrelas`">
    <span class="inline-flex gap-0.5" aria-hidden="true">
      <span v-for="star in 5" :key="star" class="relative block size-4 text-amber-600">
        <Star class="absolute size-4 opacity-40" />
        <span class="absolute inset-y-0 left-0 overflow-hidden" :style="{ width: `${Math.max(0, Math.min(1, rating - star + 1)) * 100}%` }"><Star class="size-4 fill-current" /></span>
      </span>
    </span>
    <span class="text-xs tabular-nums" aria-hidden="true">{{ formatRating(rating) }}/5</span>
  </span>
</template>
