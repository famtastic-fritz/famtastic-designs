<script setup lang="ts">
import { borderRadiusMap } from '~/theme';

const { theme } = useAppConfig();

useHead({
  style: [
    {
      id: 'border-radius',
      innerHTML: `:root {${Object.entries(borderRadiusMap[theme.borderRadius])
        .map(([key, value]) => `--border-radius-${key}: ${value};`)
        .join('\n')}\n${Object.entries(theme.fonts)
        .map(([key, value]) => `--font-${key}: ${value};`)
        .join('\n')}`,
    },
  ],
});
</script>

<template>
  <NuxtLayout>
    <NuxtLoadingIndicator color="repeating-linear-gradient(to right,#79FF00 0%,#38BDF8 100%)" />
    <NuxtPage />
  </NuxtLayout>
</template>
