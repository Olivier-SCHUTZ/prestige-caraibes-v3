<template>
  <div class="pc-tab-api">
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-6">
        <label>Fournisseur d'API Email</label>
        <select v-model="formData.pc_api_provider" class="pc-input">
          <option value="none">Aucun (désactivé)</option>
          <option value="brevo">Brevo (Sendinblue)</option>
        </select>
      </div>
      
      <div class="pc-form-group pc-col-6" v-if="formData.pc_api_provider !== 'none'">
        <label>Emails entrants activés</label>
        <div class="toggle-wrapper" style="margin-top: 10px;">
          <input type="checkbox" id="email_enabled" v-model="formData.pc_inbound_email_enabled" />
          <label for="email_enabled" style="font-weight:normal; display:inline-block; margin-left:8px;">Autoriser la réception d'emails</label>
        </div>
      </div>
    </div>

    <div class="pc-form-row" v-if="formData.pc_api_provider !== 'none'">
      <div class="pc-form-group pc-col-6">
        <label>Clé API</label>
        <input type="password" v-model="formData.pc_api_key" class="pc-input" placeholder="xkeysib-..." />
      </div>
      <div class="pc-form-group pc-col-6">
        <label>Secret Webhook</label>
        <input type="text" v-model="formData.pc_webhook_secret" class="pc-input" placeholder="Token de sécurité..." />
      </div>
    </div>

    <hr class="separator" />

    <div class="simulator-block">
      <h4 class="block-title">🛠️ Simulateur de Webhook (Test Local)</h4>
      <p class="pc-help-text mb-15">Simulez la réception d'un webhook (Email Brevo ou WhatsApp). Collez un payload JSON ci-dessous.</p>
      
      <div class="pc-form-group">
        <textarea v-model="simulationPayload" class="pc-input pc-code-editor" rows="10"></textarea>
      </div>

      <div class="simulator-actions">
        <button class="pc-btn pc-btn-secondary" @click="runSimulation" :disabled="isSimulating">
          {{ isSimulating ? '⏳ Simulation en cours...' : '🚀 Lancer la simulation' }}
        </button>
      </div>

      <div v-if="simulationResult" :class="['sim-result', simulationResult.success ? 'sim-success' : 'sim-error']">
        <strong>{{ simulationResult.success ? '✅ Succès :' : '❌ Erreur :' }}</strong> {{ simulationResult.message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  formData: { type: Object, required: true }
});

const isSimulating = ref(false);
const simulationResult = ref(null);

// Payload par défaut pour aider l'utilisateur
const simulationPayload = ref(`{
  "event": "inbound_parsing_event",
  "subject": "Réponse au sujet de la réservation [Resa #115]",
  "items": [
    {
      "SenderAddress": "client@gmail.com",
      "Subject": "Re: Votre séjour [Resa #115]",
      "RawHtmlBody": "Bonjour, merci pour ces infos ! J'arrive à 14h."
    }
  ]
}`);

const runSimulation = async () => {
  if (!simulationPayload.value) {
    alert("Le champ JSON est vide !");
    return;
  }

  isSimulating.value = true;
  simulationResult.value = null;

  try {
    const formData = new FormData();
    formData.action = 'pc_simulate_webhook';
    formData.append('action', 'pc_simulate_webhook');
    // On utilise notre nonce global pour simplifier !
    formData.append('security', window.pcReservationVars.nonce); 
    formData.append('payload', simulationPayload.value);

    const response = await fetch(window.pcReservationVars.ajax_url, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();
    simulationResult.value = {
      success: result.success,
      message: result.data?.message || (result.success ? "Opération réussie" : "Erreur inconnue")
    };
  } catch (err) {
    simulationResult.value = { success: false, message: "Erreur serveur critique (500)." };
  } finally {
    isSimulating.value = false;
  }
};
</script>

<style scoped>
.pc-form-row { display: flex; gap: 20px; margin-bottom: 20px; }
.pc-col-6 { flex: 1; }
.pc-form-group { display: flex; flex-direction: column; }
.pc-form-group label { font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.95rem; }
.pc-input { width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; background: white; }
.pc-input:focus { outline: none; border-color: #10b981; }
.pc-help-text { margin: 5px 0 0 0; font-size: 0.85rem; color: #64748b; }
.mb-15 { margin-bottom: 15px; }
.separator { border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0; }

.simulator-block { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; }
.block-title { margin: 0 0 5px 0; font-size: 1.1rem; color: #334155; }
.pc-code-editor { height: 200px; font-family: monospace; background: #1e293b; color: #e2e8f0; padding: 15px; }
.simulator-actions { margin-top: 15px; }

.pc-btn { padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; }
.pc-btn-secondary { background: #6366f1; color: white; }
.pc-btn-secondary:hover:not(:disabled) { background: #4f46e5; }
.pc-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.sim-result { margin-top: 15px; padding: 12px; border-radius: 6px; font-size: 0.95rem; }
.sim-success { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
.sim-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
</style>