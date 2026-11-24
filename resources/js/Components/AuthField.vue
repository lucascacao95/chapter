<script setup lang="ts">
const model = defineModel<string>({ required: true })
withDefaults(defineProps<{ name: string; label: string; type?: string; autocomplete: string; error?: string; minlength?: number; maxlength?: number }>(), { type: 'text' })
</script>

<template>
  <div>
    <label :for="name" class="mb-2 block text-sm font-medium">{{ label }}</label>
    <input :id="name" v-model="model" :name="name" :type="type" :autocomplete="autocomplete" :minlength="minlength" :maxlength="maxlength" required :aria-invalid="!!error" :aria-describedby="error ? `${name}-error` : undefined" class="w-full rounded-xl border border-[#c7c9bd] bg-white px-4 py-3 text-sm outline-none focus:border-[#66816a] focus:ring-2 focus:ring-[#66816a]/30">
    <p v-if="error" :id="`${name}-error`" role="alert" class="mt-2 text-sm text-red-700">{{ error }}</p>
  </div>
</template>
