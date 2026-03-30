import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
  plugins: [vue()],
  build: {
    // 1. On sort du dossier source pour envoyer les fichiers compilés au bon endroit pour WP
    outDir: "../../js/admin",

    // 2. IMPORTANT : Ne pas vider le dossier de destination (au cas où tu aurais d'autres apps compilées dedans)
    emptyOutDir: false,

    // 3. Pas de hash aléatoire pour que le fichier PHP sache toujours quoi charger
    rollupOptions: {
      input: {
        // APP 1 : La Metabox SEO (Pages)
        "pc-seo-app": resolve(__dirname, "seo-metabox/main.js"),
        // APP 2 : La Metabox SEO (Articles)
        "pc-post-seo-app": resolve(__dirname, "post-seo-metabox/main.js"),
        // APP 3 : La Metabox Avis (Reviews)
        "pc-review-app": resolve(__dirname, "reviews-metabox/main.js"),
      },
      output: {
        entryFileNames: "[name].min.js",
        chunkFileNames: "[name].min.js",
        assetFileNames: "[name].min.[ext]", // Générera pc-seo-app.min.css s'il y a du style
      },
    },
  },
});
