import { createApp } from "vue";
import { createPinia } from "pinia";
import SettingsApp from "./SettingsApp.vue";

/**
 * Point d'entrée principal pour le module Configuration V2
 */
document.addEventListener("DOMContentLoaded", () => {
  // On cherche le conteneur HTML spécifique aux réglages
  const mountContainer = document.getElementById("pc-settings-v2-app");

  if (mountContainer) {
    // Initialisation de l'application Vue
    const app = createApp(SettingsApp);

    // Initialisation du State Management (Pinia)
    const pinia = createPinia();

    // Injection des plugins et montage
    app.use(pinia);
    app.mount("#pc-settings-v2-app");

    console.log(
      "🚀 PC Reservation Core : Module Configuration V2 monté avec succès !",
    );
  }
});
