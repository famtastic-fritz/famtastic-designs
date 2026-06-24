<script setup lang="ts">
definePageMeta({ layout: 'famproof' });
const config = useRuntimeConfig();
useSeoMeta({ title: 'Admin Proof | FAMtastic Designs', description: 'Local admin proof route for editing proof content without waiting on full CMS activation.' });

const pin = ref('');
const loading = ref(false);
const saving = ref(false);
const saveMessage = ref('');
const errorMessage = ref('');
const leads = ref<any[]>([]);

const form = reactive({
  utilityBarText: '',
  contactEmail: '',
  phone: '',
  heroHeadline: '',
  heroSubheadline: '',
  primaryCtaLabel: '',
  finalCtaHeadline: '',
  whyFamtasticJson: '[]',
  servicesJson: '[]',
  packagesJson: '[]',
  faqsJson: '[]',
});

function hydrate(content: any) {
  form.utilityBarText = content.siteSettings?.utilityBarText || '';
  form.contactEmail = content.siteSettings?.contactEmail || '';
  form.phone = content.siteSettings?.phone || '';
  form.heroHeadline = content.homepage?.heroHeadline || '';
  form.heroSubheadline = content.homepage?.heroSubheadline || '';
  form.primaryCtaLabel = content.homepage?.primaryCtaLabel || '';
  form.finalCtaHeadline = content.homepage?.finalCtaHeadline || '';
  form.whyFamtasticJson = JSON.stringify(content.homepage?.whyFamtastic || [], null, 2);
  form.servicesJson = JSON.stringify(content.services || [], null, 2);
  form.packagesJson = JSON.stringify(content.packages || [], null, 2);
  form.faqsJson = JSON.stringify(content.faqs || [], null, 2);
}

async function loadContent() {
  loading.value = true;
  errorMessage.value = '';
  try {
    const content = await $fetch('/api/admin-proof/content');
    hydrate(content);
  } catch (error: any) {
    errorMessage.value = error?.data?.statusMessage || error?.message || 'Could not load proof content.';
  } finally {
    loading.value = false;
  }
}

async function saveContent() {
  saving.value = true;
  saveMessage.value = '';
  errorMessage.value = '';
  try {
    const payload = {
      siteSettings: {
        utilityBarText: form.utilityBarText,
        contactEmail: form.contactEmail,
        phone: form.phone,
      },
      homepage: {
        heroHeadline: form.heroHeadline,
        heroSubheadline: form.heroSubheadline,
        primaryCtaLabel: form.primaryCtaLabel,
        finalCtaHeadline: form.finalCtaHeadline,
        whyFamtastic: JSON.parse(form.whyFamtasticJson || '[]'),
      },
      services: JSON.parse(form.servicesJson || '[]'),
      packages: JSON.parse(form.packagesJson || '[]'),
      faqs: JSON.parse(form.faqsJson || '[]'),
    };
    const result = await $fetch('/api/admin-proof/content', {
      method: 'POST',
      body: {
        pin: pin.value,
        content: payload,
      },
    });
    saveMessage.value = `Saved to ${result.savedTo}`;
  } catch (error: any) {
    errorMessage.value = error?.data?.statusMessage || error?.message || 'Could not save proof content.';
  } finally {
    saving.value = false;
  }
}

async function loadLeads() {
  errorMessage.value = '';
  try {
    const response = await $fetch<{ leads: any[] }>(`/api/admin-proof/leads?pin=${encodeURIComponent(pin.value)}`);
    leads.value = response.leads || [];
  } catch (error: any) {
    errorMessage.value = error?.data?.statusMessage || error?.message || 'Could not load local leads.';
  }
}

