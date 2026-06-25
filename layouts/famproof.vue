<script setup lang="ts">
import CookieBanner from '~/components/CookieBanner.vue';
import { resolveAuditPaymentLink, resolveBookingLink, isExternalLink } from '~/app/utils/proof-links';
import { useFamtasticContent } from '~/composables/useFamtasticContent';

const config = useRuntimeConfig();
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('layout-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);
const nav = computed(() => content.value.navigation || []);
const footerLinks = computed(() => content.value.footerLinks || []);
const bookingHref = computed(() => resolveBookingLink(config.public, site.value));
const auditHref = computed(() => resolveAuditPaymentLink(config.public, site.value));
const bookingExternal = computed(() => isExternalLink(bookingHref.value));
const auditExternal = computed(() => isExternalLink(auditHref.value));
</script>

<template>
  <div class="min-h-screen bg-[#050807] text-white">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(121,255,0,0.16),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(56,189,248,0.12),transparent_28%)]" />

    <div class="relative border-b border-white/8 bg-[#0b100d] text-xs text-white/72">
      <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
        <div class="flex flex-wrap items-center gap-4">
          <span>{{ site.utilityBarText }}</span>
          <a v-if="site.phone" :href="`tel:${site.phone}`" class="transition hover:text-[#79FF00]">{{ site.phone }}</a>
          <a :href="`mailto:${site.contactEmail}`" class="transition hover:text-[#79FF00]">{{ site.contactEmail }}</a>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <a v-if="bookingExternal" :href="bookingHref" target="_blank" rel="noreferrer" class="transition hover:text-[#79FF00]">Free Website Consultation</a>
          <NuxtLink v-else :to="bookingHref" class="transition hover:text-[#79FF00]">Free Website Consultation</NuxtLink>
          <NuxtLink :to="site.portalLink" class="transition hover:text-[#79FF00]">{{ site.portalLabel }}</NuxtLink>
          <NuxtLink to="/get-started" class="rounded-full border border-white/10 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-white transition hover:border-[#79FF00]/50 hover:text-[#79FF00]">{{ site.fastQuoteLabel }}</NuxtLink>
        </div>
      </div>
    </div>

    <header class="sticky top-0 z-30 border-b border-white/10 bg-[#050807]/90 backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <NuxtLink to="/" class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em]">
          <span class="text-[#79FF00]">FAM</span>
          <span class="text-white">tastic Designs</span>
        </NuxtLink>
        <nav class="hidden items-center gap-6 text-sm text-white/78 md:flex">
          <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" class="transition hover:text-[#79FF00]">{{ item.label }}</NuxtLink>
        </nav>
        <div class="flex items-center gap-2">
          <NuxtLink :to="site.portalLink" class="hidden rounded-full border border-white/12 px-4 py-2 text-sm text-white/80 transition hover:border-[#79FF00]/50 hover:text-white sm:inline-flex">{{ site.portalLabel }}</NuxtLink>
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
      <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.1fr_0.9fr_1fr] lg:px-8">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.22em] text-[#79FF00]">{{ site.siteName }}</p>
          <p class="mt-3 max-w-xl text-sm leading-7 text-white/72">Professional websites, landing pages, CMS editing, lead capture, automation, and client systems for growing businesses.</p>
          <div class="mt-6 flex flex-wrap gap-3 text-xs text-white/60">
            <span class="rounded-full border border-[#79FF00]/20 px-3 py-1">Mobile-first</span>
            <span class="rounded-full border border-[#79FF00]/20 px-3 py-1">Lead-focused</span>
            <span class="rounded-full border border-[#79FF00]/20 px-3 py-1">CMS-ready</span>
          </div>
        </div>
        <div>
          <p class="text-sm font-semibold text-white">Links</p>
          <div class="mt-4 grid gap-3 text-sm text-white/72">
            <NuxtLink v-for="item in footerLinks" :key="`${item.to}-footer`" :to="item.to" class="transition hover:text-[#79FF00]">{{ item.label }}</NuxtLink>
          </div>
        </div>
        <div>
          <p class="text-sm font-semibold text-white">Contact</p>
          <div class="mt-4 grid gap-3 text-sm text-white/72">
            <a :href="`mailto:${site.contactEmail}`" class="transition hover:text-[#79FF00]">{{ site.contactEmail }}</a>
            <a v-if="site.phone" :href="`tel:${site.phone}`" class="transition hover:text-[#79FF00]">{{ site.phone }}</a>
            <a v-if="bookingExternal" :href="bookingHref" target="_blank" rel="noreferrer" class="transition hover:text-[#79FF00]">Request a Consultation</a>
            <NuxtLink v-else :to="bookingHref" class="transition hover:text-[#79FF00]">Request a Consultation</NuxtLink>
            <a v-if="auditExternal" :href="auditHref" target="_blank" rel="noreferrer" class="transition hover:text-[#79FF00]">Discuss Audit / Deposit Options</a>
            <NuxtLink v-else :to="auditHref" class="transition hover:text-[#79FF00]">Discuss Audit / Deposit Options</NuxtLink>
          </div>
          <p class="mt-6 text-xs text-white/45">© 2026 {{ site.siteName }}. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <CookieBanner />
  </div>
</template>
