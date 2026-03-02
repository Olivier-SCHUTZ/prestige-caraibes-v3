/**
 * COMPOSANT : Gestionnaire de la Carte Interactive (Leaflet)
 * Partagé entre la recherche de Logements et d'Expériences.
 */
(function ($, w) {
  "use strict";

  class PCSearchMap {
    constructor(containerId) {
      this.$container = $(`#${containerId}`);
      if (!this.$container.length || typeof w.L === "undefined") return;

      this.map = null;
      this.markersLayer = null;
      this.init();
    }

    init() {
      // Centre par défaut sur la Guadeloupe
      this.map = L.map(this.$container[0]).setView([16.265, -61.551], 10);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png").addTo(
        this.map,
      );
      this.markersLayer = new L.LayerGroup().addTo(this.map);
    }

    updateMarkers(items) {
      if (!this.map || !this.markersLayer) return;

      this.markersLayer.clearLayers();
      if (!items || !items.length) return;

      const bounds = [];

      items.forEach((item) => {
        const lat = item.latitude || item.lat;
        const lng = item.longitude || item.lng;

        if (!lat || !lng) return;

        const latLng = [parseFloat(lat), parseFloat(lng)];
        const title = item.title || item.post_title || "";
        const price = item.price || item.base_price_from || "";
        const link = item.link || item.permalink || "#";

        // Texte du bouton selon s'il s'agit d'une expérience ou d'un logement
        const isExp = link.includes("/experience");
        const btnText = isExp ? "Voir l'expérience" : "Voir la villa";
        const priceText = price
          ? `${price}${!isExp ? "€ / nuit" : ""}<br>`
          : "";

        L.marker(latLng)
          .addTo(this.markersLayer)
          .bindPopup(
            `<strong>${title}</strong><br>${priceText}<a href="${link}" target="_blank" rel="noopener">${btnText}</a>`,
          );

        bounds.push(latLng);
      });

      if (bounds.length) {
        this.map.fitBounds(bounds, { padding: [50, 50] });
      }
    }
  }

  // Exposer l'instance de la carte globalement pour que l'AJAX puisse l'appeler
  $(function () {
    if ("requestIdleCallback" in w) {
      w.requestIdleCallback(() => {
        w.pcSearchMap = new PCSearchMap("pc-map-container");
      });
    } else {
      setTimeout(() => {
        w.pcSearchMap = new PCSearchMap("pc-map-container");
      }, 300);
    }
  });
})(jQuery, window);
