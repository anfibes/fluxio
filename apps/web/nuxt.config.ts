import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  app: {
    head: {
      // Runs synchronously before first paint to apply stored/system theme and prevent FOUC.
      script: [
        {
          innerHTML: `(function(){try{var p=localStorage.getItem('fluxio.theme')||'system';var t=p==='system'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;document.documentElement.dataset.theme=t;document.documentElement.style.colorScheme=t;}catch(e){}})()`,
        },
      ],
    },
  },

  modules: ['@nuxtjs/i18n'],

  css: ['~/assets/css/main.css'],

  vite: {
    plugins: [tailwindcss()],
  },

  runtimeConfig: {
    public: {
        apiBase: import.meta.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
    },
  },

  i18n: {
    defaultLocale: 'en',
    strategy: 'no_prefix',
    langDir: 'locales/',
    locales: [
      { code: 'en', name: 'English', file: 'en.json' },
      { code: 'it', name: 'Italiano', file: 'it.json' },
    ],
    bundle: {
      optimizeTranslationDirective: false,
    },
  },
})
