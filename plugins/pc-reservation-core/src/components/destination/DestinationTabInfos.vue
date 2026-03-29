<template>
  <div class="pc-tab-infos">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <p style="color: #64748b; margin: 0;">
            Gérez ici les blocs d'informations pratiques de la destination (Transport, Hébergement, Météo...).
        </p>
        <button class="pc-btn pc-btn-primary pc-btn-sm" @click.prevent="addInfo">
            <span>➕</span> Ajouter un bloc
        </button>
    </div>

    <div v-if="!formData.dest_infos || formData.dest_infos.length === 0" style="text-align: center; padding: 40px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; color: #94a3b8;">
        Aucune information pratique pour le moment. Cliquez sur "Ajouter un bloc" pour commencer.
    </div>

    <div class="pc-form-grid" style="display: grid; gap: 20px">
        <div 
            v-for="(info, index) in formData.dest_infos" 
            :key="index"
            style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; position: relative;"
        >
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                <h4 style="margin: 0; color: #1e293b; font-size: 1rem;">
                    #{{ index + 1 }} - {{ info.titre || 'Nouveau bloc' }}
                </h4>
                <div style="display: flex; gap: 5px;">
                    <button class="pc-btn-icon" @click.prevent="moveUp(index)" :disabled="index === 0" title="Monter" style="cursor: pointer; background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px;">
                        ↑
                    </button>
                    <button class="pc-btn-icon" @click.prevent="moveDown(index)" :disabled="index === formData.dest_infos.length - 1" title="Descendre" style="cursor: pointer; background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px;">
                        ↓
                    </button>
                    <button class="pc-btn-icon" @click.prevent="removeInfo(index)" title="Supprimer" style="cursor: pointer; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; border-radius: 4px; padding: 4px 8px; margin-left: 10px;">
                        ✕
                    </button>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 15px;">
                <div class="pc-form-group">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px">Titre du bloc</label>
                    <input type="text" v-model="info.titre" class="pc-input" placeholder="Ex: Transport local" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
                </div>
                
                <div class="pc-form-group">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px">Icône (FontAwesome)</label>
                    <input type="text" v-model="info.icone" class="pc-input" placeholder="Ex: fa-solid fa-bus" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
                </div>
            </div>

            <div class="pc-form-group">
                <label style="display: block; font-weight: 600; margin-bottom: 5px">Contenu (Texte ou HTML)</label>
                <textarea v-model="info.contenu" rows="4" placeholder="Saisissez les informations ici..." style="width: 100%; box-sizing: border-box; resize: vertical; padding: 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-family: inherit;"></textarea>
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

// Assurons-nous que le tableau existe bien (sécurité)
if (!Array.isArray(props.formData.dest_infos)) {
    props.formData.dest_infos = [];
}

// Ajouter un nouveau bloc vide
const addInfo = () => {
    props.formData.dest_infos.push({
        titre: '',
        icone: '',
        contenu: ''
    });
};

// Supprimer un bloc
const removeInfo = (index) => {
    if (confirm("Êtes-vous sûr de vouloir supprimer ce bloc d'information ?")) {
        props.formData.dest_infos.splice(index, 1);
    }
};

// Monter le bloc d'un cran
const moveUp = (index) => {
    if (index > 0) {
        const item = props.formData.dest_infos.splice(index, 1)[0];
        props.formData.dest_infos.splice(index - 1, 0, item);
    }
};

// Descendre le bloc d'un cran
const moveDown = (index) => {
    if (index < props.formData.dest_infos.length - 1) {
        const item = props.formData.dest_infos.splice(index, 1)[0];
        props.formData.dest_infos.splice(index + 1, 0, item);
    }
};
</script>