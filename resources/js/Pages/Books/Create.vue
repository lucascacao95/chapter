<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ArrowLeft } from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AddBookPanel from '@/Components/Books/AddBookPanel.vue'
import { sharingLabel } from '@/types/feed'
import type { Book } from '@/types/books'

const selected = Number(new URL(window.location.href).searchParams.get('collection_id'))
const collectionId = Number.isSafeInteger(selected) && selected > 0 ? selected : null
function saved(book: Book) {
  const destination = book.collection_id === null ? '/books' : `/books?view=collections&collection=${book.collection_id}`
  const message = (book.file ? 'Arquivo recebido. Os detalhes serão extraídos em segundo plano.' : 'Livro cadastrado com sucesso.') + (book.feed_activity ? ` ${sharingLabel(book.feed_activity)}` : '')
  router.visit(destination, { onSuccess: () => router.flash('success', message) })
}
</script>

<template>
  <AppLayout title="Adicionar livro">
    <div class="mx-auto max-w-3xl">
      <Link href="/books" class="inline-flex items-center gap-2 rounded text-xs font-medium text-muted-foreground outline-none hover:text-primary focus-visible:ring-2 focus-visible:ring-ring"><ArrowLeft class="size-3.5" aria-hidden="true" />Meus livros</Link>
      <div class="mb-8 mt-6"><h1 class="font-serif text-4xl tracking-tight sm:text-5xl">Adicionar livro</h1><p class="mt-4 text-sm leading-6 text-muted-foreground">Envie um EPUB ou PDF para começar sua próxima leitura.</p></div>
      <AddBookPanel :collection-id="collectionId" @cancel="router.visit('/books')" @saved="saved" />
    </div>
  </AppLayout>
</template>
