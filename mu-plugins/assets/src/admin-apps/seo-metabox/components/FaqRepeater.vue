<template>
  <div class="pc-faq-repeater">
    <div class="pc-faq-header">
      <h4>Questions Fréquentes (FAQ)</h4>
      <p class="description">Ces Q/R généreront automatiquement un schéma SEO FAQPage.</p>
    </div>

    <div 
      v-for="(item, index) in localItems" 
      :key="index" 
      class="pc-faq-row"
    >
      <div class="pc-faq-row-header">
        <span class="pc-faq-number">Question {{ index + 1 }}</span>
        <div class="pc-faq-actions">
          <button type="button" class="button-link pc-move" @click="moveItem(index, -1)" :disabled="index === 0" title="Monter">↑</button>
          <button type="button" class="button-link pc-move" @click="moveItem(index, 1)" :disabled="index === localItems.length - 1" title="Descendre">↓</button>
          <button type="button" class="button-link pc-delete" @click="removeItem(index)">Supprimer</button>
        </div>
      </div>

      <div class="pc-faq-row-body">
        <div class="pc-field-group">
          <label>Question</label>
          <input 
            type="text" 
            v-model="item.question" 
            class="large-text" 
            placeholder="Ex: Comment se passe la remise des clés ?"
            @input="updateParent"
          />
        </div>
        
        <div class="pc-field-group">
          <label>Réponse</label>
          <textarea 
            v-model="item.answer" 
            class="large-text" 
            rows="4" 
            placeholder="Ex: Notre concierge vous accueillera directement sur place..."
            @input="updateParent"
          ></textarea>
        </div>
      </div>
    </div>

    <button type="button" class="button button-primary pc-add-btn" @click="addItem">
      + Ajouter une Q/R
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue'])

// On crée une copie locale pour travailler facilement
const localItems = ref([...props.modelValue])

// Surveiller les changements externes (au chargement initial)
watch(() => props.modelValue, (newVal) => {
  localItems.value = [...newVal]
}, { deep: true })

// Mettre à jour le parent (App.vue)
const updateParent = () => {
  emit('update:modelValue', localItems.value)
}

// Ajouter une ligne vide
const addItem = () => {
  localItems.value.push({ question: '', answer: '' })
  updateParent()
}

// Supprimer une ligne
const removeItem = (index) => {
  if (confirm('Êtes-vous sûr de vouloir supprimer cette question ?')) {
    localItems.value.splice(index, 1)
    updateParent()
  }
}

// Déplacer une ligne (réorganiser)
const moveItem = (index, direction) => {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= localItems.value.length) return
  
  const item = localItems.value.splice(index, 1)[0]
  localItems.value.splice(newIndex, 0, item)
  updateParent()
}
</script>

<style scoped>
.pc-faq-repeater {
  background: #fff;
  border: 1px solid #ccd0d4;
  padding: 15px;
  border-radius: 4px;
  margin-top: 20px;
}

.pc-faq-header {
  margin-bottom: 15px;
}
.pc-faq-header h4 {
  margin: 0 0 5px 0;
  font-size: 14px;
}

.pc-faq-row {
  background: #f9f9f9;
  border: 1px solid #e2e4e7;
  margin-bottom: 15px;
}

.pc-faq-row-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f0f0f1;
  border-bottom: 1px solid #e2e4e7;
  font-weight: 600;
}

.pc-faq-actions {
  display: flex;
  gap: 10px;
}

.button-link.pc-delete {
  color: #d63638;
}
.button-link.pc-delete:hover {
  color: #d63638;
  text-decoration: underline;
}

.button-link.pc-move {
  font-weight: bold;
  text-decoration: none;
}
.button-link.pc-move:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.pc-faq-row-body {
  padding: 15px;
}

.pc-field-group {
  margin-bottom: 15px;
}
.pc-field-group:last-child {
  margin-bottom: 0;
}
.pc-field-group label {
  display: block;
  font-weight: 600;
  margin-bottom: 5px;
}

.pc-add-btn {
  margin-top: 10px;
}
</style>