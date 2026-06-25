<script setup lang="ts">
import { resolveAuditPaymentLink, resolveBookingLink, isExternalLink } from '~/app/utils/proof-links';
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const config = useRuntimeConfig();
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('thanks-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);
const bookingHref = computed(() => resolveBookingLink(config.public, site.value));
const auditHref = computed(() => resolveAuditPaymentLink(config.public, site.value));
const bookingExternal = computed(() => isExternalLink(bookingHref.value));
const auditExternal = computed(() => isExternalLink(auditHref.value));
useSeoMeta({ title: 'Thank You | FAMtastic Designs', description: 'Thank you page for the FAMtastic Designs contact and intake flow.' });
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 lg:px-8">
    <div class="rounded-[32px] border border-[#79FF00]/22 bg-[#0D1210] p-10">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Submission received</p>
      <h1 class="mt-4 text-4xl font-black text-white">Thanks — your request is in.</h1>
      <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-white/72">Thanks for reaching out. Project details are reviewed manually so the next step can match your scope, timing, and business goals.</p>
      <div class="mt-8 grid gap-3 sm:grid-cols-3">
        <a v-if="bookingExternal" :href="bookingHref" target="_blank" rel="noreferrer" class="rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Request a Consultation</a>
        <NuxtLink v-else :to="bookingHref" class="rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Request a Consultation</NuxtLink>
        <a v-if="auditExternal" :href="auditHref" target="_blank" rel="noreferrer" class="rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Discuss Audit / Deposit Options</a>
        <NuxtLink v-else :to="auditHref" class="rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Discuss Audit / Deposit Options</NuxtLink>
        <NuxtLink to="/" class="rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Return Home</NuxtLink>
      </div>
    </div>
  </div>
</template>
