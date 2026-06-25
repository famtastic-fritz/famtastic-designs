<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('pricing-content', () => getContent());
const content = computed(() => data.value || fallback);
const packages = computed(() => [...(content.value.packages || [])].sort((a, b) => a.sortOrder - b.sortOrder));
const carePlans = computed(() => content.value.paymentPlaceholders.carePlans || []);

useSeoMeta({
  title: content.value.seo.pages.packages.title,
  description: content.value.seo.pages.packages.description,
  ogTitle: content.value.seo.pages.packages.title,
  ogDescription: content.value.seo.pages.packages.description,
});
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Pricing</p>
      <h1 class="mt-3 text-4xl font-black text-white">Clear package lanes, not vague agency fog.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">Starting prices show how a buyer can enter at different levels. Final scope gets confirmed after consultation.</p>
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
        <p class="mt-5 text-xs text-white/45">Final scope confirmed after consultation.</p>
        <p v-if="plan.paymentOptions?.length" class="mt-3 text-xs leading-6 text-white/60">Payment options are finalized during consultation: {{ plan.paymentOptions.join(' • ') }}</p>
        <NuxtLink :to="`/get-started?package=${encodeURIComponent(plan.packageName)}`" class="mt-6 inline-flex rounded-full bg-white px-5 py-3 text-sm font-bold text-[#050807]">{{ plan.cta }}</NuxtLink>
      </article>
    </div>

    <div class="mt-12 rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Care plans</p>
          <h2 class="mt-3 text-2xl font-bold text-white">Ongoing support after launch.</h2>
        </div>
        <p class="text-sm text-white/60">Recurring billing stays in mock mode locally until a real PayPal care-plan path is approved and verified.</p>
      </div>
      <div class="mt-6 grid gap-4 md:grid-cols-3">
        <article v-for="plan in carePlans" :key="plan.name" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <p class="text-lg font-semibold text-white">{{ plan.name }}</p>
          <p class="mt-2 text-2xl font-black text-white">{{ plan.price }}</p>
        </article>
      </div>
    </div>
  </div>
</template>
