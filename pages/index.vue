<script setup lang="ts">
import ShortConsultationForm from '~/components/ShortConsultationForm.vue';
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });

const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('home-content', () => getContent());
const content = computed(() => data.value || fallback);

const site = computed(() => content.value.siteSettings);
const home = computed(() => content.value.homepage);
const services = computed(() => content.value.services || []);
const packages = computed(() => [...(content.value.packages || [])].sort((a, b) => a.sortOrder - b.sortOrder));
const addons = computed(() => content.value.addons || []);
const faqs = computed(() => content.value.faqs || []);
const portfolio = computed(() => content.value.portfolio || []);
const testimonials = computed(() => content.value.testimonials || []);
const platformStrip = computed(() => content.value.platformStrip || []);
const portalPreview = computed(() => content.value.portalPreview || []);

useSeoMeta({
  title: content.value.seo.pages.home.title,
  description: content.value.seo.pages.home.description,
  ogTitle: content.value.seo.pages.home.title,
  ogDescription: content.value.seo.pages.home.description,
});

useHead({
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'ProfessionalService',
        name: site.value.siteName,
        email: site.value.contactEmail,
        telephone: site.value.phone,
        url: site.value.siteUrl,
        description: home.value.heroSubheadline,
        areaServed: 'Remote / project fit',
        serviceType: services.value.map((item: any) => item.title),
      }),
    },
  ],
});
</script>

