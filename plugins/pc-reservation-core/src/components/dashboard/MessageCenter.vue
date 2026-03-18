<template>
  <div class="message-center">
    <div class="pc-tabs-navigation">
      <button
        v-for="tab in ['email', 'whatsapp', 'notes']"
        :key="tab"
        :class="['pc-tab-btn', { active: activeTab === tab }]"
        @click="switchTab(tab)"
      >
        {{ capitalize(tab) }}
      </button>
    </div>

    <div class="pc-conversation-history">
      <div v-if="isLoading" class="pc-loading-spinner">
        Chargement des messages...
      </div>

      <template v-else>
        <div
          v-for="msg in filteredMessages"
          :key="msg.id"
          :class="['vue-message-item', msg.direction]"
        >
          <div class="vue-msg-header">
            <img
              v-if="msg.sender_avatar && msg.sender_avatar.includes('http')"
              :src="msg.sender_avatar"
              class="vue-msg-avatar-img"
              alt="Avatar"
            />
            <span v-else class="vue-msg-avatar">{{ msg.sender_avatar }}</span>

            <span class="vue-msg-author">{{ msg.sender_name }}</span>
            <span class="vue-msg-date">{{ msg.formatted_date }}</span>
          </div>
          <div class="vue-msg-body" v-html="formatMessageBody(msg.corps)"></div>

          <div
            class="vue-msg-attachments"
            v-if="
              msg.metadata &&
              msg.metadata.attachments &&
              msg.metadata.attachments.length > 0
            "
          >
            <span
              v-for="(att, idx) in msg.metadata.attachments"
              :key="idx"
              class="pc-attachment-badge"
            >
              📎 {{ att.name }}
            </span>
          </div>

          <div class="vue-msg-footer" v-if="msg.status_badge">
            <span :class="['pc-badge', msg.status_badge.class]">
              {{ msg.status_badge.icon }} {{ msg.status_badge.text }}
            </span>
          </div>
        </div>
      </template>
    </div>

    <div class="pc-message-composer">
      <div
        v-if="activeTab === 'email' && docStore.templates"
        class="pc-attachments-panel"
      >
        <span class="pc-attach-title"
          >📎 Joindre des documents (Générés automatiquement) :</span
        >
        <div class="pc-doc-checkboxes">
          <template v-for="(group, key) in docStore.templates" :key="key">
            <label
              v-for="tpl in group.items"
              :key="tpl.id"
              class="pc-checkbox-label"
            >
              <input type="checkbox" :value="tpl.id" v-model="selectedDocs" />
              {{ tpl.label.replace(/[^a-zA-ZÀ-ÿ\s]/g, "") }}
            </label>
          </template>
        </div>
      </div>

      <textarea
        v-model="newMessage"
        :placeholder="`Écrire un message via ${capitalize(activeTab)}...`"
        :disabled="isSending"
      ></textarea>

      <div class="pc-composer-actions">
        <button
          class="pc-btn-primary"
          @click="handleSend"
          :disabled="!newMessage.trim() || isSending"
        >
          {{ isSending ? "Envoi en cours..." : "Envoyer" }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useMessagingStore } from "@/stores/messaging-store";
import { useDocumentStore } from "@/stores/document-store";

const props = defineProps({
  reservationId: {
    type: Number,
    required: true,
  },
});

const store = useMessagingStore();
const docStore = useDocumentStore();
const newMessage = ref("");
const selectedDocs = ref([]);

// États réactifs depuis le store
const activeTab = computed(() => store.activeTab);
const filteredMessages = computed(() => store.filteredMessages);
const isLoading = computed(() => store.isLoading);
const isSending = computed(() => store.isSending);

// Actions
const switchTab = (tab) => {
  store.activeTab = tab;
};

const handleSend = async () => {
  if (!newMessage.value.trim()) return;

  await store.sendMessage({
    reservation_id: props.reservationId,
    custom_subject: `Message depuis ${activeTab.value}`,
    custom_body: newMessage.value,
    template_id: "custom",
    channel_source: activeTab.value,
    document_ids: selectedDocs.value.join(","),
  });

  if (!store.error) {
    newMessage.value = "";
    selectedDocs.value = [];
  }
};

const capitalize = (str) => str.charAt(0).toUpperCase() + str.slice(1);

