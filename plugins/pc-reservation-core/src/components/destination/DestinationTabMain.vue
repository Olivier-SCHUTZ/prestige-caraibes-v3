<template>
  <div class="pc-tab-main">
    <div class="pc-form-grid" style="display: grid; gap: 20px">

      <div class="pc-form-group" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1rem; color: #1e293b;">
          🖼️ Images de la destination
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
          
          <div class="pc-form-group">
            <label style="display: block; font-weight: 600; margin-bottom: 10px">Hero Desktop</label>
            <div class="pc-media-box" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 10px; text-align: center; background: white; min-height: 120px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                
                <div v-if="formData.dest_hero_desktop && formData.dest_hero_desktop.url" style="position: relative; width: 100%;">
                    <img :src="formData.dest_hero_desktop.url" style="width: 100%; height: 120px; object-fit: cover; border-radius: 6px;" />
                    <button @click.prevent="removeMedia('dest_hero_desktop')" style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 26px; height: 26px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">✕</button>
                </div>
                
                <div v-else>
                    <div style="font-size: 2rem; color: #cbd5e1; margin-bottom: 10px;">🖼️</div>
                    <button @click.prevent="openMedia('dest_hero_desktop')" class="pc-btn pc-btn-secondary pc-btn-sm">
                        Choisir une image
                    </button>
                </div>

            </div>
          </div>

          <div class="pc-form-group">
            <label style="display: block; font-weight: 600; margin-bottom: 10px">Hero Mobile</label>
            <div class="pc-media-box" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 10px; text-align: center; background: white; min-height: 120px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                
                <div v-if="formData.dest_hero_mobile && formData.dest_hero_mobile.url" style="position: relative; width: 100%;">
                    <img :src="formData.dest_hero_mobile.url" style="width: 100%; height: 120px; object-fit: cover; border-radius: 6px;" />
                    <button @click.prevent="removeMedia('dest_hero_mobile')" style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 26px; height: 26px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">✕</button>
                </div>
                
                <div v-else>
                    <div style="font-size: 2rem; color: #cbd5e1; margin-bottom: 10px;">📱</div>
                    <button @click.prevent="openMedia('dest_hero_mobile')" class="pc-btn pc-btn-secondary pc-btn-sm">
                        Choisir une image
                    </button>
                </div>

            </div>
          </div>

        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Région</label>
          <select v-model="formData.dest_region" class="pc-select" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px; background: white;">
            <option value="">Sélectionner une région...</option>
            <option value="grande-terre">Grande-Terre</option>
            <option value="basse-terre">Basse-Terre</option>
            <option value="iles-voisines">Îles voisines</option>
          </select>
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 10px">Type de plage</label>
          <div style="display: flex; gap: 20px; align-items: center; height: 40px;">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #334155;">
                  <input type="radio" v-model="formData.dest_sea_side" value="caraibes" style="width: 18px; height: 18px;" />
                  Mer des Caraïbes
              </label>
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #334155;">
                  <input type="radio" v-model="formData.dest_sea_side" value="atlantique" style="width: 18px; height: 18px;" />
                  Océan Atlantique
              </label>
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Latitude (GPS)</label>
          <input type="number" step="0.000001" v-model="formData.dest_geo_lat" class="pc-input" placeholder="Ex: 16.225" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Longitude (GPS)</label>
          <input type="number" step="0.000001" v-model="formData.dest_geo_lng" class="pc-input" placeholder="Ex: -61.534" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
        </div>
      </div>

      <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0" />

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px">
        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Population</label>
          <input type="number" v-model="formData.dest_population" class="pc-input" placeholder="Ex: 24000" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Surface (km²)</label>
          <input type="number" step="0.1" v-model="formData.dest_surface_km2" class="pc-input" placeholder="Ex: 80.2" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
        </div>

        <div class="pc-form-group">
          <label style="display: block; font-weight: 600; margin-bottom: 5px">Distance Aéroport (km)</label>
          <input type="number" step="0.1" v-model="formData.dest_airport_distance_km" class="pc-input" placeholder="Ex: 15.5" style="width: 100%; box-sizing: border-box; height: 40px; padding: 0 12px; border: 1px solid #cbd5e0; border-radius: 6px;" />
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

// Appel natif à la bibliothèque de médias WordPress (wp.media)
const openMedia = (fieldKey) => {
    if (typeof wp === 'undefined' || !wp.media) {
        alert("La bibliothèque de médias WordPress n'est pas chargée.");
        return;
    }

    const frame = wp.media({
        title: 'Sélectionner une image',
        button: { text: 'Utiliser cette image' },
        multiple: false
    });

    frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        
        // On stocke l'objet {id, url} pour affichage et sauvegarde
        props.formData[fieldKey] = {
            id: attachment.id,
            url: attachment.url
        };
    });

    frame.open();
};

// Suppression de l'image sélectionnée
const removeMedia = (fieldKey) => {
    props.formData[fieldKey] = null;
};
</script>