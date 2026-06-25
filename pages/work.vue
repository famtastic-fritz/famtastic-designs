<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('work-content', () => getContent());
const content = computed(() => data.value || fallback);
const workItems = computed(() => content.value.portfolio || []);

useSeoMeta({ title: 'Work | FAMtastic Designs', description: 'Selected website directions, launch concepts, and client-facing examples from FAMtastic Designs.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Work / Portfolio</p>
      <h1 class="mt-3 text-4xl font-black text-white">Launch concepts and website directions built to move a brand forward.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">A mix of launch concepts, offer packaging, and site directions that show how FAMtastic Designs approaches clarity, conversion, and visual impact.</p>
    </div>

    <div class="mt-10 grid gap-5 lg:grid-cols-3">
      <article v-for="item in workItems" :key="item.title" class="rounded-[28px] border border-white/8 bg-[#0D1210] p-7">
        <p class="text-xs uppercase tracking-[0.24em] text-[#38BDF8]">{{ item.projectType }}</p>
        <h2 class="mt-3 text-2xl font-bold text-white">{{ item.title }}</h2>
        <p class="mt-4 text-sm leading-7 text-white/72">{{ item.summary }}</p>
        <p class="mt-5 text-xs leading-6 text-white/52">{{ item.resultLabel }}</p>
      </article>
    </div>

    <div class="mt-12 rounded-[28px] border border-dashed border-white/12 bg-white/[0.03] p-8 text-sm leading-7 text-white/72">Need a portfolio direction that matches your business? Use the contact or intake flow to request examples tailored to your niche, offer ladder, and launch goals.</div>
  </div>
</template>
