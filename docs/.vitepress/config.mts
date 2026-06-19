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
      { text: '安裝與開發說明', link: '/use/1-overview', activeMatch: '/(use|dev)/' },
      { text: 'API 說明', link: '/api/', target: '_self' },
      { text: '下載客戶端', link: 'https://github.com/chyuaner/python-minecraft-mods-sync/releases' },
      { text: '官網', link: 'https://mcweb.barian.moe' }
    ],

    sidebar: [
      {
        text: '安裝與使用',
        items: [
          { text: '概覽', link: '/use/1-overview' },
          { text: '快速開始', link: '/use/2-quick-start' },
          { text: '系統需求與依賴項', link: '/use/3-system-requirements-and-dependencies' },
          { text: '安裝與設定指南', link: '/use/4-installation-and-setup' },
          { text: '生產環境部署 (Nginx)', link: '/use/5-production-deployment-with-nginx' },
        ],
      },
      {
        text: '技術指南',
        items: [
          { text: '專案架構與設計原則', link: '/dev/6-project-architecture-and-design-principles' },
          { text: 'Mod 解析系統', link: '/dev/7-mod-parsing-system-neoforge-forge-fabric' },
          { text: '伺服器監控與狀態查詢', link: '/dev/8-server-monitoring-and-status-queries' },
          { text: '快取機制與快取失效', link: '/dev/9-caching-mechanism-and-cache-invalidation' },
          { text: 'Zip 歸檔生成與管理', link: '/dev/10-zip-archive-generation-and-management' },
          { text: 'Mod 資訊 API', link: '/dev/11-mod-information-apis' },
          { text: '伺服器狀態 API', link: '/dev/12-server-status-apis' },
          { text: '檔案服務 API', link: '/dev/13-file-serving-apis' },
          { text: '回應格式化與內容協商', link: '/dev/14-response-formatting-and-content-negotiation' },
          { text: '多伺服器配置', link: '/dev/15-multi-server-configuration' },
          { text: '錯誤處理與異常管理', link: '/dev/16-error-handling-and-exception-management' },
          { text: '效能優化策略', link: '/dev/17-performance-optimization-strategies' },
        ],
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/chyuaner/php-minecraft-server-info' }
    ]
  }
})
