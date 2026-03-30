import { createApp } from "vue";
import App from "./App.vue";

document.addEventListener("DOMContentLoaded", () => {
  const mountEl = document.getElementById("pc-review-vue-app");
  if (mountEl) {
    createApp(App).mount(mountEl);
  }
});
