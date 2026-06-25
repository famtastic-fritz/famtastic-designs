<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('packages-content', () => getContent());
const content = computed(() => data.value || fallback);
const packages = computed(() => [...(content.value.packages || [])].sort((a, b) => a.sortOrder - b.sortOrder));

useSeoMeta({
  title: 'Packages | FAMtastic Designs',
  description: 'Package ladder for FAMtastic Designs including starter sites, growth builds, landing pages, portals, and care plans.',
  ogTitle: 'Packages | FAMtastic Designs',
  ogDescription: 'Package ladder for FAMtastic Designs including starter sites, growth builds, landing pages, portals, and care plans.',
});
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Packages</p>
      <h1 class="mt-3 text-4xl font-black text-white">Website packages with clear entry points and honest next steps.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">This page mirrors the live offer ladder in the proof branch so owners can validate names, price language, scope framing, and CTA fit without pretending payment is active.</p>
    </div>

    <div class="mt-10 grid gap-4 lg:grid-cols-3">
      <article v-for="plan in packages" :key="plan.packageName" :class="plan.highlighted ? 'border-[#79FF00]/35 bg-[#79FF00]/8' : 'border-white/8 bg-[#0D1210]'" class="rounded-[28px] border p-7">
        <p class="text-sm font-semibold text-white">{{ plan.packageName }}</p>
        <p class="mt-3 text-3xl font-black text-white">{{ plan.priceLabel }}</p>
        <p class="mt-4 text-sm leading-7 text-white/72">{{ plan.description }}</p>
        <p class="mt-4 text-xs uppercase tracking-[0.2em] text-[#79FF00]">Best for</p>
        <p class="mt-2 text-sm text-white/72">{{ plan.bestFor }}</p>
        <ul class="mt-6 grid gap-3 text-sm text-white/78">
          <li v-for="feature in plan.features" :key="feature" class="flex gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-[#79FF00]" /> <span>{{ feature }}</span></li>
        </ul>
        <p v-if="plan.paymentOptions?.length" class="mt-4 text-xs leading-6 text-white/60">Future payment path: {{ plan.paymentOptions.join(' • ') }}</p>
        <NuxtLink :to="`/get-started?package=${encodeURIComponent(plan.packageName)}`" class="mt-6 inline-flex rounded-full bg-white px-5 py-3 text-sm font-bold text-[#050807]">{{ plan.cta }}</NuxtLink>
      </article>
    </div>
  </div>
</template>
