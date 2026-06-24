<script setup lang="ts">
useSeoMeta({ title: 'Client Portal Login | FAMtastic Designs', description: 'Client portal preview and login path for the FAMtastic Designs proof sandbox.' });
definePageMeta({ layout: 'famproof' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Login</p>
        <h1 class="mt-3 text-4xl font-black text-white">Portal access preview for clients.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">Proof mode keeps the UX clean without forcing live auth, Directus, or billing connections during local development.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Current mode:</strong> Preview only</p>
          <p class="mt-3"><strong class="text-white">Activation later:</strong> Real auth + project data + billing integrations.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/portal" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Open Portal Preview</NuxtLink>
          <NuxtLink to="/get-started" class="inline-flex items-center justify-center rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Request a portal-ready build</NuxtLink>
        </div>
      </div>
      <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Preview login state</p>
        <div class="mt-6 grid gap-4">
          <input disabled value="demo@client-portal.local" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white/60" />
          <input disabled value="••••••••" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white/60" />
          <button disabled class="rounded-full bg-white/10 px-5 py-3 text-sm font-semibold text-white/60">Preview only — auth mocked</button>
        </div>
      </div>
    </div>
  </div>
</template>
