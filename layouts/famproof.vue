<script setup lang="ts">
import { company, nav } from '~/data/famtastic-proof';
</script>

<template>
  <div class="min-h-screen bg-[#050807] text-white">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(121,255,0,0.16),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(56,189,248,0.12),transparent_28%)] pointer-events-none" />
    <header class="sticky top-0 z-30 border-b border-white/10 bg-[#050807]/90 backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <NuxtLink to="/" class="flex items-center gap-2 text-sm font-semibold tracking-[0.18em] uppercase">
          <span class="text-[#79FF00]">FAM</span>
          <span class="text-white">tastic Designs</span>
        </NuxtLink>
        <nav class="hidden items-center gap-6 text-sm text-white/78 md:flex">
          <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" class="transition hover:text-[#79FF00]">{{ item.label }}</NuxtLink>
        </nav>
        <div class="flex items-center gap-2">
          <NuxtLink to="/client-portal-login" class="hidden rounded-full border border-white/12 px-4 py-2 text-sm text-white/80 transition hover:border-[#79FF00]/50 hover:text-white sm:inline-flex">Client Portal</NuxtLink>
          <NuxtLink to="/get-started" class="inline-flex rounded-full bg-[#79FF00] px-4 py-2 text-sm font-semibold text-[#050807] transition hover:bg-[#93ff33]">Get Started</NuxtLink>
        </div>
      </div>
      <div class="border-t border-white/5 px-4 py-2 md:hidden">
        <div class="mx-auto flex max-w-7xl gap-4 overflow-x-auto text-sm text-white/70">
          <NuxtLink v-for="item in nav" :key="`${item.to}-mobile`" :to="item.to" class="whitespace-nowrap transition hover:text-[#79FF00]">{{ item.label }}</NuxtLink>
        </div>
      </div>
    </header>

    <main class="relative">
      <slot />
    </main>

    <footer class="relative border-t border-white/10 bg-[#070b09]">
      <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.3fr_1fr_1fr] lg:px-8">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.22em] text-[#79FF00]">FAMtastic Designs</p>
          <p class="mt-3 max-w-xl text-sm leading-7 text-white/72">{{ company.tagline }}</p>
          <div class="mt-6 flex flex-wrap gap-3 text-xs text-white/60">
            <span class="rounded-full border border-[#79FF00]/20 px-3 py-1">Mobile-First</span>
            <span class="rounded-full border border-[#79FF00]/20 px-3 py-1">Conversion-Focused</span>
            <span class="rounded-full border border-[#79FF00]/20 px-3 py-1">Portal Ready</span>
          </div>
        </div>
        <div>
          <p class="text-sm font-semibold text-white">Pages</p>
          <div class="mt-4 grid gap-3 text-sm text-white/72">
            <NuxtLink v-for="item in nav" :key="`${item.to}-footer`" :to="item.to" class="transition hover:text-[#79FF00]">{{ item.label }}</NuxtLink>
          </div>
        </div>
        <div>
          <p class="text-sm font-semibold text-white">Contact</p>
          <div class="mt-4 grid gap-3 text-sm text-white/72">
            <a :href="`mailto:${company.email}`" class="transition hover:text-[#79FF00]">{{ company.email }}</a>
            <a :href="`https://${company.domain}`" target="_blank" rel="noreferrer" class="transition hover:text-[#79FF00]">{{ company.domain }}</a>
            <NuxtLink to="/get-started" class="transition hover:text-[#79FF00]">Start your project</NuxtLink>
            <NuxtLink to="/client-portal-login" class="transition hover:text-[#79FF00]">Client portal login</NuxtLink>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
