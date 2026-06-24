<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('sitemap-page-content', () => getContent());
const content = computed(() => data.value || fallback);
const links = computed(() => content.value.footerLinks || []);
useSeoMeta({ title: 'Sitemap | FAMtastic Designs', description: 'HTML sitemap for the FAMtastic Designs local proof.' });
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8 lg:p-10">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Sitemap</p>
      <h1 class="mt-3 text-4xl font-black text-white">Browse the local proof routes.</h1>
      <div class="mt-8 grid gap-3 text-sm text-white/72 sm:grid-cols-2">
        <NuxtLink v-for="item in links" :key="item.to" :to="item.to" class="rounded-2xl border border-white/8 bg-[#0D1210] px-4 py-3 transition hover:text-[#79FF00]">{{ item.label }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
