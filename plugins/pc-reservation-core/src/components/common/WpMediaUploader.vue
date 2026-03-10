<template>
  <div class="wp-media-uploader">
    <label v-if="label" class="uploader-label">{{ label }}</label>

    <div class="uploader-container">
      <div v-if="previewUrl" class="image-preview">
        <img :src="previewUrl" alt="Aperçu" />
        <button
          type="button"
          @click.stop.prevent="removeImage"
          class="remove-btn"
          title="Retirer l'image"
        >
          ✕
        </button>
      </div>

      <div v-else class="image-placeholder" @click.stop.prevent="openUploader">
        <span class="icon">📷</span>
        <span class="text">{{ placeholder || "Sélectionner une image" }}</span>
      </div>

      <button
        v-if="previewUrl"
        type="button"
        @click.stop.prevent="openUploader"
        class="pc-btn pc-btn-sm pc-btn-secondary mt-2"
      >
        Changer l'image
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";

const props = defineProps({
  modelValue: {
    type: [String, Number, Object],
    default: "",
  },
  label: String,
  placeholder: String,
});

const emit = defineEmits(["update:modelValue"]);

const previewUrl = ref("");
let mediaFrame = null;

// Initialisation de l'aperçu au chargement
const initPreview = () => {
  const val = props.modelValue;
  if (!val) {
    previewUrl.value = "";
    return;
  }

  // 1. Si c'est déjà un objet avec une URL (Format envoyé par l'API backend)
  if (typeof val === "object" && val.url) {
    previewUrl.value = val.url;
  }
  // 2. Si c'est une URL directe
  else if (typeof val === "string" && val.startsWith("http")) {
    previewUrl.value = val;
  }
  // 3. Si c'est un ID (Nombre), on demande à WordPress de récupérer l'URL
  else if (!isNaN(val) && window.wp && window.wp.media) {
    const attachment = window.wp.media.attachment(val);
    attachment.fetch().then(() => {
      previewUrl.value = attachment.get("url");
    });
  }
};

// Écouter les changements externes (ex: quand on ouvre la modale)
watch(
  () => props.modelValue,
  () => {
    initPreview();
  },
);

onMounted(() => {
  initPreview();
});

// Pont vers la modale Media de WordPress
const openUploader = () => {
  if (typeof window.wp === "undefined" || !window.wp.media) {
    alert("Erreur: La bibliothèque de médias WordPress n'est pas disponible.");
    return;
  }

  if (!mediaFrame) {
    mediaFrame = window.wp.media({
      title: "Sélectionner une image",
      button: { text: "Utiliser cette image" },
      multiple: false,
      library: { type: "image" },
    });

    mediaFrame.on("select", () => {
      const attachment = mediaFrame.state().get("selection").first().toJSON();

      // Mettre à jour l'aperçu localement
      previewUrl.value = attachment.url;

      // Envoyer l'ID au parent (ACF stocke l'ID par défaut)
      emit("update:modelValue", attachment.id);
    });
  }

  mediaFrame.open();
};

const removeImage = () => {
  previewUrl.value = "";
  emit("update:modelValue", "");
};
</script>

<style scoped>
.wp-media-uploader {
  margin-bottom: 1rem;
}
.uploader-label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #1e293b;
}
.uploader-container {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
.image-placeholder {
  width: 100%;
  padding: 2rem;
  background: #f8fafc;
  border: 2px dashed #cbd5e0;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.image-placeholder:hover {
  border-color: #94a3b8;
  background: #f1f5f9;
}
.image-placeholder .icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}
.image-placeholder .text {
  color: #64748b;
  font-size: 0.9rem;
}
.image-preview {
  position: relative;
  width: 100%;
  max-width: 400px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.image-preview img {
  width: 100%;
  height: 200px;
  object-fit: cover;
  display: block;
}
.remove-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  background: #ef4444;
  color: white;
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
.remove-btn:hover {
  background: #dc2626;
}
.mt-2 {
  margin-top: 0.5rem;
}
</style>
