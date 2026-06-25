<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('portal-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);
const portalPreview = computed(() => content.value.portalPreview || []);

const previewCards = [
  { label: 'Project progress', value: '72%', note: 'Preview example only. Real client project data will come from the future portal backend.' },
  { label: 'Open tasks', value: '5', note: 'Sample task visibility for the future invite-only client portal.' },
  { label: 'Files shared', value: '12', note: 'Example file count only. Real storage/auth is not wired yet.' },
  { label: 'Invoices', value: '2', note: 'Mock invoice visibility only. Billing integrations still come later.' },
];

useSeoMeta({ title: 'Client Portal Preview | FAMtastic Designs', description: 'Client-facing portal preview for FAMtastic Designs. Real auth and project data are still mocked.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Preview</p>
        <h1 class="mt-3 text-4xl font-black text-white">A client-facing project hub preview, not the finished auth system.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">This page shows the intended client experience for project updates, shared files, invoices, and next steps. Real portal authentication, invite acceptance, password recovery, and live project data are still mocked for now.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Portal surface:</strong> {{ site.portalLink }}</p>
          <p class="mt-3"><strong class="text-white">Current mode:</strong> Preview only</p>
          <p class="mt-3"><strong class="text-white">Future login model:</strong> Invite-only client registration with real dashboard access after auth is connected.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/client-portal-login" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Preview Client Login</NuxtLink>
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
        <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5 text-sm leading-7 text-white/72">
          <p class="font-semibold text-white">Future client auth pages</p>
          <ul class="mt-3 grid gap-2 text-white/64">
            <li>- /client-portal-login</li>
            <li>- /client-portal-accept-invite</li>
            <li>- /client-portal-forgot-password</li>
            <li>- /client-portal-reset-password</li>
            <li>- /client-portal-dashboard</li>
          </ul>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="item in portalPreview" :key="item" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-sm font-semibold text-white">{{ item }}</p>
            <p class="mt-2 text-xs leading-6 text-white/58">Client-facing preview only. Backend auth and data wiring still come later.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
