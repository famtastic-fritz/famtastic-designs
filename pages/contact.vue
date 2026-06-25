<script setup lang="ts">
import ShortConsultationForm from '~/components/ShortConsultationForm.vue';
import { resolveAuditPaymentLink, resolveBookingLink, isExternalLink } from '~/app/utils/proof-links';
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const config = useRuntimeConfig();
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('contact-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);
const bookingHref = computed(() => resolveBookingLink(config.public, site.value));
const auditHref = computed(() => resolveAuditPaymentLink(config.public, site.value));
const bookingExternal = computed(() => isExternalLink(bookingHref.value));
const auditExternal = computed(() => isExternalLink(auditHref.value));
useSeoMeta({ title: content.value.seo.pages.contact.title, description: content.value.seo.pages.contact.description });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Contact</p>
        <h1 class="mt-3 text-4xl font-black text-white">Talk through the website, landing page, or client-system need.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">Use the short consultation form for the quick path, or open the full intake if the project needs more detail.</p>
        <div class="mt-8 grid gap-4 text-sm text-white/72">
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">Email</p>
            <a :href="`mailto:${site.contactEmail}`" class="mt-2 block text-lg font-semibold text-white">{{ site.contactEmail }}</a>
          </div>
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">Quick actions</p>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
              <a v-if="bookingExternal" :href="bookingHref" target="_blank" rel="noreferrer" class="rounded-full border border-white/12 px-4 py-2 text-sm text-white transition hover:border-[#79FF00]/40">Request a Consultation</a>
              <NuxtLink v-else :to="bookingHref" class="rounded-full border border-white/12 px-4 py-2 text-sm text-white transition hover:border-[#79FF00]/40">Request a Consultation</NuxtLink>
              <a v-if="auditExternal" :href="auditHref" target="_blank" rel="noreferrer" class="rounded-full border border-white/12 px-4 py-2 text-sm text-white transition hover:border-[#79FF00]/40">Discuss Audit / Deposit Options</a>
              <NuxtLink v-else :to="auditHref" class="rounded-full border border-white/12 px-4 py-2 text-sm text-white transition hover:border-[#79FF00]/40">Discuss Audit / Deposit Options</NuxtLink>
              <NuxtLink to="/get-started" class="rounded-full border border-white/12 px-4 py-2 text-sm text-white transition hover:border-[#79FF00]/40">Full Intake</NuxtLink>
            </div>
          </div>
        </div>
      </div>
      <ShortConsultationForm title="Request Free Consultation" />
    </div>
  </div>
</template>
