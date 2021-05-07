module.exports = {
  title: 'LibreSign',
  base: '/libresign/',
  description: 'Libre digital signature app for Nextcloud.',
  theme: 'openapi',
  themeConfig: {
    servers: ['/api/0.1/'],
    locales: {
      '/': {
        sidebar: 'auto',
        nav: [
          {
            text: 'Guide',
            link: '/Getting-started' },
          {
            text: 'LibreCode',
            link: 'https://librecode.coop/'
          },
          {
            text: 'Github',
            link: 'https://github.com/libresign/libresign'
          }
        ]
      },
      '/pt/': {
        sidebar: 'auto',
        nav: [
          {
            text: 'Guia',
            link: '/Getting-started' },
          {
            text: 'LibreCode',
            link: 'https://librecode.coop/'
          },
          {
            text: 'Github',
            link: 'https://github.com/libresign/libresign'
          }
        ]
      }
    }
  },
  locales: {
    '/': {
      lang: 'en-US'
    },
    '/pt/': {
      lang: 'pt-BR',
      description: 'App de assinatura digital livre para Nextcloud.'
    }
  },
  plugins: [
    '@vuepress/plugin-back-to-top',
    '@vuepress/plugin-medium-zoom',
  ]
};
