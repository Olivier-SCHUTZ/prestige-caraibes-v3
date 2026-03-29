import { createApp } from "vue";
import { createPinia } from "pinia";
import DestinationApp from "./DestinationApp.vue";

/**
 * Point d'entrée principal pour le module Destinations V2 (Vue.js)
 */
document.addEventListener("DOMContentLoaded", () => {
  // On cherche le conteneur HTML spécifique aux destinations
  const mountContainer = document.getElementById("pc-destination-v2-app");

  if (mountContainer) {
    // Initialisation de l'application Vue
    const app = createApp(DestinationApp);

    // Initialisation du State Management (Pinia)
    const pinia = createPinia();

    // Injection des plugins et montage
    app.use(pinia);
    app.mount("#pc-destination-v2-app");

    console.log(
      "🚀 PC Reservation Core : Module Destinations V2 monté avec succès !",
    );
  }
});
