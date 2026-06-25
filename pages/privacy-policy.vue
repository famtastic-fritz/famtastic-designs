<script setup lang="ts">
import { useFamtasticContent } from '~/composables/useFamtasticContent';

definePageMeta({ layout: 'famproof' });
const route = useRoute();
const { getContent, fallback } = useFamtasticContent();
const { data } = await useAsyncData(`legal-${route.path}`, () => getContent());
const content = computed(() => data.value || fallback);
const page = computed(() => content.value.legal.privacyPolicy);
useSeoMeta({ title: 'Privacy Policy | FAMtastic Designs', description: 'Privacy policy for the FAMtastic Designs website.' });
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8 lg:p-10">
      <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Legal</p>
      <h1 class="mt-3 text-4xl font-black text-white">{{ page.title }}</h1>
      <p class="mt-2 text-sm text-white/50">Updated {{ page.updated }}</p>
      <p class="mt-6 text-base leading-8 text-white/72">{{ page.intro }}</p>
      <section v-for="section in page.sections" :key="section.heading" class="mt-8">
        <h2 class="text-xl font-bold text-white">{{ section.heading }}</h2>
        <p class="mt-3 text-sm leading-7 text-white/72">{{ section.body }}</p>
      </section>
    </div>
  </div>
</template>
