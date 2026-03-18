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
          :class="['pc-message-item', msg.css_classes]"
        >
          <div class="pc-msg-header">
            <span class="pc-msg-avatar">{{ msg.sender_avatar }}</span>
            <span class="pc-msg-author">{{ msg.sender_name }}</span>
            <span class="pc-msg-date">{{ msg.formatted_date }}</span>
          </div>
          <div class="pc-msg-body" v-html="msg.corps"></div>
          <div class="pc-msg-footer" v-if="msg.status_badge">
            <span :class="['pc-badge', msg.status_badge.class]">
              {{ msg.status_badge.icon }} {{ msg.status_badge.text }}
            </span>
          </div>
        </div>
      </template>
    </div>

    <div class="pc-message-composer">
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
import { useMessagingStore } from "@/stores/messaging-store"; // Ton store Pinia

const props = defineProps({
  reservationId: {
    type: Number,
    required: true,
  },
});

const store = useMessagingStore();
const newMessage = ref("");

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
    custom_subject: `Message depuis ${activeTab.value}`, // À adapter selon tes besoins
    custom_body: newMessage.value,
    template_id: "custom",
    channel_source: activeTab.value,
  });

  if (!store.error) {
    newMessage.value = ""; // Vider le champ après envoi réussi
  }
};

const capitalize = (str) => str.charAt(0).toUpperCase() + str.slice(1);

// Cycle de vie
onMounted(() => {
  store.fetchConversation(props.reservationId);
  // store.enableRealTimeUpdates(); // À décommenter plus tard si implémenté
});

onUnmounted(() => {
  // store.disableRealTimeUpdates();
});
</script>

<style scoped>
/* Conteneur principal */
.message-center {
  display: flex;
  flex-direction: column;
  height: 500px; /* Hauteur fixe exemple pour la zone de scroll */
  border: 1px solid #ddd;
  border-radius: 8px;
  background-color: #f9f9f9;
}

/* Onglets intelligents (Tabs) */
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
  color: #0073aa; /* Bleu WordPress */
  border-bottom: 2px solid #0073aa;
  background-color: #fff;
}

/* Zone d'historique de conversation (Scrollable) */
.pc-conversation-history {
  flex: 1;
  overflow-y: auto;
  padding: 15px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Bulles de message - Base */
.pc-message-item {
  max-width: 80%;
  padding: 10px 15px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
}

/* Direction des messages */
.pc-message-item.entrant {
  align-self: flex-start;
  background-color: #fff;
  border: 1px solid #ddd;
}

.pc-message-item.sortant {
  align-self: flex-end;
  background-color: #e3f2fd; /* Bleu clair pour l'hôte */
  color: #333;
}

/* Style spécifique aux notes internes */
.pc-message-item.pc-msg--notes {
  background-color: #fff3cd; /* Jaune warning */
  border: 1px solid #ffeeba;
  max-width: 90%;
}

/* Éléments de structure du message */
.pc-msg-header {
  display: flex;
  align-items: center;
  font-size: 0.8em;
  color: #777;
  margin-bottom: 5px;
}

.pc-msg-avatar {
  margin-right: 5px;
}
.pc-msg-author {
  font-weight: bold;
  margin-right: 10px;
}
.pc-msg-date {
  margin-left: auto;
  font-style: italic;
}

.pc-msg-body {
  font-size: 0.95em;
  line-height: 1.4;
  word-break: break-word;
}

.pc-msg-footer {
  margin-top: 5px;
  display: flex;
  justify-content: flex-end;
}

/* Badges de statut */
.pc-badge {
  font-size: 0.75em;
  padding: 2px 6px;
  border-radius: 4px;
}
.pc-badge--draft {
  background-color: #eee;
  color: #555;
}
.pc-badge--success {
  background-color: #c3e6cb;
  color: #155724;
}
.pc-badge--error {
  background-color: #f5c6cb;
  color: #721c24;
}
.pc-badge--warning {
  background-color: #ffeeba;
  color: #856404;
}

/* Zone de composition (Bas) */
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
</style>
