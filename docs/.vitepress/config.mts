import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "伺服器技術文件",
  description: "Minecraft Server API & Documentation",
  base: '/docs/',
  outDir: '../public/docs',
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: '首頁', link: '/' },
      { text: 'HTTP API', link: '/api/', target: '_self' }
    ],

    sidebar: [
      {
        text: 'Guide',
        items: [
          { text: '關於本站', link: '/' }
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/chyuaner/php-minecraft-server-info' }
    ]
  }
})
