import { createApp } from "vue";
import { createPinia } from "pinia";
import CalendarApp from "./CalendarApp.vue";

// On cherche le conteneur défini dans le shortcode legacy
const mountEl = document.querySelector("[data-pc-calendar-vue]");

if (mountEl) {
  const pinia = createPinia();
  const app = createApp(CalendarApp);

  app.use(pinia);
  app.mount(mountEl);
}
