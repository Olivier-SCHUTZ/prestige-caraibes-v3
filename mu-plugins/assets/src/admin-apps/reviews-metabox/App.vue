<template>
  <div class="pc-review-wrap">
    <input type="hidden" name="pc_review_payload" :value="payloadString" />

    <div class="pc-section">
      <h3>🏠 Fiche hébergement ou expérience liée</h3>
      <div class="pc-field-group">
        <label>Sélectionnez la villa, l'appartement ou l'expérience *</label>
        <select v-model="formData.pc_post_id" required>
          <option value="" disabled>-- Choisir un hébergement ou une expérience --</option>
          <option v-for="prop in availableProperties" :key="prop.id" :value="prop.id">
            {{ prop.title }}
          </option>
        </select>
      </div>
    </div>

    <div class="pc-section">
      <h3>👤 Auteur de l'avis</h3>
      <div class="pc-row">
        <div class="pc-field-group half">
          <label>Nom du client *</label>
          <input type="text" v-model="formData.pc_reviewer_name" required />
        </div>
        <div class="pc-field-group half">
          <label>Pays / localisation</label>
          <input type="text" v-model="formData.pc_reviewer_location" placeholder="FR, BE, CA…" />
        </div>
      </div>
      <div class="pc-field-group">
        <label>Email (non publié)</label>
        <input type="email" v-model="formData.pc_email" />
      </div>
    </div>

    <div class="pc-section">
      <h3>📅 Détails du séjour</h3>
      <div class="pc-row">
        <div class="pc-field-group half">
          <label>Note (sur 5) *</label>
          <input type="number" v-model="formData.pc_rating" min="1" max="5" required />
        </div>
        <div class="pc-field-group half">
          <label>Mois de séjour</label>
          <input type="month" v-model="formData.pc_stayed_date" />
          <p class="description">Format YYYY-MM</p>
        </div>
      </div>
    </div>

    <div class="pc-section">
      <h3>✍️ Contenu de l’avis</h3>
      <div class="pc-field-group">
        <label>Titre (optionnel)</label>
        <input type="text" v-model="formData.pc_title" />
      </div>
      <div class="pc-field-group">
        <label>Avis *</label>
        <textarea v-model="formData.pc_body" rows="5" required></textarea>
      </div>
    </div>

    <div class="pc-section">
      <h3>🌍 Source</h3>
      <div class="pc-row">
        <div class="pc-field-group half">
          <label>Plateforme</label>
          <select v-model="formData.pc_source">
            <option value="internal">Interne (soumis sur le site)</option>
            <option value="airbnb">Airbnb</option>
            <option value="booking">Booking</option>
            <option value="google">Google</option>
          </select>
          <p class="description">Seuls les avis "Interne" comptent pour le schema.</p>
        </div>
        <div class="pc-field-group half">
          <label>URL source (optionnel)</label>
          <input type="url" v-model="formData.pc_source_url" placeholder="Lien vers l'avis d'origine..." />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

// Données transmises par PHP
const availableProperties = window.PC_AVAILABLE_PROPERTIES || [];

const formData = ref({
  pc_post_id: '',
  pc_reviewer_name: '',
  pc_reviewer_location: '',
  pc_email: '',
  pc_rating: 5,
  pc_stayed_date: '',
  pc_title: '',
  pc_body: '',
  pc_source: 'internal',
  pc_source_url: ''
});

onMounted(() => {
  if (window.PC_REVIEW_INITIAL_STATE) {
    const initData = window.PC_REVIEW_INITIAL_STATE;
    formData.value.pc_post_id = initData.pc_post_id || '';
    formData.value.pc_reviewer_name = initData.pc_reviewer_name || '';
    formData.value.pc_reviewer_location = initData.pc_reviewer_location || '';
    formData.value.pc_email = initData.pc_email || '';
    formData.value.pc_rating = initData.pc_rating || 5;
    formData.value.pc_stayed_date = initData.pc_stayed_date || '';
    formData.value.pc_title = initData.pc_title || '';
    formData.value.pc_body = initData.pc_body || '';
    formData.value.pc_source = initData.pc_source || 'internal';
    formData.value.pc_source_url = initData.pc_source_url || '';
  }
});

const payloadString = computed(() => JSON.stringify(formData.value));
</script>

<style scoped>
.pc-review-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.pc-section {
  background: #f9f9f9;
  padding: 15px;
  border: 1px solid #e2e4e7;
  border-radius: 4px;
}
.pc-section h3 {
  margin-top: 0;
  margin-bottom: 15px;
  padding-bottom: 8px;
  border-bottom: 1px solid #ccd0d4;
  font-size: 14px;
}
.pc-row {
  display: flex;
  gap: 20px;
}
.pc-field-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 15px;
  width: 100%;
}
.pc-field-group.half {
  width: 50%;
}
.pc-field-group label {
  font-weight: 600;
  margin-bottom: 5px;
}
.pc-field-group input,
.pc-field-group textarea,
.pc-field-group select {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid #8c8f94;
  border-radius: 4px;
}
.pc-field-group input:focus,
.pc-field-group textarea:focus,
.pc-field-group select:focus {
  border-color: #2271b1;
  box-shadow: 0 0 0 1px #2271b1;
  outline: none;
}
.pc-field-group .description {
  margin: 4px 0 0;
  color: #646970;
  font-size: 12px;
}
</style>