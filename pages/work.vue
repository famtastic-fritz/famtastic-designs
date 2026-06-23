<script setup lang="ts">
import { featuredWork } from '~/data/famtastic-proof';
definePageMeta({ layout: 'famproof' });
useSeoMeta({ title: 'Work | FAMtastic Designs', description: 'Proof-style portfolio and placeholder case studies for the FAMtastic Designs sandbox.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Work / Portfolio</p>
      <h1 class="mt-3 text-4xl font-black text-white">Proof cards now. Real client case studies can replace them later.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">No fake awards. No invented logos. Just honest demo cards that show the type of transformation the business is selling.</p>
    </div>

    <div class="mt-10 grid gap-5 lg:grid-cols-3">
      <article v-for="item in featuredWork" :key="item.title" class="rounded-[28px] border border-white/8 bg-[#0D1210] p-7">
        <p class="text-xs uppercase tracking-[0.24em] text-[#38BDF8]">Demo Project</p>
        <h2 class="mt-3 text-2xl font-bold text-white">{{ item.title }}</h2>
        <p class="mt-4 text-sm leading-7 text-white/72">{{ item.built }}</p>
        <p class="mt-5 text-lg font-black text-white">{{ item.metric }}</p>
        <p class="mt-3 text-xs leading-6 text-white/52">{{ item.note }}</p>
      </article>
    </div>

    <div class="mt-12 rounded-[28px] border border-dashed border-white/12 bg-white/[0.03] p-8 text-sm leading-7 text-white/72">Replace these cards with real client work before final launch if needed. For now they function as honest placeholder case studies inside the proof sandbox.</div>
  </div>
</template>
