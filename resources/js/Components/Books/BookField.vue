<script setup lang="ts">
import { computed } from 'vue'
import { Label } from '@/Components/ui/label'

const props = defineProps<{ inputId: string; label: string; required?: boolean; error?: string; hint?: string }>()
const attributes = computed(() => ({
  id: props.inputId,
  name: props.inputId,
  required: props.required,
  'aria-invalid': !!props.error,
  'aria-describedby': [props.hint ? `${props.inputId}-hint` : '', props.error ? `${props.inputId}-error` : ''].filter(Boolean).join(' ') || undefined,
}))
</script>

<template>
  <div class="min-w-0 space-y-2">
    <Label :for="inputId" class="text-sm font-medium">
      {{ label }}<span v-if="required" class="text-destructive" aria-hidden="true">*</span>
    </Label>
    <slot :attributes="attributes" />
    <p v-if="hint" :id="`${inputId}-hint`" class="text-xs leading-5 text-muted-foreground">{{ hint }}</p>
    <p v-if="error" :id="`${inputId}-error`" role="alert" class="text-sm text-destructive">{{ error }}</p>
  </div>
</template>
