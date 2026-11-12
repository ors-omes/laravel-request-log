import { defineConfig } from 'vitepress'
import llmstxt, { copyOrDownloadAsMarkdownButtons } from 'vitepress-plugin-llms'

const repo = 'https://github.com/ors-omes/laravel-request-log'
const site = 'https://ors-omes.github.io/laravel-request-log'

export default defineConfig({
  title: 'Laravel Request Log',
  description: 'Structured, redacted, queryable HTTP request log for Laravel 12 and 13.',
  lang: 'en-US',
  base: '/laravel-request-log/',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['public/demo/**'],
  ignoreDeadLinks: [/^\/demo\//],

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-request-log/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#2457c5' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Laravel Request Log' }],
    ['meta', { property: 'og:description', content: 'Every mutation and every error, recorded safely.' }],
    ['meta', { property: 'og:image', content: `${site}/screenshots/list.png` }],
  ],

  vite: {
    plugins: [
      llmstxt({
        domain: 'https://ors-omes.github.io',
        description: 'Structured, redacted, queryable HTTP request log for Laravel 12 and 13.',
        details: 'Records every POST/PUT/PATCH/DELETE and every 4xx/5xx response (any method) after the response is sent: redacted and shortened payloads and responses, exception with snippet and grouping hash, query statistics, user, tenant, subject, route and action, with a reference code shared through the Laravel Context, a response header and error responses.',
      }),
    ],
  },

  markdown: {
    config(md) {
      md.use(copyOrDownloadAsMarkdownButtons)
    },
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/introduction', activeMatch: '/guide/' },
      { text: 'Reference', link: '/reference/configuration', activeMatch: '/reference/' },
      { text: 'Demo', link: '/demo/index.html', target: '_self' },
      {
        text: 'v0.1.0-beta.2',
        items: [
          { text: 'Changelog', link: '/reference/changelog' },
          { text: 'Releases', link: `${repo}/releases` },
          { text: 'llms.txt', link: `${site}/llms.txt` },
        ],
      },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Getting started',
          items: [
            { text: 'Introduction', link: '/guide/introduction' },
            { text: 'Installation', link: '/guide/installation' },
          ],
        },
        {
          text: 'Recording',
          items: [
            { text: 'What gets recorded', link: '/guide/recording' },
            { text: 'What is stored', link: '/guide/stored-data' },
            { text: 'Reference codes', link: '/guide/reference-codes' },
            { text: 'Application hooks', link: '/guide/hooks' },
          ],
        },
        {
          text: 'Reading the log',
          items: [
            { text: 'Viewer', link: '/guide/viewer' },
            { text: 'JSON API', link: '/guide/api' },
            { text: 'Telescope', link: '/guide/telescope' },
          ],
        },
        {
          text: 'Operations',
          items: [
            { text: 'Security and privacy', link: '/guide/security' },
            { text: 'Retention and audit', link: '/guide/retention-and-audit' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Configuration', link: '/reference/configuration' },
            { text: 'Database schema', link: '/reference/schema' },
            { text: 'Artisan commands', link: '/reference/commands' },
            { text: 'Changelog', link: '/reference/changelog' },
          ],
        },
      ],
    },

    socialLinks: [{ icon: 'github', link: repo }],

    editLink: {
      pattern: `${repo}/edit/main/docs/:path`,
      text: 'Edit this page on GitHub',
    },

    search: { provider: 'local' },

    outline: { level: [2, 3] },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 ORS OMES',
    },
  },
})
