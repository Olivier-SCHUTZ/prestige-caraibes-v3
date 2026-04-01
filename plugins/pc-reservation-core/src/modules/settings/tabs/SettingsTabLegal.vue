<template>
  <div class="pc-tab-legal">
    
    <h4 class="block-title">📄 Design des Documents PDF</h4>
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-8">
        <label>ID du Média (Logo pour PDF)</label>
        <input type="number" v-model="formData.pc_pdf_logo" class="pc-input" placeholder="Ex : 142" />
        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #64748b;">Saisissez l'ID de l'image (et non l'URL).</p>
      </div>
      <div class="pc-form-group pc-col-4">
        <label>Couleur Principale</label>
        <input type="color" v-model="formData.pc_pdf_primary_color" class="pc-input color-picker" />
      </div>
    </div>

    <hr class="separator" />

    <h4 class="block-title">🏢 Mentions Légales & Entreprise</h4>
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-6">
        <label>Raison Sociale / Nom</label>
        <input type="text" v-model="formData.pc_legal_name" class="pc-input" placeholder="SARL Prestige Caraïbes" />
      </div>
      <div class="pc-form-group pc-col-6">
        <label>Capital Social</label>
        <input type="text" v-model="formData.pc_legal_capital" class="pc-input" placeholder="10 000 €" />
      </div>
    </div>
    
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>Siège Social (Adresse complète)</label>
        <textarea v-model="formData.pc_legal_address" class="pc-input pc-textarea" rows="2"></textarea>
      </div>
    </div>

    <div class="pc-form-row">
      <div class="pc-form-group pc-col-3">
        <label>SIRET</label>
        <input type="text" v-model="formData.pc_legal_siret" class="pc-input" />
      </div>
      <div class="pc-form-group pc-col-3">
        <label>TVA Intracom.</label>
        <input type="text" v-model="formData.pc_legal_tva" class="pc-input" />
      </div>
      <div class="pc-form-group pc-col-3">
        <label>Email de facturation</label>
        <input type="email" v-model="formData.pc_legal_email" class="pc-input" />
      </div>
      <div class="pc-form-group pc-col-3">
        <label>Téléphone</label>
        <input type="text" v-model="formData.pc_legal_phone" class="pc-input" />
      </div>
    </div>

    <hr class="separator" />

    <h4 class="block-title">🏦 Coordonnées Bancaires (RIB)</h4>
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-4">
        <label>Nom de la Banque</label>
        <input type="text" v-model="formData.pc_bank_name" class="pc-input" />
      </div>
      <div class="pc-form-group pc-col-4">
        <label>IBAN</label>
        <input type="text" v-model="formData.pc_bank_iban" class="pc-input" />
      </div>
      <div class="pc-form-group pc-col-4">
        <label>BIC / SWIFT</label>
        <input type="text" v-model="formData.pc_bank_bic" class="pc-input" />
      </div>
    </div>

    <hr class="separator" />

    <h4 class="block-title">⚖️ Conditions Générales de Vente (HTML autorisé)</h4>
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-6">
        <label>CGV - Location / Logement</label>
        <textarea v-model="formData.cgv_location" class="pc-input pc-textarea" rows="4"></textarea>
      </div>
      <div class="pc-form-group pc-col-6">
        <label>CGV - Expériences / Activités</label>
        <textarea v-model="formData.cgv_experience" class="pc-input pc-textarea" rows="4"></textarea>
      </div>
    </div>

    <div class="pc-form-row">
      <div class="pc-form-group pc-col-6">
        <label>CGV - Séjour</label>
        <textarea v-model="formData.cgv_sejour" class="pc-input pc-textarea" rows="4"></textarea>
      </div>
      <div class="pc-form-group pc-col-6">
        <label>CGV - Custom</label>
        <textarea v-model="formData.pc_linked_cgv" class="pc-input pc-textarea" rows="4"></textarea>
      </div>
    </div>

  </div>
</template>

<script setup>
defineProps({
  formData: { type: Object, required: true }
});
</script>

<style scoped>
.pc-form-row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
.pc-col-12 { flex: 0 0 100%; }
.pc-col-8 { flex: 2; }
.pc-col-6 { flex: 1; min-width: 300px; }
.pc-col-4 { flex: 1; }
.pc-col-3 { flex: 1; min-width: 200px; }
.pc-form-group { display: flex; flex-direction: column; }
.pc-form-group label { font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.9rem; }
.pc-input { width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; background: white; }
.pc-input:focus { outline: none; border-color: #10b981; }
.pc-textarea { height: auto; resize: vertical; min-height: 80px; padding: 10px 12px; }
.color-picker { padding: 2px 4px; cursor: pointer; }
.block-title { margin: 0 0 15px 0; font-size: 1.1rem; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; display: inline-block; }
.separator { border: 0; border-top: 1px dashed #e2e8f0; margin: 30px 0; }
</style>