<script setup lang="ts">
const router = useRouter();

const props = withDefaults(
  defineProps<{
    compact?: boolean;
    title?: string;
  }>(),
  {
    compact: false,
    title: 'Request Free Consultation',
  },
);

const form = reactive({
  name: '',
  email: '',
  phone: '',
  business_name: '',
  service_needed: '',
  budget: '',
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
  form_type: 'consultation',
});

const submitting = ref(false);
const errorMessage = ref('');

onMounted(() => {
  const route = useRoute();
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
    await $fetch('/api/leads', { method: 'POST', body: form });
    await router.push('/thank-you');
  } catch (error: any) {
    errorMessage.value = error?.data?.statusMessage || error?.message || 'Something went wrong. Try again.';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <form class="rounded-[28px] border border-white/10 bg-[#0D1210] p-6" @submit.prevent="submitForm">
    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-[#79FF00]">{{ props.title }}</p>
    <div class="mt-4 grid gap-4" :class="props.compact ? 'sm:grid-cols-2' : ''">
      <label class="grid gap-2 text-sm text-white/75"><span>Name</span><input v-model="form.name" required class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
      <label class="grid gap-2 text-sm text-white/75"><span>Email</span><input v-model="form.email" required type="email" class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
      <label class="grid gap-2 text-sm text-white/75"><span>Phone</span><input v-model="form.phone" type="tel" class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
      <label class="grid gap-2 text-sm text-white/75"><span>Business Name</span><input v-model="form.business_name" class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
      <label class="grid gap-2 text-sm text-white/75"><span>Service Needed</span><input v-model="form.service_needed" required class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
      <label class="grid gap-2 text-sm text-white/75"><span>Budget</span><input v-model="form.budget" class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white" /></label>
      <label class="grid gap-2 text-sm text-white/75 sm:col-span-2"><span>Message</span><textarea v-model="form.message" rows="4" class="rounded-2xl border border-white/10 bg-[#050807] px-4 py-3 text-white"></textarea></label>
    </div>
    <p class="mt-4 text-xs text-white/55">By submitting this proof form, you are sending a local mock lead for QA purposes.</p>
    <div v-if="errorMessage" class="mt-4 rounded-2xl border border-[#EF4444]/30 bg-[#EF4444]/10 px-4 py-3 text-sm text-[#ffd3d3]">{{ errorMessage }}</div>
    <button type="submit" :disabled="submitting" class="mt-5 inline-flex items-center justify-center rounded-full bg-[#79FF00] px-6 py-3 text-sm font-bold text-[#050807] disabled:cursor-not-allowed disabled:opacity-60">{{ submitting ? 'Submitting...' : 'Request Free Consultation' }}</button>
  </form>
</template>