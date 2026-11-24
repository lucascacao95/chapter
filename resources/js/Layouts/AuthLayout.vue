<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
defineProps<{ title: string; description: string }>()
const page = usePage<{ status?: string }>()
</script>

<template>
  <Head :title="title" />
  <main class="grid min-h-screen bg-[#f8f7f3] text-[#22251f] lg:grid-cols-2">
    <section class="hidden flex-col justify-between bg-[#34543a] p-16 text-[#f8f7f3] lg:flex">
      <Link href="/" class="text-lg font-semibold">minha biblioteca</Link>
      <div class="max-w-lg">
        <p class="text-xs uppercase tracking-widest text-[#c4d2be]">Seu espaço de leitura</p>
        <h1 class="mt-5 font-serif text-5xl leading-tight">As suas próximas histórias começam aqui.</h1>
        <p class="mt-5 text-sm text-[#d3ddd0]">Sua biblioteca pessoal, sempre por perto.</p>
      </div>
      <p class="text-xs text-[#c4d2be]">Um projeto pessoal para leitores.</p>
    </section>
    <section class="flex min-h-screen flex-col px-6 py-7 sm:px-10 lg:px-16">
      <Link href="/" class="self-end text-sm text-[#34543a] underline underline-offset-4">Voltar ao início</Link>
      <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-14">
        <p class="text-xs font-semibold uppercase tracking-widest text-[#66816a]">Minha Biblioteca</p>
        <h2 class="mt-3 font-serif text-4xl tracking-tight">{{ title }}</h2>
        <p class="mt-3 text-sm leading-6 text-[#62665d]">{{ description }}</p>
        <p v-if="page.props.status" role="status" class="mt-6 rounded-xl bg-[#dce8d9] p-4 text-sm text-[#34543a]">{{ page.props.status }}</p>
        <slot />
      </div>
      <p class="text-center text-xs text-[#62665d]">Minha Biblioteca · Feito para leitores.</p>
    </section>
  </main>
</template>
