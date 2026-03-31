<template>
  <Teleport to="body">
    <div class="v2-modal-wrapper">
      <div class="v2-modal-overlay" @click="$emit('close')"></div>

      <div class="v2-modal-container modal-large">
        <div class="v2-modal-header">
          <h2>{{ modalTitle }}</h2>
          <button class="v2-close-btn" @click="$emit('close')" title="Fermer">✕</button>
        </div>

        <div class="v2-modal-body">
          <div v-if="store.loading && !formData.id" class="loading-state">
            ⏳ Chargement...
          </div>

          <div v-else class="modal-layout">
            
            <div class="modal-main-content">
              
              <div class="pc-form-group mb-20">
                <label>Nom du modèle <span class="pc-text-danger">*</span></label>
                <input type="text" v-model="formData.title" class="pc-input" required placeholder="Saisissez le titre..." />
              </div>

              <div class="settings-block">
                <h4 class="block-title">⚙️ Configuration du Scénario</h4>
                
                <div class="pc-form-group mb-15">
                  <label>Catégorie de message</label>
                  <select v-model="formData.pc_message_category" class="pc-input">
                    <option value="email_system">📧 Email Système (Factures, Devis, Confirmations)</option>
                    <option value="quick_reply">💬 Réponse Rapide (Snippets pour WhatsApp)</option>
                  </select>
                </div>

                <template v-if="formData.pc_message_category === 'email_system'">
                  <div class="pc-form-group mb-15">
                    <label>Type de déclencheur</label>
                    <select v-model="formData.pc_msg_type" class="pc-input">
                      <option value="libre">Message personnalisé (libre)</option>
                      <option value="immediat">Message immédiat après action</option>
                      <option value="programme">Message programmé suite à réservation</option>
                    </select>
                  </div>

                  <div v-if="formData.pc_msg_type === 'immediat'" class="pc-form-group mb-15 sub-field">
                    <label>Déclencheur (Action sur le site)</label>
                    <select v-model="formData.pc_trigger_action" class="pc-input">
                      <option value="resa_directe">Nouvelle Réservation Directe (Confirmée/Payée)</option>
                      <option value="demande_devis">Nouvelle Demande de Réservation (En attente)</option>
                      <option value="paiement_recu">Paiement Reçu (Acompte ou Solde)</option>
                    </select>
                  </div>

                  <div v-if="formData.pc_msg_type === 'programme'" class="pc-form-row mb-15 sub-field">
                    <div class="pc-form-group pc-col-6">
                      <label>Moment de l'envoi</label>
                      <select v-model="formData.pc_trigger_relative" class="pc-input">
                        <option value="before_checkin">Avant l'arrivée du client</option>
                        <option value="after_checkin">Après l'arrivée du client</option>
                        <option value="before_checkout">Avant le départ du client</option>
                        <option value="after_checkout">Après le départ du client</option>
                      </select>
                    </div>
                    <div class="pc-form-group pc-col-6">
                      <label>Nombre de jours</label>
                      <div class="input-with-suffix">
                        <input type="number" v-model="formData.pc_trigger_days" class="pc-input" min="1" />
                        <span class="suffix">jours</span>
                      </div>
                    </div>
                  </div>

                  <div class="pc-form-group mb-15">
                    <label>Sujet de l'email <span class="pc-text-danger">*</span></label>
                    <input type="text" v-model="formData.pc_msg_subject" class="pc-input" placeholder="Sujet du message..." />
                  </div>

                  <div class="pc-form-group">
                    <label>Joindre un PDF natif</label>
                    <select v-model="formData.pc_msg_attachment" class="pc-input">
                      <option value="">-- Aucun document --</option>
                      <option value="native_devis">📄 Devis commercial</option>
                      <option value="native_facture">🧾 Facture principale</option>
                      <option value="native_facture_acompte">💰 Facture d'acompte</option>
                      <option value="native_contrat">📋 Contrat de location</option>
                      <option value="native_voucher">🎫 Voucher / Bon d'échange</option>
                    </select>
                  </div>
                </template>
              </div>

              <div class="pc-form-group mt-20">
                <label>Contenu du message</label>
                <textarea 
                  ref="contentEditor"
                  v-model="formData.content" 
                  class="pc-input pc-textarea" 
                  rows="12" 
                  placeholder="Saisissez votre message ici... (Vous pouvez utiliser les variables à droite)"
                ></textarea>
              </div>

            </div>

            <div class="modal-sidebar">
              <div class="variables-box">
                <h4>Variables Disponibles</h4>
                <p class="help-text">💡 Cliquez sur une variable pour l'insérer au niveau du curseur.</p>

                <div class="var-group">
                  <h5>👤 Données Client</h5>
                  <button type="button" class="var-btn" @click="insertVar('{prenom_client}')">{prenom_client}</button>
                  <button type="button" class="var-btn" @click="insertVar('{nom_client}')">{nom_client}</button>
                  <button type="button" class="var-btn" @click="insertVar('{email_client}')">{email_client}</button>
                  <button type="button" class="var-btn" @click="insertVar('{telephone}')">{telephone}</button>
                </div>

                <div class="var-group">
                  <h5>📅 Données Séjour</h5>
                  <button type="button" class="var-btn" @click="insertVar('{date_arrivee}')">{date_arrivee}</button>
                  <button type="button" class="var-btn" @click="insertVar('{date_depart}')">{date_depart}</button>
                  <button type="button" class="var-btn" @click="insertVar('{duree_sejour}')">{duree_sejour}</button>
                  <button type="button" class="var-btn" @click="insertVar('{logement}')">{logement}</button>
                  <button type="button" class="var-btn" @click="insertVar('{numero_resa}')">{numero_resa}</button>
                </div>

                <div class="var-group">
                  <h5>💶 Données Financières</h5>
                  <button type="button" class="var-btn" @click="insertVar('{montant_total}')">{montant_total}</button>
                  <button type="button" class="var-btn" @click="insertVar('{acompte_paye}')">{acompte_paye}</button>
                  <button type="button" class="var-btn" @click="insertVar('{solde_restant}')">{solde_restant}</button>
                  
                  <h6 class="mt-10">Liens Stripe :</h6>
                  <button type="button" class="var-btn" @click="insertVar('{lien_paiement_acompte}')">{lien_paiement_acompte}</button>
                  <button type="button" class="var-btn" @click="insertVar('{lien_paiement_solde}')">{lien_paiement_solde}</button>
                  <button type="button" class="var-btn" @click="insertVar('{lien_paiement_caution}')">{lien_paiement_caution}</button>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="v2-modal-footer">
          <div class="footer-left">
            <span class="pc-text-danger" v-if="saveError">{{ saveError }}</span>
            <button v-if="formData.id" class="pc-btn pc-btn-danger" @click="handleDelete" :disabled="isSaving">
              <span>🗑️</span> Supprimer
            </button>
          </div>
          <div class="footer-right">
            <button class="pc-btn pc-btn-secondary" @click="$emit('close')" :disabled="isSaving">Annuler</button>
            <button class="pc-btn pc-btn-primary" @click="handleSave" :disabled="isSaving || store.loading">
              {{ isSaving ? "⏳ Sauvegarde..." : "💾 Sauvegarder le modèle" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useTemplateStore } from "@/stores/template-store";

const emit = defineEmits(["close", "saved"]);
const store = useTemplateStore();

const isSaving = ref(false);
const saveError = ref("");
const contentEditor = ref(null);

// Les données par défaut
const formData = ref({
  id: 0,
  title: '',
  content: '',
  status: 'publish',
  pc_message_category: 'email_system',
  pc_msg_type: 'libre',
  pc_trigger_action: 'resa_directe',
  pc_trigger_relative: 'before_checkin',
  pc_trigger_days: 1,
  pc_msg_subject: '',
  pc_msg_attachment: ''
});

// Titre dynamique
const modalTitle = computed(() => {
  if (formData.value.id) return `Édition : ${formData.value.title}`;
  return "Nouveau Modèle de Message";
});

// Au montage, on hydrate avec les données du store si on édite
onMounted(() => {
  if (store.currentTemplate) {
    const rawData = JSON.parse(JSON.stringify(store.currentTemplate));
    formData.value = { ...formData.value, ...rawData };
  }
});

// Insérer une variable à la position du curseur
const insertVar = (variable) => {
  if (!contentEditor.value) return;
  
  const textarea = contentEditor.value;
  const startPos = textarea.selectionStart;
  const endPos = textarea.selectionEnd;
  
  const currentContent = formData.value.content || '';
  
  formData.value.content = 
    currentContent.substring(0, startPos) + 
    variable + 
    currentContent.substring(endPos);

  // Remettre le focus et replacer le curseur après la variable
  setTimeout(() => {
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = startPos + variable.length;
  }, 10);
};

// Sauvegarde
const handleSave = async () => {
  if (!formData.value.title) {
    saveError.value = "Le nom du modèle est obligatoire.";
    return;
  }
  if (formData.value.pc_message_category === 'email_system' && !formData.value.pc_msg_subject) {
    saveError.value = "Le sujet de l'email est obligatoire pour ce type de message.";
    return;
  }

  isSaving.value = true;
  saveError.value = "";

  const result = await store.saveTemplate(formData.value);
  isSaving.value = false;

  if (result.success) {
    emit("close");
  } else {
    saveError.value = result.message || "Erreur lors de la sauvegarde.";
  }
};

// Suppression
const handleDelete = async () => {
  if (confirm("Êtes-vous sûr de vouloir supprimer définitivement ce modèle ?")) {
    isSaving.value = true;
    const result = await store.deleteTemplate(formData.value.id);
    if (result.success) {
      emit("close");
    } else {
      saveError.value = result.message;
      isSaving.value = false;
    }
  }
};
</script>

<style scoped>
/* Isolation V2 Modale */
.v2-modal-wrapper { position: fixed; inset: 0; z-index: 150000; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; }
.v2-modal-overlay { position: absolute; inset: 0; background-color: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px); }
.v2-modal-container { position: relative; background: white; width: 95%; max-height: 90vh; border-radius: 12px; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; }
.modal-large { max-width: 1100px; }

