<script setup lang="ts">
const keyName = 'famtastic_cookie_consent';
const visible = ref(false);

onMounted(() => {
  visible.value = !localStorage.getItem(keyName);
});

function setConsent(value: 'accepted' | 'declined') {
  localStorage.setItem(keyName, value);
  visible.value = false;
}
</script>

<template>
  <div v-if="visible" class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-5xl rounded-[24px] border border-white/12 bg-[#050807]/96 p-5 shadow-[0_20px_60px_rgba(0,0,0,0.45)] backdrop-blur">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <p class="text-sm font-semibold text-white">Cookie notice</p>
        <p class="mt-2 text-sm leading-7 text-white/70">This local proof stores cookie preferences in localStorage. Future analytics or marketing scripts should respect this consent before firing. See the <NuxtLink class="text-[#79FF00] underline-offset-2 hover:underline" to="/cookie-policy">Cookie Policy</NuxtLink>.</p>
      </div>
      <div class="flex gap-3">
        <button class="rounded-full border border-white/14 px-4 py-2 text-sm font-semibold text-white" @click="setConsent('declined')">Decline</button>
        <button class="rounded-full bg-[#79FF00] px-4 py-2 text-sm font-bold text-[#050807]" @click="setConsent('accepted')">Accept</button>
      </div>
    </div>
  </div>
</template>