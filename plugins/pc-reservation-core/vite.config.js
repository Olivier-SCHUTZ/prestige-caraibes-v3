import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
  plugins: [vue()],

  // Configuration pour faire cohabiter Vite et WordPress
  build: {
    // Le dossier où seront générés nos fichiers optimisés
    outDir: "dist",

    // Nettoie le dossier dist/ à chaque nouveau build
    emptyOutDir: true,

    // CRUCIAL : Génère un fichier manifest.json pour que PHP trouve les assets
    manifest: true,

    rollupOptions: {
      // Nos différents points d'entrée (Code Splitting)
      // Pour l'instant on prépare celui du Dashboard, on ajoutera le calendrier etc. plus tard
      input: {
        dashboard: resolve(__dirname, "src/modules/dashboard/main.js"),
      },
    },
  },

  resolve: {
    // Permet d'utiliser "@" dans nos imports pour pointer directement vers "src/"
    alias: {
      "@": resolve(__dirname, "src"),
    },
  },
});
