import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";

/**
 * Point d'entrée principal pour le Dashboard V2 (Vue.js)
 * Pattern Strangler : On ne monte l'application que si le conteneur
 * '#pc-dashboard-v2-app' est explicitement présent sur la page.
 */
document.addEventListener("DOMContentLoaded", () => {
  const mountContainer = document.getElementById("pc-dashboard-v2-app");

  if (mountContainer) {
    // Initialisation de l'application Vue
    const app = createApp(App);

    // Initialisation du State Management (Pinia)
    const pinia = createPinia();

    // Injection des plugins et montage
    app.use(pinia);
    app.mount("#pc-dashboard-v2-app");

    console.log("🚀 PC Reservation Core : Dashboard V2 monté avec succès !");
  }
});
