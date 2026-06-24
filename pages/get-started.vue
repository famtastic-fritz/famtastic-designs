<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const route = useRoute();
const router = useRouter();
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData('intake-content', () => getContent());
const content = computed(() => data.value || fallback);
const site = computed(() => content.value.siteSettings);

useSeoMeta({
  title: content.value.seo.pages.getStarted.title,
  description: content.value.seo.pages.getStarted.description,
});

const projectTypes = ['New website', 'Website redesign', 'Branding', 'Landing page', 'Client portal', 'Automation', 'SEO / maintenance', 'Not sure yet'];
const goals = ['Get more leads', 'Look more professional', 'Sell products', 'Book appointments', 'Automate client onboarding', 'Replace outdated website', 'Launch a new brand', 'Other'];
const timelines = ['ASAP', '2–4 weeks', '1–2 months', 'Just planning'];
const budgets = ['Under $1,500', '$1,500–$3,000', '$3,000–$6,000', '$6,000+', 'Need help deciding'];
const contactMethods = ['Email', 'Phone', 'Text'];

const form = reactive({
  project_type: 'New website',
  business_name: '',
  industry: '',
  current_website: '',
  social_url: '',
  location: '',
  goals: [] as string[],
  timeline: '2–4 weeks',
  budget: '$3,000–$6,000',
  name: '',
  email: '',
  phone: '',
  preferred_contact_method: 'Email',
  best_time_to_reach: '',
  message: '',
  utm_source: '',
  utm_medium: '',
  utm_campaign: '',
  utm_content: '',
  utm_term: '',
  referrer: '',
  landing_page: '',
  device_type: '',
  submitted_at: '',
  package: '',
  form_type: 'full_intake',
});

const submitting = ref(false);
const errorMessage = ref('');

onMounted(() => {
  form.project_type = typeof route.query.project_type === 'string' ? route.query.project_type : form.project_type;
  form.package = typeof route.query.package === 'string' ? route.query.package : '';
  form.utm_source = typeof route.query.utm_source === 'string' ? route.query.utm_source : '';
  form.utm_medium = typeof route.query.utm_medium === 'string' ? route.query.utm_medium : '';
  form.utm_campaign = typeof route.query.utm_campaign === 'string' ? route.query.utm_campaign : '';
  form.utm_content = typeof route.query.utm_content === 'string' ? route.query.utm_content : '';
  form.utm_term = typeof route.query.utm_term === 'string' ? route.query.utm_term : '';
  form.referrer = document.referrer || '';
  form.landing_page = window.location.pathname + window.location.search;
  form.device_type = window.innerWidth < 768 ? 'mobile' : window.innerWidth < 1024 ? 'tablet' : 'desktop';
});

