<script setup lang="ts">
import { pricingPackages, monthlyPlans } from '~/data/famtastic-proof';
definePageMeta({ layout: 'famproof' });
useSeoMeta({ title: 'Pricing | FAMtastic Designs', description: 'Starter, growth, premium portal packages, and monthly care plans.' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Pricing</p>
      <h1 class="mt-3 text-4xl font-black text-white">Clear package lanes, not vague agency fog.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">Use these as the current proofing packages. They are editable later through Directus once the backend template is fully activated, and editable now in the local proof data file.</p>
    </div>

    <div class="mt-10 grid gap-4 lg:grid-cols-3">
      <article v-for="plan in pricingPackages" :key="plan.name" :class="plan.featured ? 'border-[#79FF00]/35 bg-[#79FF00]/8' : 'border-white/8 bg-[#0D1210]'" class="rounded-[28px] border p-7">
        <p class="text-sm font-semibold text-white">{{ plan.name }}</p>
        <p class="mt-3 text-3xl font-black text-white">{{ plan.price }}</p>
        <p class="mt-4 text-sm leading-7 text-white/72">{{ plan.description }}</p>
        <ul class="mt-6 grid gap-3 text-sm text-white/78">
          <li v-for="feature in plan.features" :key="feature" class="flex gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-[#79FF00]" /> <span>{{ feature }}</span></li>
        </ul>
        <NuxtLink :to="plan.href" class="mt-6 inline-flex rounded-full bg-white px-5 py-3 text-sm font-bold text-[#050807]">{{ plan.cta }}</NuxtLink>
      </article>
    </div>

    <div class="mt-12 rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Monthly Plans</p>
          <h2 class="mt-3 text-2xl font-bold text-white">Keep momentum after launch.</h2>
        </div>
        <p class="text-sm text-white/60">Recurring billing intentionally remains a placeholder until real Stripe credentials are added.</p>
      </div>
      <div class="mt-6 grid gap-4 md:grid-cols-3">
        <article v-for="plan in monthlyPlans" :key="plan.name" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <p class="text-lg font-semibold text-white">{{ plan.name }}</p>
          <p class="mt-2 text-2xl font-black text-white">{{ plan.price }}</p>
          <p class="mt-3 text-sm leading-7 text-white/72">{{ plan.description }}</p>
        </article>
      </div>
      <div class="mt-8 rounded-[22px] border border-dashed border-[#79FF00]/30 bg-[#79FF00]/8 p-5 text-sm leading-7 text-white/72">Need something custom? We can shape the right combination of website, branding, landing pages, automations, and client systems after reviewing your intake.</div>
    </div>
  </div>
</template>
