<template>
  <div class="pc-tab-messaging">
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-4">
        <label>Préfixe téléphone par défaut</label>
        <input type="text" v-model="formData.pc_default_phone_prefix" class="pc-input" placeholder="+590" />
        <p class="pc-help-text">Préfixe utilisé pour les numéros WhatsApp (ex: +590 pour la Guadeloupe)</p>
      </div>
    </div>

    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>Template WhatsApp par défaut</label>
        <textarea v-model="formData.pc_whatsapp_template" class="pc-input pc-textarea" rows="4" placeholder="Bonjour {prenom_client}..."></textarea>
        <p class="pc-help-text">Template de secours. Variables disponibles: {prenom_client}, {nom_client}, {numero_resa}</p>
      </div>
    </div>

    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>Signature email par défaut (HTML autorisé)</label>
        <textarea v-model="formData.pc_email_signature" class="pc-input pc-textarea" rows="4"></textarea>
        <p class="pc-help-text">Signature ajoutée automatiquement aux emails envoyés depuis le système</p>
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
.pc-form-row { display: flex; gap: 20px; margin-bottom: 25px; }
.pc-col-12 { flex: 1; }
.pc-col-4 { flex: 0 0 300px; }
.pc-form-group { display: flex; flex-direction: column; }
.pc-form-group label { font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.95rem; }
.pc-input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; background: white; font-family: inherit; }
.pc-input:focus { outline: none; border-color: #10b981; }
.pc-textarea { height: auto; resize: vertical; min-height: 100px; }
.pc-help-text { margin: 5px 0 0 0; font-size: 0.85rem; color: #64748b; }
</style>