onMounted(() => {
  loadContent();
});
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="max-w-3xl">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Admin Proof</p>
      <h1 class="mt-3 text-4xl font-black text-white">Local content editing proof without waiting on production CMS rollout.</h1>
      <p class="mt-5 text-base leading-8 text-white/72">Use this route to prove editable content now. Directus remains the documented next CMS lane; this fallback proves the owner can change content and see it on the site locally.</p>
    </div>

    <div class="mt-10 grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div class="grid gap-5">
        <div class="rounded-[28px] border border-white/8 bg-[#0D1210] p-6">
          <label class="grid gap-2 text-sm text-white/75"><span>Admin Proof PIN</span><input v-model="pin" type="password" class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
          <div class="mt-4 flex flex-wrap gap-3">
            <button class="rounded-full border border-white/12 px-4 py-2 text-sm font-semibold text-white" :disabled="loading" @click="loadContent">{{ loading ? 'Loading...' : 'Load Content' }}</button>
            <button class="rounded-full border border-white/12 px-4 py-2 text-sm font-semibold text-white" @click="loadLeads">Load Leads</button>
            <NuxtLink to="/" class="rounded-full border border-white/12 px-4 py-2 text-sm font-semibold text-white">Open Homepage</NuxtLink>
          </div>
        </div>

        <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-6">
          <h2 class="text-xl font-bold text-white">Proof editor</h2>
          <div class="mt-5 grid gap-4">
            <label class="grid gap-2 text-sm text-white/75"><span>Utility bar text</span><input v-model="form.utilityBarText" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2 text-sm text-white/75"><span>Contact email</span><input v-model="form.contactEmail" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Phone</span><input v-model="form.phone" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
            </div>
            <label class="grid gap-2 text-sm text-white/75"><span>Hero headline</span><input v-model="form.heroHeadline" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
            <label class="grid gap-2 text-sm text-white/75"><span>Hero subheadline</span><textarea v-model="form.heroSubheadline" rows="3" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white"></textarea></label>
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="grid gap-2 text-sm text-white/75"><span>Primary CTA label</span><input v-model="form.primaryCtaLabel" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
              <label class="grid gap-2 text-sm text-white/75"><span>Final CTA headline</span><input v-model="form.finalCtaHeadline" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white" /></label>
            </div>
            <label class="grid gap-2 text-sm text-white/75"><span>Why FAMtastic (JSON array)</span><textarea v-model="form.whyFamtasticJson" rows="6" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 font-mono text-xs text-white"></textarea></label>
            <label class="grid gap-2 text-sm text-white/75"><span>Services (JSON array)</span><textarea v-model="form.servicesJson" rows="12" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 font-mono text-xs text-white"></textarea></label>
            <label class="grid gap-2 text-sm text-white/75"><span>Packages (JSON array)</span><textarea v-model="form.packagesJson" rows="12" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 font-mono text-xs text-white"></textarea></label>
            <label class="grid gap-2 text-sm text-white/75"><span>FAQs (JSON array)</span><textarea v-model="form.faqsJson" rows="10" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 font-mono text-xs text-white"></textarea></label>
            <button class="inline-flex w-fit rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]" :disabled="saving" @click="saveContent">{{ saving ? 'Saving...' : 'Save Proof Content' }}</button>
            <p v-if="saveMessage" class="text-sm text-[#79FF00]">{{ saveMessage }}</p>
            <p v-if="errorMessage" class="text-sm text-[#ffb4b4]">{{ errorMessage }}</p>
          </div>
        </div>
      </div>

      <div class="grid gap-5">
        <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-6">
          <h2 class="text-xl font-bold text-white">Proof steps</h2>
          <ol class="mt-4 grid gap-3 text-sm leading-7 text-white/72">
            <li>1. Load content.</li>
            <li>2. Change the hero headline or CTA text.</li>
            <li>3. Save the content using the PIN.</li>
            <li>4. Open the homepage and confirm the new text appears.</li>
            <li>5. Load leads and confirm recent form submissions exist locally.</li>
          </ol>
        </div>
        <div class="rounded-[28px] border border-white/8 bg-[#0D1210] p-6">
          <h2 class="text-xl font-bold text-white">Local leads</h2>
          <p class="mt-2 text-sm text-white/60">Loaded from .data/famtastic-leads.json through the proof route.</p>
          <div class="mt-5 grid gap-3">
            <article v-for="(lead, idx) in leads" :key="`${lead.email}-${idx}`" class="rounded-2xl border border-white/8 bg-white/[0.03] p-4">
              <p class="text-sm font-semibold text-white">{{ lead.name }} — {{ lead.form_type }}</p>
              <p class="mt-2 text-xs leading-6 text-white/62">{{ lead.email }} • {{ lead.project_type || lead.service_needed || 'Not specified' }}</p>
              <p class="mt-2 text-xs leading-6 text-white/55">{{ lead.submitted_at }}</p>
            </article>
            <p v-if="!leads.length" class="text-sm text-white/48">No leads loaded yet.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