// Formateur de texte ultra-puissant
const formatMessageBody = (html) => {
  if (!html) return "";

  // 1. Suppression rétroactive des Magic Quotes (Barres obliques / Antislashs)
  let cleanHtml = html.replace(/\\'/g, "'").replace(/\\"/g, '"');

  // NOUVEAU : Pulvérisation brutale de l'URL Gravatar AVANT le parsing du HTML
  // (Détruit tout lien gravatar, qu'il contienne des variables complexes ou non)
  cleanHtml = cleanHtml.replace(
    /https?:\/\/[^\s<"']*gravatar\.com[^\s<"']*/gi,
    "",
  );

  try {
    const parser = new DOMParser();
    const doc = parser.parseFromString(cleanHtml, "text/html");

    // 2. Transformation des URLs PDF en jolis badges cliquables
    const links = doc.querySelectorAll("a");
    links.forEach((link) => {
      if (link.href.toLowerCase().includes(".pdf")) {
        const urlParts = link.href.split("/");
        let filename = urlParts[urlParts.length - 1].split("?")[0];
        link.innerHTML = `📎 ${decodeURIComponent(filename)}`;
        link.classList.add("pc-pdf-badge");
      }
    });

    // 3. Suppression des images invisibles (Tracking) ou des gravatars cassés (qui causent l'URL géante)
    const trackingImgs = doc.querySelectorAll(
      'img[src*="gravatar"], img[width="1"][height="1"]',
    );
    trackingImgs.forEach((img) => img.remove());

    // 4. Nettoyage final : suppression des liens Gravatar restants ou des liens devenus vides
    const allLinks = doc.querySelectorAll("a");
    allLinks.forEach((link) => {
      if (link.href.includes("gravatar") || link.textContent.trim() === "") {
        link.remove();
      }
    });

    return doc.body.innerHTML;
  } catch (e) {
    return cleanHtml;
  }
};

// Cycle de vie
onMounted(() => {
  store.fetchConversation(props.reservationId);
  docStore.fetchTemplates(props.reservationId);
  store.startPolling(props.reservationId);
});

onUnmounted(() => {
  store.stopPolling();
});
</script>

<style scoped>
/* ==========================================================
   1. STRUCTURE GLOBALE
========================================================== */
.message-center {
  display: flex;
  flex-direction: column;
  height: 500px;
  border: 1px solid #ddd;
  border-radius: 8px;
  background-color: #f9f9f9;
}

.pc-tabs-navigation {
  display: flex;
  border-bottom: 1px solid #ddd;
  background-color: #f1f1f1;
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
}

.pc-tab-btn {
  flex: 1;
  padding: 10px 15px;
  border: none;
  background: none;
  cursor: pointer;
  text-align: center;
  color: #555;
  font-weight: 500;
}

.pc-tab-btn.active {
  color: #0073aa;
  border-bottom: 2px solid #0073aa;
  background-color: #fff;
}

.pc-conversation-history {
  flex: 1;
  overflow-y: auto;
  padding: 15px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ==========================================================
   2. BULLES DE MESSAGE (ISOLATION VUE 3)
========================================================== */
.vue-message-item {
  max-width: 80%;
  padding: 12px 16px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.vue-message-item.entrant {
  align-self: flex-start;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-bottom-left-radius: 4px;
}

.vue-message-item.sortant {
  align-self: flex-end;
  background-color: #e0f2fe; /* Bleu clair propre */
  border: 1px solid #bae6fd;
  color: #0c4a6e;
  border-bottom-right-radius: 4px;
}

.vue-msg-header {
  display: flex;
  align-items: center;
  font-size: 0.85em;
  color: inherit;
  opacity: 0.8;
  margin-bottom: 8px;
  max-width: 100%; /* Sécurité anti-débordement */
  flex-wrap: wrap; /* Permet au texte de passer à la ligne si besoin */
}
.vue-msg-avatar {
  margin-right: 8px;
  font-size: 1.2em;
}
/* NOUVEAU : Style de la pastille image */
.vue-msg-avatar-img {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  margin-right: 8px;
  object-fit: cover;
}
.vue-msg-author {
  font-weight: 600;
  margin-right: 10px;
}
.vue-msg-date {
  margin-left: auto;
  font-style: italic;
  opacity: 0.7;
}

/* ==========================================================
   3. DOMPTAGE DU HTML DES EMAILS
========================================================== */
.vue-msg-body {
  font-size: 0.92rem;
  line-height: 1.5;
  word-break: break-word;
  overflow-wrap: break-word;
}

/* 3.1 Sécurité absolue pour les Tableaux (Évite que la signature ne casse tout) */
:deep(.vue-msg-body) table {
  width: 100% !important;
  max-width: 100% !important;
  border-collapse: collapse;
  /* SUPPRESSION de table-layout: fixed pour laisser les colonnes s'adapter à leur contenu (ex: logo vs texte) */
}
:deep(.vue-msg-body) td,
:deep(.vue-msg-body) th {
  /* On arrête de forcer la césure des mots dans les tableaux, c'est ce qui écrase la signature */
  word-wrap: normal !important;
  overflow-wrap: normal !important;
  word-break: normal !important;
  vertical-align: top;
}
:deep(.vue-msg-body) td * {
  /* Empêche les marges internes des paragraphes de la signature d'exploser la hauteur de la bulle */
  margin: 2px 0 !important;
}

/* 3.2 Images Responsives */
:deep(.vue-msg-body) img {
  max-width: 100% !important;
  height: auto !important;
}

/* 3.3 Espacement propre des paragraphes */
:deep(.vue-msg-body) p {
  margin: 0 0 10px 0 !important;
}
:deep(.vue-msg-body) p:last-child {
  margin-bottom: 0 !important;
}
:deep(.vue-msg-body) a {
  color: inherit;
  text-decoration: underline;
  font-weight: 600;
}

/* 3.4 Masquage des parasites PHP (Watermarks invisibles) */
:deep(.vue-msg-body) div[style*="opacity:0"],
:deep(.vue-msg-body) span[style*="opacity:0"] {
  display: none !important;
}

/* 3.5 Nettoyage du Bloc Stripe */
:deep(.vue-msg-body) div[style*="background-color:#f8f9fa"],
:deep(.vue-msg-body) div[style*="background:#f9f9f9"],
:deep(.vue-msg-body) div[style*="pcr-stripe-pay"] {
  background-color: rgba(0, 0, 0, 0.03) !important;
  border: 1px solid rgba(0, 0, 0, 0.05) !important;
  border-radius: 6px !important;
  padding: 10px !important;
  margin: 10px 0 !important;
}
:deep(.vue-msg-body) a[style*="background-color:#6772E5"] {
  padding: 6px 12px !important;
  font-size: 0.85em !important;
  text-decoration: none !important;
  display: inline-block !important;
  color: white !important;
}

/* 3.6 Badges PDF (Le trombone) */
:deep(.vue-msg-body) a.pc-pdf-badge {
  display: inline-flex;
  align-items: center;
  background-color: rgba(0, 0, 0, 0.05) !important;
  padding: 6px 10px !important;
  border-radius: 6px !important;
  text-decoration: none !important;
  font-weight: 600;
  font-size: 0.9em;
  border: 1px solid rgba(0, 0, 0, 0.1) !important;
  margin: 5px 0 !important;
  color: #334155 !important;
  word-break: normal !important;
}
:deep(.vue-message-item.sortant) a.pc-pdf-badge {
  background-color: rgba(255, 255, 255, 0.6) !important;
  border-color: rgba(0, 0, 0, 0.1) !important;
  color: #0c4a6e !important;
}

/* ==========================================================
   4. ZONE DE COMPOSITION ET BOUTONS
========================================================== */
.pc-message-composer {
  padding: 15px;
  border-top: 1px solid #ddd;
  background-color: #fff;
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
}

textarea {
  width: 100%;
  height: 60px;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  resize: vertical;
  font-family: inherit;
}

.pc-attachments-panel {
  margin-bottom: 12px;
  padding: 10px;
  background-color: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
}
.pc-attach-title {
  font-weight: 600;
  font-size: 0.85em;
  color: #475569;
  display: block;
  margin-bottom: 8px;
}
.pc-doc-checkboxes {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.pc-checkbox-label {
  font-size: 0.85em;
  display: flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  color: #334155;
}

.pc-composer-actions {
  margin-top: 10px;
  display: flex;
  justify-content: flex-end;
}

.pc-btn-primary {
  background-color: #0073aa;
  color: white;
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.pc-btn-primary:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}
.pc-loading-spinner {
  text-align: center;
  padding: 20px;
  color: #777;
}

/* ==========================================================
   5. PIÈCES JOINTES METADATA
========================================================== */
.vue-msg-attachments {
  margin-top: 10px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pc-attachment-badge {
  display: inline-flex;
  align-items: center;
  background-color: rgba(0, 0, 0, 0.05);
  padding: 6px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.85em;
  border: 1px solid rgba(0, 0, 0, 0.1);
  color: #334155;
}

.vue-message-item.sortant .pc-attachment-badge {
  background-color: rgba(255, 255, 255, 0.6);
  border-color: rgba(0, 0, 0, 0.1);
  color: #0c4a6e;
}
</style>
