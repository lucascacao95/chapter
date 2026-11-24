<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthField from '../../Components/AuthField.vue'

const form = useForm({ email: '', password: '' })
const submit = () => form.post('/login', { onFinish: () => form.reset('password') })
</script>

<template>
  <AuthLayout title="Entrar na sua conta" description="Entre com seu e-mail e senha.">
    <form class="mt-9 space-y-5" @submit.prevent="submit">
      <AuthField v-model="form.email" name="email" label="E-mail" type="email" autocomplete="email" :error="form.errors.email" />
<AuthField v-model="form.password" name="password" label="Senha" type="password" autocomplete="current-password" :error="form.errors.password" />
      <Link href="/forgot-password" class="block text-sm text-[#34543a] underline">Esqueci minha senha</Link>
      <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-[#34543a] px-6 py-3.5 text-sm font-medium text-white hover:bg-[#29452f] disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Aguarde…' : 'Entrar' }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-[#62665d]">Ainda não tem conta? <Link href="/register" class="text-[#34543a] underline">Criar conta</Link></p>
  </AuthLayout>
</template>
