<script setup lang="ts">
import { useId } from 'vue'
import { Star } from '@lucide/vue'
import { formatRating } from '@/types/books'
const value = defineModel<number | null>({ required: true })
defineProps<{ disabled?: boolean; invalid?: boolean }>()
const name = useId()
</script>
<template>
  <fieldset :disabled="disabled" :aria-invalid="invalid || undefined" aria-describedby="rating-help rating-error" class="space-y-3">
    <legend class="mb-2 text-sm font-medium">Sua nota</legend>
    <div class="flex flex-wrap items-center gap-3">
      <label class="relative cursor-pointer rounded-lg border border-border px-2 py-2 text-xs focus-within:ring-2 focus-within:ring-ring" :class="value === 0 ? 'bg-primary text-primary-foreground' : ''">
        <input v-model="value" type="radio" :name="name" :value="0" aria-label="0 estrelas" class="absolute inset-0 h-full w-full cursor-pointer opacity-0 disabled:cursor-default" />0
      </label>
      <div class="flex">
        <span v-for="star in 5" :key="star" class="relative block size-9 text-amber-600">
          <Star class="absolute inset-1 size-7 opacity-40" aria-hidden="true" />
          <span class="pointer-events-none absolute inset-y-1 left-1 overflow-hidden" :style="{ width: `${Math.max(0, Math.min(1, (value ?? 0) - star + 1)) * 28}px` }"><Star class="size-7 fill-current" aria-hidden="true" /></span>
          <label v-for="half in [0.5, 1]" :key="half" class="absolute inset-y-0 w-1/2 cursor-pointer rounded-sm focus-within:ring-2 focus-within:ring-ring" :class="half === 0.5 ? 'left-0' : 'right-0'">
            <input v-model="value" type="radio" :name="name" :value="star - 1 + half" :aria-label="`${formatRating(star - 1 + half)} estrelas`" class="absolute inset-0 h-full w-full cursor-pointer opacity-0 disabled:cursor-default" />
          </label>
        </span>
      </div>
      <span class="text-sm tabular-nums" aria-live="polite">{{ value === null ? 'Selecione uma nota' : `${formatRating(value)}/5` }}</span>
    </div>
    <p id="rating-help" class="text-xs leading-5 text-muted-foreground">Escolha meia estrela ou uma estrela inteira. Use as setas do teclado para ajustar. Zero também é uma nota válida.</p>
  </fieldset>
</template>
