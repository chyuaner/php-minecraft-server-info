import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "Minecraft模組同步專用PHP後端",
  description: "Minecraft Server API & Documentation",
  base: process.env.BASE_PATH || '/docs/',
  outDir: '../public/docs',
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: '首頁', link: '/' },
      { text: '安裝與開發說明', link: '/use/getting-started' },
      { text: 'API 說明', link: '/api/', target: '_self' },
      { text: '下載客戶端', link: 'https://github.com/chyuaner/python-minecraft-mods-sync/releases' },
      { text: '官網', link: 'https://mcweb.barian.moe' }
    ],

    sidebar: [
      {
        text: '安裝與使用',
        items: [
          { text: '概覽 <span class="VPBadge warning">施工中</span>', link: '/use/overview' },
          { text: '系統需求 <span class="VPBadge warning">施工中</span>', link: '/use/requirement' },
          { text: '安裝、架設指南 <span class="VPBadge warning">施工中</span>', link: '/use/getting-started' },
        ],
      },
      {
        text: '技術指南',
        items: [
          { text: '專案架構與設計原則 <span class="VPBadge warning">施工中</span>', link: '/dev/architecture' },
          { text: 'Mod模組解析 <span class="VPBadge warning">施工中</span>'},
          { text: '伺服器資訊狀態查詢 <span class="VPBadge warning">施工中</span>'},
          { text: '快取設計 <span class="VPBadge warning">施工中</span>'},
          { text: 'zip打包處理 <span class="VPBadge warning">施工中</span>'},
          { text: '效能優化策略 <span class="VPBadge warning">施工中</span>'},
        ],
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/chyuaner/php-minecraft-server-info' }
    ]
  }
})
