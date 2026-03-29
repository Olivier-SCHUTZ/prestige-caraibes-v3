import { createApp } from "vue";
import App from "./App.vue";

// On s'assure que le conteneur existe (c'est le div généré par PHP)
const container = document.getElementById("pc-seo-vue-app");

if (container) {
  createApp(App).mount("#pc-seo-vue-app");
}