.v2-modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.v2-modal-header h2 { margin: 0; font-size: 1.5rem; color: #0f172a; font-weight: 600; }
.v2-close-btn { background: transparent; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; transition: color 0.2s; }
.v2-close-btn:hover { color: #ef4444; }

.v2-modal-body { padding: 0; overflow-y: auto; flex: 1; }
.modal-layout { display: flex; min-height: 500px; }
.modal-main-content { flex: 1; padding: 2rem; border-right: 1px solid #e2e8f0; }
.modal-sidebar { width: 300px; padding: 2rem; background: #f8fafc; }

/* Formulaires */
.pc-form-row { display: flex; gap: 20px; }
.pc-col-6 { flex: 1; }
.mb-20 { margin-bottom: 20px; }
.mb-15 { margin-bottom: 15px; }
.mt-20 { margin-top: 20px; }
.pc-form-group { display: flex; flex-direction: column; }
.pc-form-group label { font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.95rem; }
.pc-input { width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; background: white; }
.pc-input:focus { outline: none; border-color: #8b5cf6; }
.pc-textarea { height: auto; resize: vertical; min-height: 200px; padding: 12px; font-family: inherit; }
.pc-text-danger { color: #ef4444; }

/* Scénario Block */
.settings-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
.block-title { margin: 0 0 15px 0; color: #1e293b; font-size: 1.1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
.sub-field { margin-left: 15px; padding-left: 15px; border-left: 2px solid #cbd5e1; }

.input-with-suffix { display: flex; align-items: center; }
.suffix { background: #f1f5f9; border: 1px solid #cbd5e1; border-left: none; padding: 0 12px; height: 40px; display: flex; align-items: center; border-radius: 0 6px 6px 0; color: #64748b; font-weight: 500; }
.input-with-suffix .pc-input { border-radius: 6px 0 0 6px; }

/* Barre Latérale Variables */
.variables-box h4 { margin: 0 0 5px 0; color: #1e293b; }
.help-text { font-size: 0.85rem; color: #64748b; margin-top: 0; margin-bottom: 20px; font-style: italic; }
.var-group { margin-bottom: 20px; }
.var-group h5 { margin: 0 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #cbd5e1; color: #334155; }
.var-group h6 { margin: 0 0 5px 0; color: #475569; font-size: 0.85rem; }
.mt-10 { margin-top: 10px; }
.var-btn { display: inline-block; background: #e2e8f0; border: 1px solid #cbd5e1; color: #0f172a; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 0.85rem; margin: 0 5px 5px 0; cursor: pointer; transition: all 0.2s; }
.var-btn:hover { background: #8b5cf6; color: white; border-color: #7c3aed; }

/* Footer */
.v2-modal-footer { padding: 1.5rem 2rem; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; }
.footer-right { display: flex; gap: 1rem; }
.loading-state { text-align: center; padding: 4rem; color: #64748b; font-size: 1.1rem; }

.pc-btn { padding: 0.6rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.pc-btn-primary { background: #8b5cf6; color: white; }
.pc-btn-primary:hover:not(:disabled) { background: #7c3aed; }
.pc-btn-secondary { background: white; border: 1px solid #cbd5e0; color: #475569; }
.pc-btn-secondary:hover:not(:disabled) { background: #f1f5f9; }
.pc-btn-danger { background: #ef4444; color: white; }
.pc-btn-danger:hover:not(:disabled) { background: #dc2626; }
.pc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>