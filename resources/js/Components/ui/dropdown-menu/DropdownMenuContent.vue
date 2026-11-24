<script setup lang="ts">
import type { DropdownMenuContentEmits, DropdownMenuContentProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { DropdownMenuContent, DropdownMenuPortal, useForwardPropsEmits } from 'reka-ui'
import { cn } from 'cn'
const props = withDefaults(defineProps<DropdownMenuContentProps & { class?: HTMLAttributes['class'] }>(), { sideOffset: 4 })
const emit = defineEmits<DropdownMenuContentEmits>()
const delegated = computed(() => { const { class: omitted, ...rest } = props; return rest })
const forwarded = useForwardPropsEmits(delegated, emit)
</script>
<template>
  <DropdownMenuPortal><DropdownMenuContent v-bind="forwarded" :class="cn('z-50 min-w-48 max-w-[calc(100vw-2rem)] rounded-xl border border-border bg-white p-1 text-sm text-foreground shadow-md outline-none', props.class)"><slot /></DropdownMenuContent></DropdownMenuPortal>
</template>
