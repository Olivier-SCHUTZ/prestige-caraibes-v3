import axios from "axios";

/**
 * API Client Centralisé (Axios)
 * Architecture V2 - Gère automatiquement l'URL de base et l'injection des Nonces WordPress.
 */

// 1. Récupération des variables globales injectées par WordPress
// On s'assure de ne pas faire crasher l'app si la variable n'est pas encore définie
const wpVars = window.pcReservationVars || {
  ajax_url: "/wp-admin/admin-ajax.php",
  nonce: "",
};

// 2. Création de l'instance Axios
const apiClient = axios.create({
  baseURL: wpVars.ajax_url,
  // On simule le comportement natif de jQuery/WordPress pour la compatibilité avec admin-ajax.php
  headers: {
    "Content-Type": "application/x-www-form-urlencoded",
    "X-Requested-With": "XMLHttpRequest",
  },
});

// 3. Intercepteur de REQUÊTES : Blindage de la sécurité avant l'envoi
apiClient.interceptors.request.use(
  (config) => {
    // WordPress s'attend à recevoir le nonce sous le nom "security" pour admin-ajax

    if (config.method === "post") {
      // Initialisation de la payload si vide
      if (!config.data) {
        config.data = {};
      }

      // Si on envoie un objet FormData (ex: upload d'image)
      if (config.data instanceof FormData) {
        if (!config.data.has("security")) {
          config.data.append("security", wpVars.nonce);
        }
        if (!config.data.has("nonce")) {
          config.data.append("nonce", wpVars.nonce); // Rétrocompatibilité Legacy
        }
      }
      // Si on envoie un objet JavaScript standard
      else {
        config.data.security = config.data.security || wpVars.nonce;
        config.data.nonce = config.data.nonce || wpVars.nonce; // Rétrocompatibilité Legacy
      }
    } else if (config.method === "get") {
      // Initialisation des paramètres si vides
      config.params = config.params || {};
      config.params.security = config.params.security || wpVars.nonce;
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// 4. Intercepteur de RÉPONSES : Gestion centralisée des erreurs
apiClient.interceptors.response.use(
  (response) => {
    // WordPress admin-ajax renvoie souvent { success: false, data: "message" }
    // On intercepte ça silencieusement pour logger en dev
    if (response.data && response.data.success === false) {
      console.warn(
        "⚠️ [API Client] Requête traitée mais succès = false :",
        response.data.data,
      );
    }
    return response;
  },
  (error) => {
    // Si la session WordPress a expiré (erreur 401 ou 403), on pourra
    // déclencher une redirection vers la page de login ici plus tard.
    console.error("🚨 [API Client] Erreur réseau ou serveur :", error);
    return Promise.reject(error);
  },
);

export default apiClient;
