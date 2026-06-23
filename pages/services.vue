<script setup lang="ts">
import { serviceCards } from '~/data/famtastic-proof';
definePageMeta({ layout: 'famproof' });
useSeoMeta({ title: 'Services | FAMtastic Designs', description: 'Web design, development, branding, landing pages, portals, and website care plans.' });
const serviceDetails = [
  { id: 'web-design', title: 'Web Design', who: 'Businesses that need a sharper, more premium first impression.', includes: ['Homepage + key page UX', 'Visual direction and hierarchy', 'Conversion-first layout planning'], cta: 'Get Started' },
  { id: 'web-development', title: 'Web Development', who: 'Teams that need fast, modern builds that do not feel stitched together.', includes: ['Nuxt/front-end implementation', 'CMS-ready content structure', 'Performance and SEO foundations'], cta: 'Book Strategy Call' },
  { id: 'brand-identity', title: 'Brand Identity', who: 'Brands that look scattered and need cleaner signal and presentation.', includes: ['Messaging refinement', 'Color and typography direction', 'CTA and offer polish'], cta: 'Get Started' },
  { id: 'client-portals', title: 'Client Portals & Automation', who: 'Agencies and service businesses tired of project chaos and email sprawl.', includes: ['Portal setup planning', 'Lead and project workflow mapping', 'Automation-ready handoff logic'], cta: 'Apply for Premium Build' },
  { id: 'campaign-landing-pages', title: 'Campaign Landing Pages', who: 'Businesses running ads, outreach, launches, or referral campaigns.', includes: ['Dedicated landing page build', 'Offer-focused page flow', 'Campaign source tracking'], cta: 'Launch a Landing Page' },
  { id: 'website-care-plans', title: 'Website Care Plans', who: 'Owners who want the site maintained and improved after launch.', includes: ['Updates and maintenance', 'Priority fixes and support', 'Optimization roadmap'], cta: 'Ask About Care Plans' },
];
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Services</p>
      <h1 class="mt-3 text-4xl font-black text-white">Design, development, and systems built to make the business feel tighter.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">This proof is intentionally cleaner than the crowded mockup. The offer is simple: look professional, capture leads, and move clients through a better experience.</p>
    </div>

    <div class="mt-10 grid gap-4 lg:grid-cols-4">
      <article v-for="card in serviceCards" :key="card.title" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
        <h2 class="text-xl font-semibold text-white">{{ card.title }}</h2>
        <p class="mt-3 text-sm leading-7 text-white/68">{{ card.sentence }}</p>
      </article>
    </div>

    <div class="mt-12 grid gap-5">
      <article v-for="item in serviceDetails" :id="item.id" :key="item.id" class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
        <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
          <div>
            <h2 class="text-2xl font-bold text-white">{{ item.title }}</h2>
            <p class="mt-4 text-sm leading-7 text-white/72"><strong class="text-white">Who it is for:</strong> {{ item.who }}</p>
          </div>
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#79FF00]">What’s included</p>
            <ul class="mt-4 grid gap-3 text-sm leading-7 text-white/72 sm:grid-cols-2">
              <li v-for="line in item.includes" :key="line" class="rounded-2xl border border-white/8 bg-[#0D1210] px-4 py-3">{{ line }}</li>
            </ul>
            <NuxtLink to="/get-started" class="mt-6 inline-flex rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">{{ item.cta }}</NuxtLink>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>
