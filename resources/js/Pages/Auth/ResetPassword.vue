<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthField from '../../Components/AuthField.vue'
const props = defineProps<{ token: string; email: string }>()
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' })
const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') })
</script>

<template>
  <AuthLayout title="Redefinir senha" description="Escolha sua nova senha para acessar sua conta.">
    <form class="mt-9 space-y-5" @submit.prevent="submit">
      <p v-if="form.errors.token" role="alert" class="text-sm text-red-700">{{ form.errors.token }}</p>
      <AuthField v-model="form.email" name="email" label="E-mail" type="email" autocomplete="email" :error="form.errors.email" />
<AuthField v-model="form.password" name="password" label="Nova senha" type="password" autocomplete="new-password" :error="form.errors.password" />
<AuthField v-model="form.password_confirmation" name="password_confirmation" label="Confirmar nova senha" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
      <p class="text-xs text-[#62665d]">Use uma senha com pelo menos 8 caracteres.</p>
      <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-[#34543a] px-6 py-3.5 text-sm font-medium text-white hover:bg-[#29452f] disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Aguarde…' : 'Salvar nova senha' }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-[#62665d]"><Link href="/forgot-password" class="text-[#34543a] underline">Solicitar outro link</Link></p>
  </AuthLayout>
</template>
