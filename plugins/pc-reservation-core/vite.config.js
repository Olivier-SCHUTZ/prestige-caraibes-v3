import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: "dist",
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        dashboard: resolve(__dirname, "src/modules/dashboard/main.js"),
        experience: resolve(__dirname, "src/modules/experience/main.js"),
        // ✨ NOUVEAU : On ajoute le module Logements
        housing: resolve(__dirname, "src/modules/housing/main.js"),
      },
    },
  },
  resolve: {
    alias: {
      "@": resolve(__dirname, "src"),
    },
  },
});
