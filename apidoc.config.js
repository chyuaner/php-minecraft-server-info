const pkg = require('./package.json');

module.exports = {
  name: pkg.name,
  version: pkg.version,
  description: pkg.description,
  url: process.env.APIDOC_URL || "https://mc-api.yuaner.tw",
  sampleUrl: process.env.APIDOC_URL || "https://mc-api.yuaner.tw",
  order: [
    "getAllMods",
    "getmod",
    "DownloadModsZip",
    "DownloadFile",
    "DownloadFilePhp",
    "getAll",
    "getFolderNames",
    "DownloadZip",
    "getAllSingle",
    "DownloadSingleZip",
    "DownloadSingleFile",
    "Ping",
    "GetServerBanner",
    "OnlinePlayers"
  ],
  header: {
    title: "概要",
    filename: "apidoc-header.md"
  },
  footer: {
    title: "關於",
    filename: "apidoc-footer.md"
  }
};