async function submitForm() {
  errorMessage.value = '';
  submitting.value = true;
  try {
    form.submitted_at = new Date().toISOString();
    await $fetch('/api/leads', {
      method: 'POST',
      body: form,
    });
    await router.push('/thank-you');
  } catch (error: any) {
    errorMessage.value = error?.data?.statusMessage || error?.message || 'Something went wrong. Try again.';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.82fr_1.18fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Get Started</p>
        <h1 class="mt-3 text-4xl font-black text-white">Full project intake for the serious version of the conversation.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">This intake captures project details, campaign source data, and contact information. In local proof mode, leads are saved to a local JSON file so the funnel can be tested without waiting on full Directus activation.</p>
        <div class="mt-8 grid gap-4">
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5 text-sm leading-7 text-white/72">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">What happens after submit</p>
            <ol class="mt-3 grid gap-2">
              <li>1. Lead saves locally in .data/famtastic-leads.json.</li>
              <li>2. User lands on the thank-you page.</li>
              <li>3. Placeholder links support booking or deposit flow.</li>
              <li>4. Directus mapping stays documented for the real activation pass.</li>
            </ol>
          </div>
          <div class="rounded-[24px] border border-white/8 bg-[#0D1210] p-5 text-sm leading-7 text-white/72">
            <p class="text-xs uppercase tracking-[0.24em] text-[#79FF00]">Proof contact</p>
            <p class="mt-3">{{ site.contactEmail }}</p>
          </div>
        </div>
      </div>

      <form class="rounded-[30px] border border-white/8 bg-white/[0.03] p-6 sm:p-8" @submit.prevent="submitForm">
        <div class="grid gap-8">
          <section>
            <h2 class="text-xl font-bold text-white">Project Type</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <label v-for="item in projectTypes" :key="item" class="flex items-center gap-3 rounded-2xl border border-white/8 bg-[#0D1210] px-4 py-3 text-sm text-white/78">
                <input v-model="form.project_type" type="radio" :value="item" class="h-4 w-4 border-white/30 bg-transparent text-[#79FF00] focus:ring-[#79FF00]" />
                <span>{{ item }}</span>
              </label>
            </div>
          </section>

          <section>
            <h2 class="text-xl font-bold text-white">Business Info</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2 text-sm text-white/75"><span>Business name</span><input v-model="form.business_name" required class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Industry</span><input v-model="form.industry" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Current website URL</span><input v-model="form.current_website" type="url" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Social media URL</span><input v-model="form.social_url" type="url" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75 sm:col-span-2"><span>Location</span><input v-model="form.location" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
            </div>
          </section>

          <section>
            <h2 class="text-xl font-bold text-white">Project Goals</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <label v-for="goal in goals" :key="goal" class="flex items-center gap-3 rounded-2xl border border-white/8 bg-[#0D1210] px-4 py-3 text-sm text-white/78">
                <input v-model="form.goals" type="checkbox" :value="goal" class="h-4 w-4 rounded border-white/30 bg-transparent text-[#79FF00] focus:ring-[#79FF00]" />
                <span>{{ goal }}</span>
              </label>
            </div>
          </section>

          <section class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-2 text-sm text-white/75"><span>Timeline</span><select v-model="form.timeline" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white"><option v-for="item in timelines" :key="item">{{ item }}</option></select></label>
            <label class="grid gap-2 text-sm text-white/75"><span>Budget</span><select v-model="form.budget" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white"><option v-for="item in budgets" :key="item">{{ item }}</option></select></label>
          </section>

          <section>
            <h2 class="text-xl font-bold text-white">Contact Info</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2 text-sm text-white/75"><span>Name</span><input v-model="form.name" required class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Email</span><input v-model="form.email" required type="email" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Phone</span><input v-model="form.phone" type="tel" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Preferred contact method</span><select v-model="form.preferred_contact_method" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white"><option v-for="item in contactMethods" :key="item">{{ item }}</option></select></label>
              <label class="grid gap-2 text-sm text-white/75 sm:col-span-2"><span>Best time to reach you</span><input v-model="form.best_time_to_reach" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75 sm:col-span-2"><span>Message / project notes</span><textarea v-model="form.message" rows="5" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white"></textarea></label>
            </div>
          </section>

          <section class="rounded-[24px] border border-dashed border-white/12 bg-[#0D1210] p-5 text-sm text-white/65">
            <h2 class="text-lg font-bold text-white">Hidden Campaign Fields</h2>
            <p class="mt-2 leading-7">This proof captures UTM query strings, referrer, landing page, device type, and submitted timestamp so attribution is not lost.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
              <code>utm_source={{ form.utm_source || '—' }}</code>
              <code>utm_medium={{ form.utm_medium || '—' }}</code>
              <code>utm_campaign={{ form.utm_campaign || '—' }}</code>
              <code>device_type={{ form.device_type || '—' }}</code>
            </div>
          </section>

          <div v-if="errorMessage" class="rounded-2xl border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-sm text-[#ffd3d3]">{{ errorMessage }}</div>

          <button type="submit" :disabled="submitting" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-6 py-3 text-sm font-bold text-[#050807] disabled:cursor-not-allowed disabled:opacity-60">{{ submitting ? 'Submitting...' : 'Submit Project Request' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