<template>
  <div>
    <section class="mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 lg:px-8 lg:pb-20 lg:pt-16">
      <div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-start">
        <div>
          <div class="inline-flex rounded-full border border-[#79FF00]/20 bg-[#79FF00]/8 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[#79FF00]">{{ home.heroEyebrow }}</div>
          <h1 class="mt-6 max-w-5xl text-4xl font-black leading-tight text-white sm:text-5xl lg:text-6xl">{{ home.heroHeadline }}</h1>
          <p class="mt-6 max-w-3xl text-base leading-8 text-white/72 sm:text-lg">{{ home.heroSubheadline }}</p>
          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <NuxtLink :to="home.primaryCtaHref" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-6 py-3 text-sm font-bold text-[#050807] transition hover:bg-[#93ff33]">{{ home.primaryCtaLabel }}</NuxtLink>
            <NuxtLink :to="home.secondaryCtaHref" class="inline-flex items-center justify-center rounded-full border border-white/12 px-6 py-3 text-sm font-semibold text-white transition hover:border-[#79FF00]/60 hover:text-[#79FF00]">{{ home.secondaryCtaLabel }}</NuxtLink>
          </div>
          <div class="mt-8 grid gap-3 sm:grid-cols-2">
            <div v-for="bullet in home.proofBullets" :key="bullet" class="rounded-2xl border border-white/8 bg-white/[0.03] px-4 py-3 text-sm text-white/74">{{ bullet }}</div>
          </div>
        </div>
        <div>
          <ShortConsultationForm compact title="Request Free Consultation" />
          <p class="mt-4 text-sm leading-7 text-white/62">{{ home.shortFormIntro }} <NuxtLink :to="home.shortFormDetailLinkHref" class="text-[#79FF00] underline-offset-2 hover:underline">{{ home.shortFormDetailLinkLabel }}</NuxtLink></p>
        </div>
      </div>
    </section>

    <section class="border-y border-white/8 bg-white/[0.02]">
      <div class="mx-auto grid max-w-7xl gap-4 px-4 py-6 text-sm text-white/70 sm:px-6 md:grid-cols-4 lg:grid-cols-8 lg:px-8">
        <div v-for="item in platformStrip" :key="item">{{ item }}</div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-6">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Packages</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Offer ladder built for real buying paths.</h2>
          <p class="mt-4 max-w-2xl text-sm leading-7 text-white/68">Starting-price language keeps the proof honest while still showing how the business can sell at multiple entry points.</p>
        </div>
        <NuxtLink to="/packages" class="hidden text-sm text-white/65 transition hover:text-[#79FF00] md:block">See all packages →</NuxtLink>
      </div>
      <div class="mt-8 grid gap-4 lg:grid-cols-3">
        <article v-for="plan in packages.slice(0, 6)" :key="plan.packageName" :class="plan.highlighted ? 'border-[#79FF00]/35 bg-[#79FF00]/8' : 'border-white/8 bg-[#0D1210]'" class="rounded-[24px] border p-6">
          <p class="text-sm font-semibold text-white">{{ plan.packageName }}</p>
          <p class="mt-3 text-2xl font-black text-white">{{ plan.priceLabel }}</p>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ plan.description }}</p>
          <p class="mt-4 text-xs uppercase tracking-[0.2em] text-[#79FF00]">Best for</p>
          <p class="mt-2 text-sm text-white/72">{{ plan.bestFor }}</p>
          <ul class="mt-5 grid gap-2 text-sm text-white/78">
            <li v-for="feature in plan.features" :key="feature" class="flex gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-[#79FF00]" /> <span>{{ feature }}</span></li>
          </ul>
          <p class="mt-5 text-xs text-white/45">Final scope confirmed after consultation.</p>
          <p v-if="plan.paymentOptions?.length" class="mt-3 text-xs leading-6 text-white/60">Future payment path: {{ plan.paymentOptions.join(' • ') }}</p>
          <NuxtLink :to="`/get-started?package=${encodeURIComponent(plan.packageName)}`" class="mt-5 inline-flex rounded-full bg-white px-4 py-2 text-sm font-semibold text-[#050807]">{{ plan.cta }}</NuxtLink>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-6">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Services</p>
          <h2 class="mt-3 text-3xl font-bold text-white">What FAMtastic Designs actually helps with.</h2>
        </div>
        <NuxtLink to="/services" class="hidden text-sm text-white/65 transition hover:text-[#79FF00] md:block">See all services →</NuxtLink>
      </div>
      <div class="mt-8 grid gap-4 lg:grid-cols-3">
        <article v-for="item in services" :key="item.slug" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <h3 class="text-xl font-semibold text-white">{{ item.title }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ item.shortDescription }}</p>
          <ul class="mt-4 grid gap-2 text-sm text-white/78">
            <li v-for="deliverable in item.deliverables" :key="deliverable" class="flex gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-[#79FF00]" /> <span>{{ deliverable }}</span></li>
          </ul>
          <NuxtLink :to="`/services#${item.slug}`" class="mt-6 inline-flex text-sm font-semibold text-[#79FF00]">{{ item.cta }} →</NuxtLink>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <div class="rounded-[28px] border border-white/8 bg-[#0D1210] p-8">
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Why FAMtastic</p>
          <h2 class="mt-3 text-3xl font-bold text-white">A website should act like a business asset, not a pretty placeholder.</h2>
          <ul class="mt-6 grid gap-3 text-sm leading-7 text-white/72">
            <li v-for="line in home.whyFamtastic" :key="line">{{ line }}</li>
          </ul>
        </div>
        <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Add-ons</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Extra support when the project needs more than the core build.</h2>
          <div class="mt-6 grid gap-4 md:grid-cols-2">
            <article v-for="item in addons" :key="item.name" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5">
              <h3 class="text-lg font-semibold text-white">{{ item.name }}</h3>
              <p class="mt-2 text-sm leading-7 text-white/68">{{ item.description }}</p>
              <p class="mt-3 text-sm font-semibold text-white">{{ item.priceLabel }}</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8" id="process">
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <article v-for="(step, idx) in home.process" :key="step.title" class="rounded-[24px] border border-white/8 bg-white/[0.03] p-6">
          <p class="text-sm font-semibold text-[#79FF00]">0{{ idx + 1 }}</p>
          <h3 class="mt-3 text-xl font-semibold text-white">{{ step.title }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ step.description }}</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-6">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Work</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Honest demo concepts instead of fake client wins.</h2>
        </div>
        <NuxtLink to="/work" class="hidden text-sm text-white/65 transition hover:text-[#79FF00] md:block">View work →</NuxtLink>
      </div>
      <div class="mt-8 grid gap-4 lg:grid-cols-3">
        <article v-for="item in portfolio" :key="item.title" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <p class="text-xs uppercase tracking-[0.24em] text-[#38BDF8]">{{ item.projectType }}</p>
          <h3 class="mt-3 text-xl font-semibold text-white">{{ item.title }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ item.summary }}</p>
          <p class="mt-5 text-xs leading-6 text-white/52">{{ item.resultLabel }}</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-4 lg:grid-cols-3">
        <article v-for="item in testimonials" :key="item.quote" class="rounded-[24px] border border-white/8 bg-white/[0.03] p-6">
          <p class="text-sm leading-7 text-white/72">“{{ item.quote }}”</p>
          <p class="mt-4 text-sm font-semibold text-white">{{ item.name }}</p>
          <p class="text-xs text-white/52">{{ item.titleCompany }}</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-8 rounded-[28px] border border-white/8 bg-white/[0.03] p-8 lg:grid-cols-[0.95fr_1.05fr] lg:p-10">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Preview</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Project progress, files, invoices, and updates in one place.</h2>
          <p class="mt-4 max-w-xl text-sm leading-7 text-white/70">The portal is presented as a preview now. Real auth and deeper operations are still mocked until the next backend activation pass.</p>
          <NuxtLink to="/portal" class="mt-6 inline-flex rounded-full border border-white/12 px-5 py-3 text-sm font-semibold text-white transition hover:border-[#79FF00]/55 hover:text-[#79FF00]">Client Portal Login</NuxtLink>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="item in portalPreview" :key="item" class="rounded-2xl border border-white/8 bg-[#0D1210] p-5 text-sm font-medium text-white/82">{{ item }}</div>
        </div>
      </div>
    </section>

    <section id="faqs" class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-4 lg:grid-cols-2">
        <article v-for="faq in faqs" :key="faq.question" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <h3 class="text-lg font-semibold text-white">{{ faq.question }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ faq.answer }}</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
      <div class="rounded-[28px] border border-[#79FF00]/18 bg-[#79FF00]/10 px-8 py-10 text-center">
        <h2 class="text-3xl font-black text-white">{{ home.finalCtaHeadline }}</h2>
        <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
          <NuxtLink :to="home.finalCtaHref" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-6 py-3 text-sm font-bold text-[#050807]">{{ home.finalCtaLabel }}</NuxtLink>
          <NuxtLink to="/contact" class="inline-flex items-center justify-center rounded-full border border-white/14 px-6 py-3 text-sm font-semibold text-white">Contact</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
