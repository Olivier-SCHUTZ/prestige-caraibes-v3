<template>
  <div class="pc-image-uploader">
    <div v-if="modelValue" class="pc-image-preview">
      <img :src="modelValue" alt="Prévisualisation" />
      <button type="button" class="button button-link-delete pc-remove-img" @click="removeImage">
        Retirer l'image
      </button>
    </div>

    <div v-else class="pc-image-placeholder">
      <button type="button" class="button" @click="openMediaLibrary">
        Sélectionner une image
      </button>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: [String, Number], // 🚀 CORRECTION : Accepte les URLs (String) et les vieux IDs (Number)
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

let mediaFrame = null

const openMediaLibrary = () => {
  // Si la fenêtre WP Media existe déjà, on la réouvre simplement
  if (mediaFrame) {
    mediaFrame.open()
    return
  }

  // Sinon, on initialise la fenêtre native de WordPress
  mediaFrame = wp.media({
    title: 'Sélectionner ou envoyer une image',
    button: {
      text: 'Utiliser cette image'
    },
    multiple: false // On ne veut qu'une seule image
  })

  // Quand l'utilisateur clique sur "Utiliser cette image"
  mediaFrame.on('select', () => {
    const attachment = mediaFrame.state().get('selection').first().toJSON()
    
    // Ton ancien champ ACF renvoyait une URL, on fait exactement pareil !
    emit('update:modelValue', attachment.url)
  })

  mediaFrame.open()
}

const removeImage = () => {
  emit('update:modelValue', '')
}
</script>

<style scoped>
.pc-image-uploader {
  max-width: 350px;
  background: #f9f9f9;
  border: 1px dashed #ccd0d4;
  padding: 10px;
  border-radius: 4px;
}

.pc-image-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.pc-image-preview img {
  max-width: 100%;
  height: auto;
  border: 1px solid #e2e4e7;
  border-radius: 3px;
}

.pc-image-placeholder {
  display: flex;
  justify-content: center;
  padding: 20px 0;
}

.pc-remove-img {
  color: #d63638;
}
</style>