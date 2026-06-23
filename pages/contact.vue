<script setup lang="ts">
import { company } from '~/data/famtastic-proof';
const config = useRuntimeConfig();
definePageMeta({ layout: 'famproof' });
useSeoMeta({ title: 'Contact | FAMtastic Designs', description: 'Contact FAMtastic Designs about websites, branding, portals, and automation.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Contact</p>
        <h1 class="mt-3 text-4xl font-black text-white">Let’s tighten the front door of the business.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">If you already know you need a stronger website, a cleaner intake flow, or a portal-ready client experience, start here.</p>
        <div class="mt-8 grid gap-4 text-sm text-white/72">
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">Email</p>
            <a :href="`mailto:${company.email}`" class="mt-2 block text-lg font-semibold text-white">{{ company.email }}</a>
          </div>
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">Website</p>
            <a :href="`https://${company.domain}`" target="_blank" rel="noreferrer" class="mt-2 block text-lg font-semibold text-white">{{ company.domain }}</a>
          </div>
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">Best next step</p>
            <p class="mt-2 text-lg font-semibold text-white">Use the Get Started intake</p>
            <p class="mt-2">That route captures project details, campaign fields, and next-step context better than a generic email.</p>
          </div>
        </div>
      </div>
      <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Quick actions</p>
        <div class="mt-6 grid gap-4">
          <NuxtLink to="/get-started" class="rounded-[22px] border border-[#79FF00]/24 bg-[#79FF00]/10 px-5 py-5 text-white transition hover:border-[#79FF00]/40 hover:bg-[#79FF00]/14">
            <strong class="block text-lg">Get Started</strong>
            <span class="mt-2 block text-sm text-white/72">Fill out the intake form so the project can be scoped correctly.</span>
          </NuxtLink>
          <a :href="config.public.bookingLink" target="_blank" rel="noreferrer" class="rounded-[22px] border border-white/8 bg-[#0D1210] px-5 py-5 text-white transition hover:border-[#38BDF8]/40">
            <strong class="block text-lg">Book a Strategy Call</strong>
            <span class="mt-2 block text-sm text-white/72">Placeholder scheduling link for the proof sandbox.</span>
          </a>
          <a :href="config.public.stripeAuditPaymentLink" target="_blank" rel="noreferrer" class="rounded-[22px] border border-white/8 bg-[#0D1210] px-5 py-5 text-white transition hover:border-[#FACC15]/40">
            <strong class="block text-lg">Pay Website Audit Deposit</strong>
            <span class="mt-2 block text-sm text-white/72">Safe placeholder only. No live payment keys are connected in this proof.</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
