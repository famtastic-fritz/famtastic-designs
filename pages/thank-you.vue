<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('thanks-content', () => getContent());
const content = computed(() => data.value || fallback);
const config = useRuntimeConfig();
useSeoMeta({ title: 'Thank You | FAMtastic Designs', description: 'Thank you page for the FAMtastic Designs proof intake flow.' });
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 lg:px-8">
    <div class="rounded-[32px] border border-[#79FF00]/22 bg-[#0D1210] p-10">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Submission received</p>
      <h1 class="mt-4 text-4xl font-black text-white">Thanks — your request is in.</h1>
      <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-white/72">In proof mode, submissions are stored locally while the full CMS/CRM activation remains documented. The user-facing flow still lands cleanly here.</p>
      <div class="mt-8 grid gap-3 sm:grid-cols-3">
        <a :href="config.public.bookingLink" target="_blank" rel="noreferrer" class="rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Book a Strategy Call</a>
        <a :href="config.public.stripeAuditPaymentLink" target="_blank" rel="noreferrer" class="rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Pay Website Audit Deposit</a>
        <NuxtLink to="/" class="rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Return Home</NuxtLink>
      </div>
    </div>
  </div>
</template>
