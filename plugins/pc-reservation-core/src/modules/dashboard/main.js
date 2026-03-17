import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";

const initDashboard = () => {
  // Assure-toi que cet ID correspond à la div générée par ton shortcode actuel
  const container = document.getElementById("pc-reservation-dashboard-app");

  if (container) {
    const pinia = createPinia();
    const app = createApp(App);

    app.use(pinia);
    app.mount(container);
  }
};

// On s'assure que le DOM est chargé
document.addEventListener("DOMContentLoaded", initDashboard);
