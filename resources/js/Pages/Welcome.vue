<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
const page = usePage<{ auth: { user: { id: number; name: string; email: string } | null } }>()
const features = [
  { number: '01', title: 'Sua biblioteca, em um só lugar', description: 'Organize seus livros digitais e encontre o que quer ler sem complicação.' },
  { number: '02', title: 'Feita para a sua rotina', description: 'Acesse sua coleção de onde estiver, pelo computador ou celular.' },
  { number: '03', title: 'Leitura no seu Kindle', description: 'Estamos preparando um jeito simples de enviar seus arquivos ao dispositivo.' },
]
</script>

<template>
  <main class="min-h-screen overflow-hidden bg-[#f8f7f3] text-[#22251f]">
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-7 lg:px-12">
      <a href="/" class="flex items-center gap-3" aria-label="Minha Biblioteca, início">
        <span class="grid size-10 place-items-center rounded-xl bg-[#dce8d9] text-[#34543a]">
          <svg viewBox="0 0 24 24" fill="none" class="size-5" aria-hidden="true"><path d="M5 4.75A1.75 1.75 0 0 1 6.75 3H20v16H6.75A1.75 1.75 0 0 0 5 20.75v-16Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M5 17.5c.44-.65 1.05-1 1.85-1H20M9 7h7M9 10h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </span>
        <span class="text-sm font-semibold tracking-tight">minha biblioteca</span>
      </a>
      <div class="flex items-center gap-4">
        <Link :href="page.props.auth.user ? '/dashboard' : '/login'" class="text-sm font-medium text-[#34543a] transition hover:text-[#29452f]">{{ page.props.auth.user ? 'Minha conta' : 'Entrar' }}</Link>
        <span class="rounded-full border border-[#d8d8cf] px-4 py-2 text-xs font-medium text-[#62665d]">Projeto em construção</span>
      </div>
    </nav>

    <section class="mx-auto grid max-w-7xl items-center gap-12 px-6 pb-20 pt-12 md:pt-20 lg:grid-cols-[1.05fr_.95fr] lg:px-12 lg:pb-28 lg:pt-24">
      <div class="max-w-2xl">
        <p class="mb-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[.18em] text-[#58745b]"><span class="size-2 rounded-full bg-[#789878]"></span>Um espaço seu para ler</p>
        <h1 class="font-serif text-5xl leading-[1.08] tracking-[-.045em] sm:text-6xl lg:text-[4.5rem]">Todos os seus livros.<br><span class="italic text-[#66816a]">Mais perto de você.</span></h1>
        <p class="mt-7 max-w-lg text-base leading-7 text-[#6a6c63]">Uma biblioteca digital pessoal para organizar seus arquivos e levar suas próximas leituras para onde quiser.</p>
        <div class="mt-9 flex flex-wrap items-center gap-4">
          <a href="#sobre" class="rounded-full bg-[#34543a] px-6 py-3.5 text-sm font-medium text-white transition hover:bg-[#29452f]">Conheça o projeto <span aria-hidden="true">↗</span></a>
          <span class="text-xs text-[#77796f]">Simples, pessoal e feita para leitores.</span>
        </div>
        <div class="mt-14 flex items-center gap-4 border-t border-[#e2e0d7] pt-6 text-xs text-[#77796f]"><span class="grid size-8 place-items-center rounded-full border border-[#dfded5] text-[#58745b]">✳</span>Seu acervo, organizado do seu jeito.</div>
      </div>

      <div class="relative mx-auto w-full max-w-[520px]">
        <div class="absolute -right-8 -top-7 size-28 rounded-full bg-[#e8e6d8] blur-2xl"></div>
        <div class="relative rounded-[2rem] border border-[#e7e4da] bg-[#eeede6] p-5 shadow-[0_24px_70px_-36px_rgba(40,50,37,.36)] sm:p-8">
          <div class="flex items-center justify-between px-1 pb-6"><div><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#8b8d82]">um cantinho de leitura</p><p class="mt-1 font-serif text-xl">Sua estante</p></div><span class="rounded-full bg-white/70 px-3 py-1.5 text-[10px] text-[#77796f]">minha coleção</span></div>
          <div class="grid grid-cols-3 gap-3 sm:gap-4">
            <div class="book-cover cover-one"><span>01</span><div><small>UMA HISTÓRIA SOBRE</small><strong>o tempo<br>das coisas</strong><i>romance</i></div></div>
            <div class="book-cover cover-two"><span>02</span><div><small>PEQUENAS</small><strong>descobertas<br>cotidianas</strong><i>ensaios</i></div></div>
            <div class="book-cover cover-three"><span>03</span><div><small>CAMINHOS PARA</small><strong>novos<br>horizontes</strong><i>viagem</i></div></div>
          </div>
          <div class="mt-5 flex items-center justify-between rounded-2xl bg-white/65 px-4 py-3"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-[#e6ebdf] text-[#58745b]">⌕</span><div><p class="text-xs font-medium">Tudo no seu lugar</p><p class="mt-0.5 text-[10px] text-[#898b81]">Uma biblioteca que acompanha você</p></div></div><span class="text-sm text-[#8c9285]">→</span></div>
        </div>
        <div class="absolute -bottom-5 -left-5 hidden rounded-2xl border border-[#e7e4da] bg-[#fbfaf6] px-4 py-3 shadow-lg sm:block"><p class="text-[9px] uppercase tracking-[.15em] text-[#8b8d82]">feito para leitores</p><p class="mt-1 font-serif text-sm">Um livro de cada vez.</p></div>
      </div>
    </section>

    <section id="sobre" class="border-t border-[#e7e4da] bg-[#fbfaf6]">
      <div class="mx-auto max-w-7xl px-6 py-16 lg:px-12 lg:py-20">
        <div class="mb-10 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-semibold uppercase tracking-[.18em] text-[#66816a]">Começando com o essencial</p><h2 class="mt-3 font-serif text-3xl tracking-tight">Um lugar para chamar de seu.</h2></div><p class="max-w-sm text-sm leading-6 text-[#77796f]">Estamos construindo uma forma mais tranquila de cuidar dos livros que acompanham você.</p></div>
        <div class="grid gap-4 md:grid-cols-3"> <article v-for="feature in features" :key="feature.number" class="rounded-2xl border border-[#e8e6de] bg-[#f8f7f3] p-6"><span class="text-xs font-medium text-[#82917c]">{{ feature.number }}</span><h3 class="mt-7 font-serif text-xl">{{ feature.title }}</h3><p class="mt-2 text-sm leading-6 text-[#77796f]">{{ feature.description }}</p></article></div>
      </div>
    </section>
    <footer class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-6 text-xs text-[#898b81] sm:flex-row sm:items-center sm:justify-between lg:px-12"><span>Minha Biblioteca · Um projeto pessoal para leitores.</span><span>Feito com carinho no Brasil.</span></footer>
  </main>
</template>

<style scoped>
.book-cover { aspect-ratio: 0.68; border-radius: 8px 12px 12px 8px; padding: 10px; display:flex; flex-direction:column; justify-content:space-between; color:#f8f5e9; box-shadow:inset 4px 0 8px #00000013, 0 8px 14px #4143301c; }
.book-cover span { font-size:8px; opacity:.75; }
.book-cover div { display:flex; flex-direction:column; gap:7px; }
.book-cover small { font-size:6px; letter-spacing:.12em; opacity:.8; }
.book-cover strong { font-family:Georgia,serif; font-size:clamp(12px,2.5vw,19px); font-weight:400; line-height:1.08; }
.book-cover i { font-size:7px; font-style:normal; opacity:.8; }
.cover-one { background:linear-gradient(145deg,#75856c,#3e5744); }
.cover-two { background:linear-gradient(145deg,#ba8b68,#805a45); }
.cover-three { background:linear-gradient(145deg,#798a91,#455c64); }
</style>
