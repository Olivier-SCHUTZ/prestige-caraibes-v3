import { createApp } from "vue";
import { createPinia } from "pinia";
import HousingApp from "./HousingApp.vue";

/**
 * Point d'entrée pour le module Logements V2
 */
document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("pc-housing-v2-app");

  if (container) {
    const app = createApp(HousingApp);
    const pinia = createPinia();

    app.use(pinia);
    app.mount("#pc-housing-v2-app");

    console.log("🏠 PC Reservation Core : Module Logements V2 prêt !");
  }
});
