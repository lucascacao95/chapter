<script setup lang="ts">
import type { DropdownMenuItemEmits, DropdownMenuItemProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { DropdownMenuItem, useForwardPropsEmits } from 'reka-ui'
import { cn } from 'cn'
const props = defineProps<DropdownMenuItemProps & { class?: HTMLAttributes['class'] }>()
const emit = defineEmits<DropdownMenuItemEmits>()
const delegated = computed(() => { const { class: omitted, ...rest } = props; return rest })
const forwarded = useForwardPropsEmits(delegated, emit)
</script>
<template><DropdownMenuItem v-bind="forwarded" :class="cn('relative flex cursor-default select-none items-center gap-2 rounded-lg px-3 py-2 outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0', props.class)"><slot /></DropdownMenuItem></template>
