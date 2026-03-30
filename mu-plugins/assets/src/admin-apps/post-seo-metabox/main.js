import { createApp } from "vue";
import App from "./App.vue";

// On attend que le DOM soit complètement chargé pour être sûr que la div générée par PHP est présente
document.addEventListener("DOMContentLoaded", () => {
  const mountEl = document.getElementById("pc-post-seo-vue-app");

  // On ne monte l'application que si le conteneur existe (sécurité supplémentaire)
  if (mountEl) {
    createApp(App).mount(mountEl);
  }
});
