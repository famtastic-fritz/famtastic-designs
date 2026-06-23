<script setup lang="ts">
import { company, portalPreview } from '~/data/famtastic-proof';
definePageMeta({ layout: 'famproof' });
useSeoMeta({ title: 'Client Portal Login | FAMtastic Designs', description: 'Client portal preview and login path for the FAMtastic Designs proof sandbox.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Login</p>
        <h1 class="mt-3 text-4xl font-black text-white">Portal access preview for clients.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">Proof mode keeps the public UX clean without forcing live auth, Directus, or billing connections during local development.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Portal URL concept:</strong> {{ company.portalUrlConcept }}</p>
          <p class="mt-3"><strong class="text-white">Current mode:</strong> Preview only</p>
          <p class="mt-3"><strong class="text-white">Activation later:</strong> Directus auth + real project data + billing integrations.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/portal" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Open Portal Preview</NuxtLink>
          <NuxtLink to="/get-started" class="inline-flex items-center justify-center rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Request a portal-ready build</NuxtLink>
        </div>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="item in portalPreview" :key="item" class="rounded-[24px] border border-white/8 bg-white/[0.03] p-5">
          <p class="text-sm font-semibold text-white">{{ item }}</p>
          <p class="mt-2 text-xs leading-6 text-white/58">Preview surface for the proof sandbox.</p>
        </div>
      </div>
    </div>
  </div>
</template>
