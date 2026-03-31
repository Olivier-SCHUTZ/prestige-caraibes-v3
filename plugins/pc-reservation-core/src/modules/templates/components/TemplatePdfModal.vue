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
                <label>Nom du modèle PDF <span class="pc-text-danger">*</span></label>
                <input type="text" v-model="formData.title" class="pc-input" required placeholder="Ex: Contrat de Location Standard..." />
              </div>

              <div class="pc-form-row mb-20">
                <div class="pc-form-group pc-col-6">
                  <label>Type de Document</label>
                  <select v-model="formData.pc_doc_type" class="pc-input">
                    <option value="document">📝 Document générique</option>
                    <option value="devis">📄 Devis</option>
                    <option value="facture">🧾 Facture</option>
                    <option value="facture_acompte">💰 Facture d'acompte</option>
                    <option value="avoir">↩️ Avoir</option>
                    <option value="contrat">📋 Contrat</option>
                    <option value="voucher">🎫 Voucher / Bon d'échange</option>
                  </select>
                </div>
                <div class="pc-form-group pc-col-6">
                  <label>Contexte d'affichage</label>
                  <select v-model="formData.pc_model_context" class="pc-input">
                    <option value="global">🌍 Global (Afficher pour tout)</option>
                    <option value="location">🏠 Réservations de Logements uniquement</option>
                    <option value="experience">🎯 Réservations d'Expériences uniquement</option>
                  </select>
                </div>
              </div>

              <div class="info-block mb-20">
                💡 <strong>Rappel Design :</strong> Le logo, les couleurs, les mentions légales et le RIB sont gérés globalement dans <a href="#settings" @click="closeAndNavigate">Configuration > Documents & Légal</a>. Saisissez uniquement le corps de votre document ci-dessous (HTML autorisé).
              </div>

              <div class="pc-form-group">
                <label>Corps du document PDF (HTML autorisé)</label>
                <textarea 
                  ref="contentEditor"
                  v-model="formData.content" 
                  class="pc-input pc-textarea" 
                  rows="15" 
                  placeholder="Saisissez le contenu de votre PDF ici... (Vous pouvez utiliser les variables à droite)"
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
import { usePdfTemplateStore } from "@/stores/pdf-template-store";

const emit = defineEmits(["close", "saved"]);
const store = usePdfTemplateStore();

const isSaving = ref(false);
const saveError = ref("");
const contentEditor = ref(null);

// Les données par défaut
const formData = ref({
  id: 0,
  title: '',
  content: '',
  status: 'publish',
  pc_model_context: 'global',
  pc_doc_type: 'document'
});

// Titre dynamique
const modalTitle = computed(() => {
  if (formData.value.id) return `Édition PDF : ${formData.value.title}`;
  return "Nouveau Modèle PDF";
});

// Au montage, on hydrate avec les données du store si on édite
onMounted(() => {
  if (store.currentTemplate) {
    const rawData = JSON.parse(JSON.stringify(store.currentTemplate));
    formData.value = { ...formData.value, ...rawData };
  }
});

// Navigation vers les réglages (si on clique sur le lien d'info)
const closeAndNavigate = () => {
  emit('close');
  if (typeof window.switchTab === 'function') {
    window.switchTab('settings');
  }
};

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
  if (confirm("Êtes-vous sûr de vouloir supprimer définitivement ce modèle PDF ?")) {
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
.pc-form-group { display: flex; flex-direction: column; }
.pc-form-group label { font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.95rem; }
.pc-input { width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; background: white; }
.pc-input:focus { outline: none; border-color: #8b5cf6; }
.pc-textarea { height: auto; resize: vertical; min-height: 350px; padding: 12px; font-family: monospace; font-size: 0.9rem; line-height: 1.5; }
.pc-text-danger { color: #ef4444; }

/* Info Block */
.info-block { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; padding: 15px; border-radius: 8px; font-size: 0.9rem; }
.info-block a { color: #2563eb; font-weight: 600; }

/* Barre Latérale Variables */
.variables-box h4 { margin: 0 0 5px 0; color: #1e293b; }
.help-text { font-size: 0.85rem; color: #64748b; margin-top: 0; margin-bottom: 20px; font-style: italic; }
.var-group { margin-bottom: 20px; }
.var-group h5 { margin: 0 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #cbd5e1; color: #334155; }
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