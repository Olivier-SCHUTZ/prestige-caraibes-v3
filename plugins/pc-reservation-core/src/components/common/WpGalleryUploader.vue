<template>
  <div class="pc-gallery-uploader">
    <div class="pc-gallery-grid" v-if="images.length > 0">
      <div
        v-for="(img, index) in images"
        :key="img.id + '-' + index"
        class="pc-gallery-thumb"
        draggable="true"
        @dragstart="onDragStart(index)"
        @dragover.prevent
        @dragenter.prevent
        @drop="onDrop(index)"
      >
        <img :src="img.url" alt="Miniature" />

        <div class="pc-thumb-overlay">
          <span class="pc-drag-handle">↕️</span>
          <button
            type="button"
            class="pc-remove-btn"
            @click.stop.prevent="removeImage(index)"
          >
            🗑️
          </button>
        </div>
      </div>
    </div>

    <div v-else class="pc-gallery-empty">
      <p>Aucune image sélectionnée</p>
    </div>

    <button
      type="button"
      class="pc-btn pc-btn-secondary pc-btn-sm"
      @click.prevent="openMediaLibrary"
    >
      <span>📸</span> Ajouter des images
    </button>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";

const props = defineProps({
  modelValue: {
    type: [Array, String],
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue"]);

// État interne pour stocker les IDs et URLs associées
const images = ref([]);

// --- CHARGEMENT INITIAL DES URLs DEPUIS LES IDs ---
onMounted(() => {
  loadExistingImages();
});

watch(
  () => props.modelValue,
  (newVal) => {
    // Si le tableau est vidé de l'extérieur
    if (!newVal || newVal.length === 0) {
      images.value = [];
    }
  },
  { deep: true },
);

const loadExistingImages = () => {
  let ids = [];
  if (Array.isArray(props.modelValue)) {
    ids = props.modelValue;
  } else if (
    typeof props.modelValue === "string" &&
    props.modelValue.trim() !== ""
  ) {
    ids = props.modelValue
      .split(",")
      .map((id) => parseInt(id.trim()))
      .filter((id) => !isNaN(id));
  }

  if (ids.length === 0) return;

  // On initialise les images avec des placeholders
  images.value = ids.map((id) => ({ id, url: "" }));

  // On tente de récupérer les URLs réelles via wp.media (comme dans l'ancien JS)
  if (typeof wp !== "undefined" && wp.media && wp.media.attachment) {
    ids.forEach((id, index) => {
      const attachment = wp.media.attachment(id);
      attachment
        .fetch()
        .then(() => {
          const data = attachment.toJSON();
          images.value[index].url = data.sizes?.thumbnail?.url || data.url;
        })
        .catch(() => {
          // Fallback en cas d'erreur de chargement
          images.value[index].url =
            "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjNGNEY2Ii8+Cjwvc3ZnPg==";
        });
    });
  }
};

// --- GESTION WP MEDIA LIBRARY ---
const openMediaLibrary = () => {
  if (typeof wp === "undefined" || !wp.media) {
    alert("La librairie WordPress Media n'est pas disponible.");
    return;
  }

  const galleryUploader = wp.media({
    title: "Sélectionner des images",
    button: { text: "Ajouter à la pièce" },
    multiple: true, // Permet la sélection multiple
  });

  galleryUploader.on("select", () => {
    const attachments = galleryUploader.state().get("selection").toJSON();

    // On ajoute les nouvelles images à notre tableau local
    attachments.forEach((att) => {
      images.value.push({
        id: att.id,
        url: att.sizes?.thumbnail?.url || att.url,
      });
    });

    updateModel();
  });

  galleryUploader.open();
};

const removeImage = (index) => {
  images.value.splice(index, 1);
  updateModel();
};

const updateModel = () => {
  // On renvoie uniquement un tableau d'IDs au store, comme l'attend ACF
  const ids = images.value.map((img) => img.id);
  emit("update:modelValue", ids);
};

// --- GLISSER-DÉPOSER (SORTING) ---
let draggedIndex = null;

const onDragStart = (index) => {
  draggedIndex = index;
};

const onDrop = (dropIndex) => {
  if (draggedIndex === null || draggedIndex === dropIndex) return;

  const itemToMove = images.value.splice(draggedIndex, 1)[0];
  images.value.splice(dropIndex, 0, itemToMove);

  draggedIndex = null;
  updateModel(); // On met à jour l'ordre des IDs
};
</script>

<style scoped>
.pc-gallery-uploader {
  background: white;
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 8px;
}

.pc-gallery-empty {
  text-align: center;
  padding: 2rem;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  color: #64748b;
  margin-bottom: 1rem;
}

.pc-gallery-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 1rem;
}

.pc-gallery-thumb {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  cursor: grab;
  background: #f1f5f9;
}

.pc-gallery-thumb:active {
  cursor: grabbing;
}

.pc-gallery-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pc-thumb-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 5px;
  opacity: 0;
  transition: opacity 0.2s;
}

.pc-gallery-thumb:hover .pc-thumb-overlay {
  opacity: 1;
}

.pc-drag-handle {
  color: white;
  text-align: center;
  font-size: 1.2rem;
}

.pc-remove-btn {
  background: white;
  border: none;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  font-size: 0.8rem;
  cursor: pointer;
  align-self: center;
  color: #ef4444;
}

.pc-remove-btn:hover {
  background: #fee2e2;
  transform: scale(1.1);
}

.pc-btn-secondary {
  background: #f8fafc;
  border: 1px solid #cbd5e0;
  color: #475569;
}

.pc-btn-secondary:hover {
  background: #f1f5f9;
}
</style>
