<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('portal-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);
const portalPreview = computed(() => content.value.portalPreview || []);

const previewCards = [
  { label: 'Project progress', value: '72%', note: 'Illustrative project metric for a client-access rollout.' },
  { label: 'Open tasks', value: '5', note: 'Illustrative task visibility for a client-access rollout.' },
  { label: 'Files shared', value: '12', note: 'Illustrative file-sharing snapshot for a client-access rollout.' },
  { label: 'Invoices', value: '2', note: 'Illustrative invoice snapshot for a client-access rollout.' },
];

useSeoMeta({ title: 'Client Portal Access | FAMtastic Designs', description: 'Client access information for FAMtastic Designs. Secure portal activation is available for approved client workflows.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Access</p>
        <h1 class="mt-3 text-4xl font-black text-white">Client access information for projects, files, invoices, and next steps.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">This page explains how client access is handled. Secure login, project dashboards, file sharing, invoices, and update flows are enabled for approved client setups when that service layer is part of the build.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Portal surface:</strong> {{ site.portalLink }}</p>
          <p class="mt-3"><strong class="text-white">Current mode:</strong> Access details and rollout planning</p>
          <p class="mt-3"><strong class="text-white">Access model:</strong> Invite-only client access is enabled when the secure portal system is included in the project scope.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/client-portal-login" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Client Login Info</NuxtLink>
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
          <p class="font-semibold text-white">Client access route map</p>
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
            <p class="mt-2 text-xs leading-6 text-white/58">Available when the secure client access layer is enabled for the project.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
