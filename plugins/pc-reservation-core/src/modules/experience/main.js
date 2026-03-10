import { createApp } from "vue";
import { createPinia } from "pinia";
import ExperienceApp from "./ExperienceApp.vue";

/**
 * Point d'entrée principal pour le module Expériences V2 (Vue.js)
 * Pattern Strangler : On ne monte l'application que si le conteneur
 * '#pc-experience-v2-app' est explicitement présent sur la page.
 */
document.addEventListener("DOMContentLoaded", () => {
  const mountContainer = document.getElementById("pc-experience-v2-app");

  if (mountContainer) {
    // Initialisation de l'application Vue
    const app = createApp(ExperienceApp);

    // Initialisation du State Management (Pinia)
    const pinia = createPinia();

    // Injection des plugins et montage
    app.use(pinia);
    app.mount("#pc-experience-v2-app");

    console.log(
      "🚀 PC Reservation Core : Module Expériences V2 monté avec succès !",
    );
  }
});
