<script setup lang="ts">
useSeoMeta({ title: 'Client Portal Login | FAMtastic Designs', description: 'Client portal login information for FAMtastic Designs. Secure access is enabled for approved client workflows.' });
definePageMeta({ layout: 'famproof' });
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
    <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Client Portal Login</p>
        <h1 class="mt-3 text-4xl font-black text-white">Client access information for invited portal users.</h1>
        <p class="mt-5 text-base leading-8 text-white/72">Client portal access is provided to active clients when their project workspace is enabled. Need access? Contact FAMtastic Designs.</p>
        <div class="mt-8 rounded-[28px] border border-white/8 bg-[#0D1210] p-6 text-sm leading-7 text-white/72">
          <p><strong class="text-white">Current mode:</strong> Access information only</p>
          <p class="mt-3"><strong class="text-white">Access model:</strong> Invite-only login with secure credential setup when client access is enabled.</p>
          <p class="mt-3"><strong class="text-white">Need access?</strong> Active clients receive portal instructions directly when their project includes secure client access.</p>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <NuxtLink to="/contact" class="inline-flex items-center justify-center rounded-full bg-[#79FF00] px-5 py-3 text-sm font-bold text-[#050807]">Request Access</NuxtLink>
          <NuxtLink to="/portal" class="inline-flex items-center justify-center rounded-full border border-white/14 px-5 py-3 text-sm font-semibold text-white">Client Access Info</NuxtLink>
        </div>
      </div>
      <div class="rounded-[28px] border border-white/8 bg-white/[0.03] p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-[#79FF00]">Access workflow</p>
        <div class="mt-6 grid gap-4">
          <div class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white/72">Client access opens by invitation.</div>
          <div class="rounded-2xl border border-white/10 bg-[#0D1210] px-4 py-3 text-white/72">Workspace details are shared directly with active clients.</div>
          <div class="rounded-full bg-white/10 px-5 py-3 text-sm font-semibold text-white/72">Contact the team if you need portal help.</div>
        </div>
        <div class="mt-6 grid gap-3 text-sm text-white/68">
          <div class="rounded-2xl border border-white/8 px-4 py-3">Project-specific login details are issued when the workspace is enabled.</div>
          <div class="rounded-2xl border border-white/8 px-4 py-3">Support and reset instructions are provided directly to active clients.</div>
          <div class="rounded-2xl border border-white/8 px-4 py-3">No public self-service registration is shown until a secure access layer is fully active.</div>
        </div>
      </div>
    </div>
  </div>
</template>
