<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('work-content', () => getContent());
const content = computed(() => data.value || fallback);
const workItems = computed(() => content.value.portfolio || []);

useSeoMeta({ title: 'Work | FAMtastic Designs', description: 'Demo concepts and honest placeholder portfolio cards for the local proof.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Work / Portfolio</p>
      <h1 class="mt-3 text-4xl font-black text-white">Proof cards now. Real client case studies can replace them later.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">No fake awards. No invented logos. Just honest demo cards showing the kind of transformation the business is selling.</p>
    </div>

    <div class="mt-10 grid gap-5 lg:grid-cols-3">
      <article v-for="item in workItems" :key="item.title" class="rounded-[28px] border border-white/8 bg-[#0D1210] p-7">
        <p class="text-xs uppercase tracking-[0.24em] text-[#38BDF8]">{{ item.projectType }}</p>
        <h2 class="mt-3 text-2xl font-bold text-white">{{ item.title }}</h2>
        <p class="mt-4 text-sm leading-7 text-white/72">{{ item.summary }}</p>
        <p class="mt-5 text-xs leading-6 text-white/52">{{ item.resultLabel }}</p>
      </article>
    </div>

    <div class="mt-12 rounded-[28px] border border-dashed border-white/12 bg-white/[0.03] p-8 text-sm leading-7 text-white/72">Replace these cards with real client work before final launch if desired. For now they function as honest placeholder concepts inside the proof sandbox.</div>
  </div>
</template>
