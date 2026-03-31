import { createApp } from "vue";
import { createPinia } from "pinia";
import TemplatesApp from "./TemplatesApp.vue";

/**
 * Point d'entrée principal pour le module Modèles & Automatisations V2
 */
document.addEventListener("DOMContentLoaded", () => {
  // On cherche le conteneur HTML spécifique aux modèles
  const mountContainer = document.getElementById("pc-templates-v2-app");

  if (mountContainer) {
    // Initialisation de l'application Vue
    const app = createApp(TemplatesApp);

    // Initialisation du State Management (Pinia)
    const pinia = createPinia();

    // Injection des plugins et montage
    app.use(pinia);
    app.mount("#pc-templates-v2-app");

    console.log(
      "🚀 PC Reservation Core : Module Modèles & Automatisations V2 monté avec succès !",
    );
  }
});
