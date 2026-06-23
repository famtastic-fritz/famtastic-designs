<script setup lang="ts">
import { company, serviceCards, pricingPackages, featuredWork, faqItems, portalPreview } from '~/data/famtastic-proof';

definePageMeta({ layout: 'famproof' });

useSeoMeta({
  title: 'FAMtastic Designs',
  description: 'FAMtastic Designs builds high-performing websites, brand systems, automation, and client portals for growing businesses that want to look professional and win more customers.',
  ogTitle: 'FAMtastic Designs',
  ogDescription: 'Professional websites, branding, and client systems without messy tech or scattered tools.',
});
</script>

<template>
  <div>
    <section class="mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 lg:px-8 lg:pb-24 lg:pt-20">
      <div class="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
        <div>
          <div class="inline-flex rounded-full border border-[#79FF00]/20 bg-[#79FF00]/8 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Premium web design • branding • automation</div>
          <h1 class="mt-6 max-w-4xl text-4xl font-black leading-tight text-white sm:text-5xl lg:text-6xl">Websites that look expensive, load fast, and convert.</h1>
          <p class="mt-6 max-w-2xl text-base leading-8 text-white/72 sm:text-lg">FAMtastic Designs builds high-performing websites, brand systems, automation, and client portals for growing businesses that want to look professional and win more customers.</p>
          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <NuxtLink to="/get-started" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-6 py-3 text-sm font-bold text-[#050807] transition hover:bg-[#93ff33]">Get Started</NuxtLink>
            <NuxtLink to="/pricing" class="inline-flex items-center justify-center rounded-full border border-white/12 px-6 py-3 text-sm font-semibold text-white transition hover:border-[#79FF00]/60 hover:text-[#79FF00]">View Packages</NuxtLink>
          </div>
          <div class="mt-8 flex flex-wrap gap-3 text-xs font-medium text-white/70">
            <span class="rounded-full border border-white/10 px-3 py-2">Mobile-First</span>
            <span class="rounded-full border border-white/10 px-3 py-2">Conversion-Focused</span>
            <span class="rounded-full border border-white/10 px-3 py-2">Client Portal Ready</span>
            <span class="rounded-full border border-white/10 px-3 py-2">Fast Launches</span>
          </div>
        </div>
        <div class="rounded-[28px] border border-[#79FF00]/16 bg-[#0D1210] p-4 shadow-[0_24px_80px_rgba(0,0,0,0.45)]">
          <div class="rounded-[24px] border border-white/8 bg-[#050807] p-5">
            <div class="grid gap-4 md:grid-cols-[1.1fr_0.9fr]">
              <div class="rounded-[22px] border border-white/8 bg-[#0D1210] p-4">
                <div class="flex items-center justify-between text-xs text-white/55"><span>Campaign dashboard</span><span class="text-[#79FF00]">Live funnel</span></div>
                <div class="mt-5 grid gap-3">
                  <div class="rounded-2xl bg-white/4 p-4">
                    <p class="text-xs uppercase tracking-[0.18em] text-white/45">Lead capture</p>
                    <p class="mt-2 text-2xl font-bold text-white">32 new inquiries</p>
                    <p class="mt-2 text-sm text-white/60">UTM capture, qualification fields, and next-step automation built in.</p>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-white/4 p-4">
                      <p class="text-xs uppercase tracking-[0.18em] text-white/45">Speed</p>
                      <p class="mt-2 text-xl font-bold text-white">92 / 100</p>
                    </div>
                    <div class="rounded-2xl bg-white/4 p-4">
                      <p class="text-xs uppercase tracking-[0.18em] text-white/45">Portal</p>
                      <p class="mt-2 text-xl font-bold text-white">Ready</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex flex-col gap-3">
                <div class="rounded-[22px] border border-white/8 bg-white/4 p-4">
                  <p class="text-xs uppercase tracking-[0.18em] text-white/45">Proof badges</p>
                  <div class="mt-3 grid gap-2 text-sm text-white/72">
                    <div class="rounded-xl border border-[#79FF00]/16 px-3 py-2">Fast launch structure</div>
                    <div class="rounded-xl border border-[#38BDF8]/16 px-3 py-2">CMS powered content</div>
                    <div class="rounded-xl border border-white/10 px-3 py-2">Lead pipeline ready</div>
                  </div>
                </div>
                <div class="rounded-[22px] border border-white/8 bg-gradient-to-br from-[#79FF00]/18 to-transparent p-4">
                  <p class="text-xs uppercase tracking-[0.18em] text-white/45">Client experience</p>
                  <p class="mt-2 text-lg font-bold text-white">{{ company.portalUrlConcept }}</p>
                  <p class="mt-2 text-sm text-white/70">Projects, files, invoices, and updates in one place.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="border-y border-white/8 bg-white/[0.02]">
      <div class="mx-auto grid max-w-7xl gap-4 px-4 py-6 text-sm text-white/70 sm:px-6 md:grid-cols-5 lg:px-8">
        <div>Mobile-first builds</div>
        <div>CMS-powered content</div>
        <div>Lead capture forms</div>
        <div>Client portal workflow</div>
        <div>Stripe-ready invoices</div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-6">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Services</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Clean delivery lanes that help you win faster.</h2>
        </div>
        <NuxtLink to="/services" class="hidden text-sm text-white/65 transition hover:text-[#79FF00] md:block">See all services →</NuxtLink>
      </div>
      <div class="mt-8 grid gap-4 lg:grid-cols-4">
        <article v-for="card in serviceCards" :key="card.title" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <h3 class="text-xl font-semibold text-white">{{ card.title }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ card.sentence }}</p>
          <ul class="mt-4 grid gap-2 text-sm text-white/78">
            <li v-for="item in card.deliverables" :key="item" class="flex gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-[#79FF00]" /> <span>{{ item }}</span></li>
          </ul>
          <NuxtLink :to="card.cta" class="mt-6 inline-flex text-sm font-semibold text-[#79FF00]">Explore service →</NuxtLink>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8" id="process">
      <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <div class="rounded-[28px] border border-white/8 bg-[#0D1210] p-8">
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Why FAMtastic</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Premium websites without messy tech.</h2>
          <ul class="mt-6 grid gap-3 text-sm leading-7 text-white/72">
            <li>Lead capture built in from the start.</li>
            <li>CMS updates without code for the common edits.</li>
            <li>Portal-ready workflow instead of scattered tools and email chaos.</li>
            <li>Cleaner conversion structure for campaigns, referrals, and outreach traffic.</li>
          </ul>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="(step, idx) in ['Discover', 'Design', 'Build', 'Launch']" :key="step" class="rounded-[24px] border border-white/8 bg-white/[0.03] p-6">
            <p class="text-sm font-semibold text-[#79FF00]">0{{ idx + 1 }}</p>
            <h3 class="mt-3 text-xl font-semibold text-white">{{ step }}</h3>
            <p class="mt-3 text-sm leading-7 text-white/68">{{ idx === 0 ? 'Clarify goals, offers, campaign sources, and project needs before build decisions lock in.' : idx === 1 ? 'Shape the layout, positioning, and page structure so the proof feels premium without being busy.' : idx === 2 ? 'Turn the approved direction into a fast, modern build with intake flow and operations logic.' : 'Polish, review, and move toward the real CMS/portal/payment credentials when ready.' }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="flex items-end justify-between gap-6">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Featured Work</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Proof cards for the kind of transformation you actually sell.</h2>
        </div>
        <NuxtLink to="/work" class="hidden text-sm text-white/65 transition hover:text-[#79FF00] md:block">View portfolio →</NuxtLink>
      </div>
      <div class="mt-8 grid gap-4 lg:grid-cols-3">
        <article v-for="item in featuredWork" :key="item.title" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <p class="text-xs uppercase tracking-[0.24em] text-[#38BDF8]">Demo / Placeholder</p>
          <h3 class="mt-3 text-xl font-semibold text-white">{{ item.title }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ item.built }}</p>
          <p class="mt-5 text-lg font-bold text-white">{{ item.metric }}</p>
          <p class="mt-2 text-xs leading-6 text-white/52">{{ item.note }}</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="rounded-[28px] border border-[#79FF00]/16 bg-[#0D1210] p-8 lg:p-10">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Pricing</p>
            <h2 class="mt-3 text-3xl font-bold text-white">Straightforward packages with room for custom growth.</h2>
          </div>
          <NuxtLink to="/pricing" class="text-sm font-semibold text-white/70 transition hover:text-[#79FF00]">Full pricing page →</NuxtLink>
        </div>
        <div class="mt-8 grid gap-4 lg:grid-cols-3">
          <article v-for="plan in pricingPackages" :key="plan.name" :class="plan.featured ? 'border-[#79FF00]/35 bg-[#79FF00]/8' : 'border-white/8 bg-white/[0.03]'" class="rounded-[24px] border p-6">
            <p class="text-sm font-semibold text-white">{{ plan.name }}</p>
            <p class="mt-3 text-2xl font-black text-white">{{ plan.price }}</p>
            <p class="mt-3 text-sm leading-7 text-white/68">{{ plan.description }}</p>
            <ul class="mt-5 grid gap-2 text-sm text-white/78">
              <li v-for="feature in plan.features" :key="feature" class="flex gap-2"><span class="mt-1 h-2 w-2 rounded-full bg-[#79FF00]" /> <span>{{ feature }}</span></li>
            </ul>
            <NuxtLink :to="plan.href" class="mt-6 inline-flex rounded-full bg-white px-4 py-2 text-sm font-semibold text-[#050807]">{{ plan.cta }}</NuxtLink>
          </article>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-8 rounded-[28px] border border-white/8 bg-white/[0.03] p-8 lg:grid-cols-[0.95fr_1.05fr] lg:p-10">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Preview</p>
          <h2 class="mt-3 text-3xl font-bold text-white">Your project, files, invoices, and updates in one place.</h2>
          <p class="mt-4 max-w-xl text-sm leading-7 text-white/70">AgencyOS already includes a portal lane. In this proof, the visual experience is branded now while the final Directus-auth activation stays documented until the backend seed path is fully finished.</p>
          <NuxtLink to="/client-portal-login" class="mt-6 inline-flex rounded-full border border-white/12 px-5 py-3 text-sm font-semibold text-white transition hover:border-[#79FF00]/55 hover:text-[#79FF00]">Client Portal Login</NuxtLink>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="item in portalPreview" :key="item" class="rounded-2xl border border-white/8 bg-[#0D1210] p-5 text-sm font-medium text-white/82">{{ item }}</div>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
      <div class="grid gap-4 lg:grid-cols-2">
        <article v-for="faq in faqItems" :key="faq.q" class="rounded-[24px] border border-white/8 bg-[#0D1210] p-6">
          <h3 class="text-lg font-semibold text-white">{{ faq.q }}</h3>
          <p class="mt-3 text-sm leading-7 text-white/68">{{ faq.a }}</p>
        </article>
      </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
      <div class="rounded-[28px] border border-[#79FF00]/18 bg-[#79FF00]/10 px-8 py-10 text-center">
        <h2 class="text-3xl font-black text-white">Ready for a website that finally matches your business?</h2>
        <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
          <NuxtLink to="/get-started" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-6 py-3 text-sm font-bold text-[#050807]">Get Started</NuxtLink>
          <NuxtLink to="/contact" class="inline-flex items-center justify-center rounded-full border border-white/14 px-6 py-3 text-sm font-semibold text-white">Contact</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
