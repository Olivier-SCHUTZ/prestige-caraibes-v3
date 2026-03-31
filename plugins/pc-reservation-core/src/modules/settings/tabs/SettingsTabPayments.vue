<template>
  <div class="pc-tab-payments">
    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>Mode de l'API Stripe</label>
        <select v-model="formData.pc_stripe_mode" class="pc-input" style="max-width: 300px;">
          <option value="test">Test (Sandbox)</option>
          <option value="live">Live (Production)</option>
        </select>
      </div>
    </div>

    <div v-if="formData.pc_stripe_mode === 'test'" class="environment-block test-mode">
      <h4 class="block-title">🚧 Environnement de Test</h4>
      <div class="pc-form-row">
        <div class="pc-form-group pc-col-6">
          <label>Clé Publique (Test)</label>
          <input type="text" v-model="formData.pc_stripe_test_pk" class="pc-input" placeholder="pk_test_..." />
        </div>
        <div class="pc-form-group pc-col-6">
          <label>Clé Secrète (Test)</label>
          <input type="password" v-model="formData.pc_stripe_test_sk" class="pc-input" placeholder="sk_test_..." />
        </div>
      </div>
    </div>

    <div v-if="formData.pc_stripe_mode === 'live'" class="environment-block live-mode">
      <h4 class="block-title">🚀 Environnement Live (Production)</h4>
      <div class="pc-form-row">
        <div class="pc-form-group pc-col-6">
          <label>Clé Publique (Live)</label>
          <input type="text" v-model="formData.pc_stripe_live_pk" class="pc-input" placeholder="pk_live_..." />
        </div>
        <div class="pc-form-group pc-col-6">
          <label>Clé Secrète (Live)</label>
          <input type="password" v-model="formData.pc_stripe_live_sk" class="pc-input" placeholder="sk_live_..." />
        </div>
      </div>
    </div>

    <hr class="separator" />

    <div class="pc-form-row">
      <div class="pc-form-group pc-col-12">
        <label>Secret Webhook (Signature Stripe)</label>
        <input type="text" v-model="formData.pc_stripe_webhook_secret" class="pc-input" placeholder="whsec_..." />
        <p class="pc-help-text">Nécessaire pour valider les paiements automatiquement via les événements Stripe.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  formData: {
    type: Object,
    required: true
  }
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

.environment-block { padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid; }
.test-mode { background-color: #f8fafc; border-color: #cbd5e1; }
.live-mode { background-color: #fdf2f8; border-color: #fbcfe8; }
.block-title { margin: 0 0 15px 0; font-size: 1.1rem; color: #1e293b; }
.separator { border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0; }
</style>