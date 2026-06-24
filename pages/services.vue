<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('services-content', () => getContent());
const content = computed(() => data.value || fallback);
const services = computed(() => content.value.services || []);

useSeoMeta({
  title: content.value.seo.pages.services.title,
  description: content.value.seo.pages.services.description,
  ogTitle: content.value.seo.pages.services.title,
  ogDescription: content.value.seo.pages.services.description,
});
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Services</p>
      <h1 class="mt-3 text-4xl font-black text-white">Web design, development, CMS/admin setup, automation, and website care.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">This proof keeps the service stack direct: build a stronger website, tighten the lead flow, and create a cleaner client-facing experience.</p>
    </div>

    <div class="mt-12 grid gap-5">
      <article v-for="item in services" :id="item.slug" :key="item.slug" class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
        <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
          <div>
            <h2 class="text-2xl font-bold text-white">{{ item.title }}</h2>
            <p class="mt-4 text-sm leading-7 text-white/72">{{ item.shortDescription }}</p>
          </div>
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#79FF00]">What’s included</p>
            <ul class="mt-4 grid gap-3 text-sm leading-7 text-white/72 sm:grid-cols-2">
              <li v-for="line in item.deliverables" :key="line" class="rounded-2xl border border-white/8 bg-[#0D1210] px-4 py-3">{{ line }}</li>
            </ul>
            <NuxtLink to="/get-started" class="mt-6 inline-flex rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">{{ item.cta }}</NuxtLink>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>
