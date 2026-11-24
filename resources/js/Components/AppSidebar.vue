<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { BookOpen, LibraryBig, LayoutDashboard, LogOut } from '@lucide/vue'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  useSidebar,
} from '@/Components/ui/sidebar'

defineProps<{
  currentPath: string
  user: { name: string; email: string }
}>()

const { setOpenMobile } = useSidebar()
</script>

<template>
  <Sidebar collapsible="icon" variant="inset" class="border-0">
    <SidebarHeader class="px-3 pb-2 pt-5">
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton as-child size="lg" class="h-12 hover:bg-transparent">
            <Link href="/dashboard" aria-label="Minha Biblioteca, início" @click="setOpenMobile(false)">
              <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-[#dce8d9] text-[#34543a]">
                <BookOpen class="size-[18px]" aria-hidden="true" />
              </span>
              <span class="flex min-w-0 flex-col leading-tight">
                <span class="truncate text-sm font-semibold tracking-tight">minha biblioteca</span>
                <span class="mt-1 truncate text-[11px] text-[#77796f]">seu espaço de leitura</span>
              </span>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent class="px-2 pt-5">
      <SidebarGroup>
        <SidebarGroupLabel class="px-2 text-[10px] font-semibold uppercase tracking-[.16em] text-[#85877d]">
          Biblioteca
        </SidebarGroupLabel>
        <SidebarGroupContent>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton
                as-child
                tooltip="Início"
                :is-active="currentPath === '/dashboard'"
                class="h-10 rounded-xl px-3"
              >
                <Link href="/dashboard" :aria-current="currentPath === '/dashboard' ? 'page' : undefined" @click="setOpenMobile(false)">
                  <LayoutDashboard class="size-[17px]" aria-hidden="true" />
                  <span>Início</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
              <SidebarMenuButton as-child tooltip="Meus livros" :is-active="currentPath === '/books' || currentPath.startsWith('/books/')" class="h-10 rounded-xl px-3">
                <Link href="/books" :aria-current="currentPath === '/books' || currentPath.startsWith('/books/') ? 'page' : undefined" @click="setOpenMobile(false)">
                  <LibraryBig class="size-[17px]" aria-hidden="true" />
                  <span>Meus livros</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroupContent>
      </SidebarGroup>
    </SidebarContent>

    <SidebarFooter class="border-t border-sidebar-border px-2 py-3">
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton as-child size="lg" class="h-auto min-h-12 rounded-xl px-2">
            <div>
              <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#e1e5dc] text-xs font-semibold text-[#34543a]" aria-hidden="true">
                {{ user.name.slice(0, 1).toLocaleUpperCase('pt-BR') }}
              </span>
              <span class="flex min-w-0 flex-1 flex-col leading-tight">
                <span class="truncate text-xs font-medium">{{ user.name }}</span>
                <span class="mt-1 truncate text-[10px] text-[#77796f]">{{ user.email }}</span>
              </span>
            </div>
          </SidebarMenuButton>
        </SidebarMenuItem>
        <SidebarMenuItem>
          <SidebarMenuButton as-child tooltip="Sair" class="h-9 rounded-xl px-3 text-[#62665d]">
            <Link href="/logout" method="post" as="button" class="w-full" aria-label="Sair da conta">
              <LogOut class="size-4" aria-hidden="true" />
              <span>Sair</span>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarFooter>

    <SidebarRail />
  </Sidebar>
</template>
