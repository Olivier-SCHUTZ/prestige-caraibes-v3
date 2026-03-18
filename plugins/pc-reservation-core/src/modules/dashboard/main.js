import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";

const initDashboard = () => {
  // On cible le nouveau conteneur défini directement dans app-shell.php
  const container = document.getElementById("pc-dashboard-v2-app");

  if (container) {
    const pinia = createPinia();
    const app = createApp(App);

    app.use(pinia);
    app.mount(container);
  }
};

// On s'assure que le DOM est chargé
document.addEventListener("DOMContentLoaded", initDashboard);
