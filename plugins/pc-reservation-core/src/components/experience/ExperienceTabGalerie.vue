<template>
  <div class="pc-tab-galerie">
    <div
      style="
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      "
    >
      <div>
        <h3 style="margin-top: 0; color: #1e293b; font-size: 1.2rem">
          Galerie Photos
        </h3>
        <p style="color: #64748b; font-size: 0.9rem; margin: 0">
          Ajoutez jusqu'à 5 photos pour illustrer votre expérience.
        </p>
      </div>

      <button
        v-if="gallery.length > 0"
        @click="clearGallery"
        class="pc-btn pc-btn-sm pc-btn-secondary"
        style="color: #ef4444; border-color: #fca5a5"
      >
        <span>🗑️</span> Vider la galerie
      </button>
    </div>

    <div
      v-if="gallery.length > 0"
      style="
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
      "
    >
      <div
        v-for="(image, index) in gallery"
        :key="image.id || index"
        style="
          position: relative;
          border-radius: 8px;
          overflow: hidden;
          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
          background: #f8fafc;
          aspect-ratio: 1 / 1;
        "
      >
        <img
          :src="image.thumbnail || image.url"
          :alt="'Photo ' + (index + 1)"
          style="width: 100%; height: 100%; object-fit: cover; display: block"
        />

        <button
          @click="removePhoto(index)"
          title="Retirer cette image"
          style="
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(239, 68, 68, 0.95);
            color: white;
            border: none;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
          "
        >
          ✕
        </button>
      </div>
    </div>

    <div
      @click="openGalleryUploader"
      style="
        width: 100%;
        padding: 3rem 2rem;
        background: #f8fafc;
        border: 2px dashed #cbd5e0;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
      "
      onmouseover="
        this.style.borderColor = '#94a3b8';
        this.style.background = '#f1f5f9';
      "
      onmouseout="
        this.style.borderColor = '#cbd5e0';
        this.style.background = '#f8fafc';
      "
    >
      <div style="font-size: 2.5rem; margin-bottom: 10px">📸</div>
      <p style="margin: 0; font-weight: 600; color: #475569">
        {{
          gallery.length > 0
            ? "Ajouter d'autres photos"
            : "Cliquez ici pour sélectionner des photos"
        }}
      </p>
      <div
        v-if="gallery.length > 0"
        style="
          margin-top: 15px;
          display: inline-block;
          padding: 4px 12px;
          background: #dcfce7;
          color: #166534;
          border-radius: 20px;
          font-size: 0.85rem;
          font-weight: 600;
        "
      >
        ✅ {{ gallery.length }} image(s) dans la galerie
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useExperienceStore } from "../../stores/experience-store";

const store = useExperienceStore();
const { currentExperience: experience } = storeToRefs(store);

// Propriété calculée pour s'assurer qu'on travaille toujours avec un tableau
const gallery = computed(() => {
  if (!experience.value.photos_experience) {
    experience.value.photos_experience = [];
  }
  return experience.value.photos_experience;
});

let galleryFrame = null;

const openGalleryUploader = () => {
  if (typeof window.wp === "undefined" || !window.wp.media) {
    alert("Erreur: La bibliothèque de médias WordPress n'est pas disponible.");
    return;
  }

  // Instanciation unique
  if (!galleryFrame) {
    galleryFrame = window.wp.media({
      title: "Sélectionner des photos pour la galerie",
      button: { text: "Ajouter à la galerie" },
      multiple: true, // Autorise la sélection multiple
      library: { type: "image" },
    });

    galleryFrame.on("select", () => {
      const selection = galleryFrame.state().get("selection");

      // Sécurité : initialiser le tableau si vide
      if (!experience.value.photos_experience) {
        experience.value.photos_experience = [];
      }

      selection.models.forEach((model) => {
        const att = model.toJSON();

        // Vérifier que l'image n'est pas déjà dans la galerie
        const exists = experience.value.photos_experience.find(
          (img) => img.id === att.id,
        );

        if (!exists) {
          experience.value.photos_experience.push({
            id: att.id,
            url: att.url,
            thumbnail:
              att.sizes && att.sizes.thumbnail
                ? att.sizes.thumbnail.url
                : att.url,
          });
        }
      });
    });
  }

  galleryFrame.open();
};

const removePhoto = (index) => {
  experience.value.photos_experience.splice(index, 1);
};

const clearGallery = () => {
  if (confirm("Voulez-vous vraiment vider toute la galerie ?")) {
    experience.value.photos_experience = [];
  }
};
</script>
