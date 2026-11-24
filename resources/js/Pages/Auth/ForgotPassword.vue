<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthField from '../../Components/AuthField.vue'

const form = useForm({ email: '' })
const submit = () => form.post('/forgot-password')
</script>

<template>
  <AuthLayout title="Recuperar senha" description="Informe seu e-mail para solicitar um link de recuperação.">
    <form class="mt-9 space-y-5" @submit.prevent="submit">
      <AuthField v-model="form.email" name="email" label="E-mail" type="email" autocomplete="email" :error="form.errors.email" />
      
      <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-[#34543a] px-6 py-3.5 text-sm font-medium text-white hover:bg-[#29452f] disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Aguarde…' : 'Enviar link' }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-[#62665d]"><Link href="/login" class="text-[#34543a] underline">Voltar ao login</Link></p>
  </AuthLayout>
</template>
