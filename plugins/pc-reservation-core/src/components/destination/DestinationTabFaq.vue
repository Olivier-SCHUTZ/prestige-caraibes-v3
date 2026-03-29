<template>
  <div class="pc-tab-faq">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <p style="color: #64748b; margin: 0;">
            Gérez ici les questions fréquemment posées pour cette destination.
        </p>
        <button class="pc-btn pc-btn-primary pc-btn-sm" @click.prevent="addFaq">
            <span>➕</span> Ajouter une question
        </button>
    </div>

    <div v-if="!formData.dest_faq || formData.dest_faq.length === 0" style="text-align: center; padding: 40px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; color: #94a3b8;">
        Aucune question/réponse pour le moment. Cliquez sur "Ajouter une question" pour commencer.
    </div>

    <div class="pc-form-grid" style="display: grid; gap: 20px">
        <div 
            v-for="(item, index) in formData.dest_faq" 
            :key="index"
            style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; position: relative;"
        >
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                <h4 style="margin: 0; color: #1e293b; font-size: 1rem;">
                    #{{ index + 1 }} - {{ item.question || 'Nouvelle question' }}
                </h4>
                <div style="display: flex; gap: 5px;">
                    <button class="pc-btn-icon" @click.prevent="moveUp(index)" :disabled="index === 0" title="Monter" style="cursor: pointer; background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px;">
                        ↑
                    </button>
                    <button class="pc-btn-icon" @click.prevent="moveDown(index)" :disabled="index === formData.dest_faq.length - 1" title="Descendre" style="cursor: pointer; background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px;">
                        ↓
                    </button>
                    <button class="pc-btn-icon" @click.prevent="removeFaq(index)" title="Supprimer" style="cursor: pointer; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; border-radius: 4px; padding: 4px 8px; margin-left: 10px;">
                        ✕
                    </button>
                </div>
            </div>

            <div class="pc-form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px">Question</label>
                <input type="text" v-model="item.question" class="pc-input" placeholder="Ex: Quelle est la meilleure période pour y aller ?" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
            </div>

            <div class="pc-form-group">
                <label style="display: block; font-weight: 600; margin-bottom: 5px">Réponse (Texte ou HTML)</label>
                <textarea v-model="item.reponse" rows="4" placeholder="Saisissez la réponse détaillée ici..." style="width: 100%; box-sizing: border-box; resize: vertical; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit;"></textarea>
            </div>
        </div>
    </div>

  </div>
</template>

<script setup>
const props = defineProps({
  formData: {
    type: Object,
    required: true
  }
});

// Sécurité : forcer en tableau
if (!Array.isArray(props.formData.dest_faq)) {
    props.formData.dest_faq = [];
}

// Ajouter une ligne FAQ
const addFaq = () => {
    props.formData.dest_faq.push({
        question: '',
        reponse: ''
    });
};

// Supprimer une ligne FAQ
const removeFaq = (index) => {
    if (confirm("Êtes-vous sûr de vouloir supprimer cette question ?")) {
        props.formData.dest_faq.splice(index, 1);
    }
};

// Monter la question
const moveUp = (index) => {
    if (index > 0) {
        const item = props.formData.dest_faq.splice(index, 1)[0];
        props.formData.dest_faq.splice(index - 1, 0, item);
    }
};

// Descendre la question
const moveDown = (index) => {
    if (index < props.formData.dest_faq.length - 1) {
        const item = props.formData.dest_faq.splice(index, 1)[0];
        props.formData.dest_faq.splice(index + 1, 0, item);
    }
};
</script>