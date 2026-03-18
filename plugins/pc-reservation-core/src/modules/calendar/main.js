import { createApp } from "vue";
import { createPinia } from "pinia";
import CalendarApp from "./CalendarApp.vue";

// On cherche le nouveau conteneur défini dans app-shell.php
const mountEl = document.querySelector("#pc-calendar-v2-app");

if (mountEl) {
  const pinia = createPinia();
  const app = createApp(CalendarApp);

  app.use(pinia);
  app.mount(mountEl);
}
