<script setup lang="ts">
import { useId } from 'vue'
import { Switch } from '@/Components/ui/switch'
const value = defineModel<boolean>({ required: true })
defineProps<{ kind: 'book' | 'rating'; disabled?: boolean; error?: string }>()
const id = useId()
</script>
<template>
  <div class="rounded-xl border border-border bg-accent/20 p-4">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-2">
        <label :for="id" class="cursor-pointer text-sm font-medium leading-5">{{ kind === 'rating' ? 'Compartilhar esta avaliação no feed' : 'Compartilhar a adição deste livro no feed' }}</label>
        <p :id="`${id}-help`" class="text-xs leading-5 text-muted-foreground">Seus seguidores atuais e futuros verão {{ kind === 'rating' ? 'a nota, o comentário, o título, a autoria e a capa' : 'o título, a autoria e a capa' }}. O arquivo continua privado. {{ kind === 'book' ? 'Publicaremos após concluir o processamento, quando houver arquivo.' : 'Quando houver arquivo, publicaremos após concluir o processamento.' }}</p>
      </div>
      <Switch :id="id" v-model="value" :disabled="disabled" :aria-invalid="!!error" :aria-describedby="`${id}-help${error ? ` ${id}-error` : ''}`" />
    </div>
    <p v-if="error" :id="`${id}-error`" role="alert" class="mt-2 text-sm text-destructive">{{ error }}</p>
  </div>
</template>
