<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('portal-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);
const portalPreview = computed(() => content.value.portalPreview || []);

const previewCards = [
  { label: 'Project progress', value: '72%', note: 'Homepage approved, intake flow tested, admin proof staged.' },
  { label: 'Open tasks', value: '5', note: 'Owner review, real credentials, legal review, production CMS, launch path.' },
  { label: 'Files shared', value: '12', note: 'Brand notes, content drafts, reports, and launch checklist examples.' },
  { label: 'Invoices', value: '2', note: 'Deposit placeholder and strategy/audit payment placeholder.' },
];

useSeoMeta({ title: 'Client Portal | FAMtastic Designs', description: 'Client portal preview for the FAMtastic Designs proof sandbox.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Preview</p>
        <h1 class="mt-3 text-4xl font-black text-white">Your project, files, invoices, and updates in one place.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">This is the proof-mode portal surface. It shows the client experience now while real auth, file storage, and invoicing reconnection stay documented for the next pass.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Portal link:</strong> {{ site.portalLink }}</p>
          <p class="mt-3"><strong class="text-white">Current mode:</strong> Preview only</p>
          <p class="mt-3"><strong class="text-white">Later connection:</strong> Real auth, file handling, invoice data, and project updates.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/client-portal-login" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Client Portal Login</NuxtLink>
          <NuxtLink to="/get-started" class="inline-flex items-center justify-center rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Request a portal-ready build</NuxtLink>
        </div>
      </div>
      <div class="grid gap-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <article v-for="card in previewCards" :key="card.label" class="rounded-[24px] border border-white/8 bg-white/[0.03] p-5">
            <p class="text-xs uppercase tracking-[0.22em] text-[#79FF00]">{{ card.label }}</p>
            <p class="mt-3 text-3xl font-black text-white">{{ card.value }}</p>
            <p class="mt-2 text-sm leading-6 text-white/62">{{ card.note }}</p>
          </article>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="item in portalPreview" :key="item" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-sm font-semibold text-white">{{ item }}</p>
            <p class="mt-2 text-xs leading-6 text-white/58">Preview surface for the proof sandbox.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
