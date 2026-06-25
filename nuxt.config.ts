import { theme } from './theme';

const siteUrl = process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3001';
const cmsMode = process.env.NUXT_PUBLIC_CMS_MODE || 'local';
const paymentMode = process.env.NUXT_PUBLIC_PAYMENT_MODE || 'mock';
const portalMode = process.env.NUXT_PUBLIC_PORTAL_MODE || 'preview';
const bookingProvider = process.env.BOOKING_PROVIDER || 'mock';

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
    enableAdminProof: process.env.ENABLE_ADMIN_PROOF || 'false',
    directusUrl: process.env.DIRECTUS_URL || '',
    directusToken: process.env.DIRECTUS_TOKEN || '',
    adminProofPin: process.env.ADMIN_PROOF_PIN || '',
    stripeSecretKey: process.env.STRIPE_SECRET_KEY || '',
    stripeWebhookSecret: process.env.STRIPE_WEBHOOK_SECRET || '',
    paypalClientId: process.env.PAYPAL_CLIENT_ID || '',
    paypalClientSecret: process.env.PAYPAL_CLIENT_SECRET || '',
    paypalWebhookId: process.env.PAYPAL_WEBHOOK_ID || '',
    public: {
      siteName: process.env.NUXT_PUBLIC_SITE_NAME || 'FAMtastic Designs',
      siteUrl,
      cmsMode,
      paymentMode,
      portalMode,
      leadStorageMode: process.env.NUXT_PUBLIC_LEAD_STORAGE_MODE || 'local',
      bookingProvider,
      bookingUrl: process.env.BOOKING_URL || '',
      bookingEmbedUrl: process.env.BOOKING_EMBED_URL || '',
      calcomUrl: process.env.CALCOM_URL || '',
      caldiyUrl: process.env.CALDIY_URL || '',
      calendsoUrl: process.env.CALENDSO_URL || '',
      easyappointmentsUrl: process.env.EASYAPPOINTMENTS_URL || '',
      paypalEnv: process.env.PAYPAL_ENV || 'sandbox',
      paypalBusinessEmail: process.env.PAYPAL_BUSINESS_EMAIL || '',
      paypalReturnUrl: process.env.PAYPAL_RETURN_URL || '',
      paypalCancelUrl: process.env.PAYPAL_CANCEL_URL || '',
      paypalCheckoutUrl: process.env.PAYPAL_CHECKOUT_URL || '',
      paypalInvoiceUrl: process.env.PAYPAL_INVOICE_URL || '',
      paypalCarePlanUrl: process.env.PAYPAL_CARE_PLAN_URL || '',
      stripePublishableKey: process.env.STRIPE_PUBLISHABLE_KEY || '',
      stripeAuditPaymentLink: process.env.STRIPE_AUDIT_PAYMENT_LINK || '',
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
