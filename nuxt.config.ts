import { theme } from './theme';

const siteUrl = process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3001';
const cmsMode = process.env.NUXT_PUBLIC_CMS_MODE || 'local';
const paymentMode = process.env.NUXT_PUBLIC_PAYMENT_MODE || 'mock';
const portalMode = process.env.NUXT_PUBLIC_PORTAL_MODE || 'preview';

export default defineNuxtConfig({
  extends: [],
  ignore: [
    'pages/[...permalink].vue',
    'pages/posts/**',
    'pages/projects.vue',
    'pages/help/**',
    'server/api/_sitemap-urls.ts',
    'server/api/search.get.ts',
    'server/api/feedback.post.ts',
    'server/api/proxy/**',
    'server/utils/directus-server.ts',
    'modules/**',
  ],
  components: [
    { path: '~/components/base', pathPrefix: false },
    '~/components',
  ],
  css: ['~/assets/css/tailwind.css', '~/assets/css/main.css'],
  modules: [
    '@nuxt/image',
    '@nuxt/ui',
    '@nuxtjs/color-mode',
    '@nuxtjs/google-fonts',
    '@vueuse/nuxt',
    '@nuxt/icon',
  ],
  experimental: {
    asyncContext: true,
    componentIslands: false,
  },
  devtools: { enabled: true },
  runtimeConfig: {
    directusUrl: process.env.DIRECTUS_URL || '',
    directusToken: process.env.DIRECTUS_TOKEN || '',
    adminProofPin: process.env.ADMIN_PROOF_PIN || '',
    stripeSecretKey: process.env.STRIPE_SECRET_KEY || '',
    stripeWebhookSecret: process.env.STRIPE_WEBHOOK_SECRET || '',
    public: {
      siteName: process.env.NUXT_PUBLIC_SITE_NAME || 'FAMtastic Designs',
      siteUrl,
      cmsMode,
      paymentMode,
      portalMode,
      enableAdminProof: process.env.NUXT_PUBLIC_ENABLE_ADMIN_PROOF || 'true',
      leadStorageMode: process.env.NUXT_PUBLIC_LEAD_STORAGE_MODE || 'local',
      stripePublishableKey: process.env.STRIPE_PUBLISHABLE_KEY || '',
      stripeAuditPaymentLink: process.env.STRIPE_AUDIT_PAYMENT_LINK || 'https://buy.stripe.com/REPLACE_WITH_REAL_LINK',
      bookingLink: process.env.BOOKING_LINK || 'https://calendly.com/REPLACE_WITH_REAL_LINK',
    },
  },
  app: {
    head: {
      title: 'FAMtastic Designs',
      titleTemplate: '%s | FAMtastic Designs',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'FAMtastic Designs helps businesses launch professional websites, branding, and client systems without messy tech or scattered tools.' },
        { property: 'og:site_name', content: 'FAMtastic Designs' },
        { property: 'og:type', content: 'website' },
        { property: 'og:title', content: 'FAMtastic Designs' },
        { property: 'og:description', content: 'Websites that look expensive, load fast, and convert.' },
        { property: 'og:url', content: siteUrl },
      ],
      link: [{ rel: 'icon', type: 'image/png', href: '/favicon.ico' }],
    },
  },
  colorMode: {
    classSuffix: '',
  },
  image: {
    provider: 'ipx',
  },
  googleFonts: {
    families: theme.googleFonts,
    display: 'swap',
    download: true,
  },
  postcss: {
    plugins: {
      'postcss-import': {},
      'tailwindcss/nesting': {},
      tailwindcss: {},
      autoprefixer: {},
    },
  },
  build: {
    transpile: ['v-perfect-signature'],
  },
  compatibilityDate: '2024-07-28',
});
