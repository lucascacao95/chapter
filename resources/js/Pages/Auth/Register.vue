<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AuthLayout from '../../Layouts/AuthLayout.vue'
import AuthField from '../../Components/AuthField.vue'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })
const submit = () => form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') })
</script>

<template>
  <AuthLayout title="Criar sua conta" description="Cadastre-se para acessar sua biblioteca pessoal.">
    <form class="mt-9 space-y-5" @submit.prevent="submit">
      <AuthField v-model="form.name" name="name" label="Nome" type="text" autocomplete="name" :error="form.errors.name" />
<AuthField v-model="form.email" name="email" label="E-mail" type="email" autocomplete="email" :error="form.errors.email" />
<AuthField v-model="form.password" name="password" label="Senha" type="password" autocomplete="new-password" :error="form.errors.password" />
<AuthField v-model="form.password_confirmation" name="password_confirmation" label="Confirmar senha" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
      <p class="text-xs text-[#62665d]">Use uma senha com pelo menos 8 caracteres.</p>
      <button type="submit" :disabled="form.processing" class="w-full rounded-full bg-[#34543a] px-6 py-3.5 text-sm font-medium text-white hover:bg-[#29452f] disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Aguarde…' : 'Criar conta' }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-[#62665d]">Já tem conta? <Link href="/login" class="text-[#34543a] underline">Entrar</Link></p>
  </AuthLayout>
</template>
