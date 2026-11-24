<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import AppSidebar from '@/Components/AppSidebar.vue'
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/Components/ui/sidebar'

defineProps<{ title: string }>()
const page = usePage<{ auth: { user: { id: number; name: string; email: string } } }>()
</script>

<template>
  <Head :title="title" />
  <SidebarProvider class="bg-background text-foreground">
    <AppSidebar :current-path="page.url.split('?')[0]" :user="page.props.auth.user" />
    <SidebarInset class="min-h-svh min-w-0 bg-background shadow-none">
      <header class="flex h-[4.5rem] shrink-0 items-center gap-3 border-b border-border px-4 sm:px-7">
        <SidebarTrigger class="size-9 rounded-xl text-[#52634f] hover:bg-accent" />
        <span class="text-sm font-medium text-muted-foreground">{{ title }}</span>
      </header>
      <div class="mx-auto w-full max-w-7xl flex-1 px-5 py-9 sm:px-8 sm:py-12 lg:px-12">
        <slot />
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>
