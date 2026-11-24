<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from 'reka-ui'
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from 'reka-ui'
const props = defineProps<SwitchRootProps>()
const emit = defineEmits<SwitchRootEmits>()
const forwarded = useForwardPropsEmits(props, emit)
</script>
<template>
  <SwitchRoot v-bind="forwarded" class="peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent bg-input transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary">
    <SwitchThumb class="pointer-events-none block size-5 rounded-full bg-white shadow-sm transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0" />
  </SwitchRoot>
</template>
