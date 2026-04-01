<template>
  <div class="pc-tab-identity">
    <h4 class="block-title">🎨 Identité Générale</h4>
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>ID du Média (Logo Principal du site)</label>
        <input type="number" v-model="formData.pc_general_logo" class="pc-input" placeholder="Ex : 125" />
        <p class="pc-help-text">Saisissez l'ID du média WordPress (et non l'URL). Ce logo sera utilisé par défaut dans les emails et l'interface.</p>
      </div>
    </div>

    <hr class="separator" />

    <h4 class="block-title">📱 Web App / Dashboard Propriétaire</h4>
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-6">
        <label>URL de l'Espace Propriétaire (Slug)</label>
        <div class="input-with-prefix">
          <span class="prefix">/</span>
          <input type="text" v-model="formData.pc_dashboard_slug" class="pc-input" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" placeholder="espace-proprietaire" />
        </div>
        <p class="pc-help-text">Attention : Sauvegardez les permaliens WP après modification.</p>
      </div>

      <div class="pc-form-group pc-col-6">
        <label>Menu principal</label>
        <div class="toggle-wrapper" style="margin-top: 10px;">
          <input type="checkbox" id="menu_item" v-model="formData.pc_dashboard_menu_item" />
          <label for="menu_item" style="font-weight:normal; display:inline-block; margin-left:8px;">Ajouter un lien "Espace Propriétaire" au menu WP</label>
        </div>
      </div>
    </div>

    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>ID du Média (Logo du Dashboard - Fond Blanc)</label>
        <input type="number" v-model="formData.pc_dashboard_logo" class="pc-input" placeholder="Ex : 126" />
        <p class="pc-help-text">Saisissez l'ID du média. Si vide, le logo principal sera utilisé.</p>
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
.pc-form-row { display: flex; gap: 20px; margin-bottom: 20px; }
.pc-col-12 { flex: 1; }
.pc-col-6 { flex: 1; }
.pc-form-group { display: flex; flex-direction: column; }
.pc-form-group label { font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.95rem; }
.pc-input { width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; background: white; }
.pc-input:focus { outline: none; border-color: #10b981; }
.pc-help-text { margin: 5px 0 0 0; font-size: 0.85rem; color: #64748b; }
.block-title { margin: 0 0 15px 0; font-size: 1.1rem; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; display: inline-block; }
.separator { border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0; }
.input-with-prefix { display: flex; align-items: center; }
.prefix { background: #f1f5f9; border: 1px solid #cbd5e1; border-right: none; padding: 0 12px; height: 40px; display: flex; align-items: center; border-radius: 6px 0 0 6px; color: #64748b; font-weight: bold; }
</style>