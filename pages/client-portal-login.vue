<script setup lang="ts">
useSeoMeta({ title: 'Client Portal Login Preview | FAMtastic Designs', description: 'Client portal login preview for FAMtastic Designs. Real auth, forgot password, and invite acceptance are still mocked.' });
definePageMeta({ layout: 'famproof' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Login Preview</p>
        <h1 class="mt-3 text-4xl font-black text-white">Client access flow preview while real authentication stays mocked.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">This is client-facing copy only. It previews where invited clients would log in, recover a password, accept an invitation, and reach the portal dashboard after the real auth stack is connected.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Current mode:</strong> Preview only</p>
          <p class="mt-3"><strong class="text-white">Future auth:</strong> Invite-only registration plus email-backed forgot/reset password flow.</p>
          <p class="mt-3"><strong class="text-white">Still mocked:</strong> Sessions, passwords, dashboard data, file access, and invoices.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/portal" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Open Portal Preview</NuxtLink>
          <NuxtLink to="/get-started" class="inline-flex items-center justify-center rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Request a portal-ready build</NuxtLink>
        </div>
      </div>
      <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Preview login state</p>
        <div class="mt-6 grid gap-4">
          <input disabled value="client@example.com" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white/60" />
          <input disabled value="••••••••" class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white/60" />
          <button disabled class="rounded-full bg-white/10 px-5 py-3 text-sm font-semibold text-white/60">Preview only — auth mocked</button>
        </div>
        <div class="mt-6 grid gap-3 text-sm text-white/68">
          <div class="rounded-2xl border border-white/8 px-4 py-3">Forgot password preview: /client-portal-forgot-password</div>
          <div class="rounded-2xl border border-white/8 px-4 py-3">Accept invite preview: /client-portal-accept-invite</div>
          <div class="rounded-2xl border border-white/8 px-4 py-3">Dashboard destination preview: /client-portal-dashboard</div>
        </div>
      </div>
    </div>
  </div>
</template>
