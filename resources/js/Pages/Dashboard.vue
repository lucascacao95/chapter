<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { ArrowUpRight, BookOpen } from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ActivityFeed from '@/Components/Feed/ActivityFeed.vue'
import BookPreview from '@/Components/Feed/BookPreview.vue'
import type { RecentBook } from '@/types/feed'
defineProps<{ recent_books: RecentBook[] }>()
const page = usePage<{ auth: { user: { name: string } } }>()
</script>
<template>
  <AppLayout title="Início">
    <div class="mx-auto w-full max-w-3xl">
      <p class="text-[11px] font-semibold uppercase tracking-[.18em] text-[#58745b]">Seu espaço de leitura</p>
      <h1 class="mt-3 break-words font-serif text-3xl tracking-tight sm:text-4xl">Olá, {{ page.props.auth.user.name }}.</h1>
      <p class="mt-3 text-sm leading-6 text-muted-foreground">Sua biblioteca e as leituras que conectam você a outras pessoas.</p>
      <section aria-label="Minha biblioteca" class="mt-8 rounded-2xl border border-border bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><BookOpen class="size-4 text-primary" aria-hidden="true" /><h2 class="font-serif text-xl">Minha biblioteca</h2></div><Link href="/books" class="inline-flex items-center gap-1 rounded-sm text-xs font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Acessar minha biblioteca<ArrowUpRight class="size-3.5" aria-hidden="true" /></Link></div>
        <p v-if="recent_books.length" class="mt-2 text-xs text-muted-foreground">Seus livros atualizados mais recentemente.</p>
        <div v-if="recent_books.length" class="mt-5 grid grid-cols-3 gap-3 sm:gap-5"><div v-for="book in recent_books" :key="book.id" data-testid="recent-book" class="min-w-0"><div class="w-20 max-w-full"><BookPreview :book="book" /></div><h3 class="mt-3 line-clamp-2 break-words text-xs font-semibold leading-5">{{ book.title }}</h3><p class="mt-1 line-clamp-2 break-words text-[11px] leading-4 text-muted-foreground">{{ book.authors?.join(', ') || 'Autoria não informada' }}</p></div></div>
        <div v-else class="py-5 text-sm leading-6 text-muted-foreground"><p>Sua biblioteca ainda está vazia.</p><Link href="/books/create" class="mt-3 inline-flex font-medium text-primary underline underline-offset-4">Adicionar primeiro livro</Link></div>
      </section>
      <ActivityFeed />
    </div>
  </AppLayout>
</template>
