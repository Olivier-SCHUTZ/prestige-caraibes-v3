<template>
  <div class="pc-post-selector">
    
    <div class="pc-search-bar">
      <input 
        type="text" 
        v-model="searchQuery" 
        @keydown.enter.prevent="searchPosts"
        placeholder="Chercher une villa ou un appartement..." 
        class="regular-text"
      />
      <button type="button" class="button" @click="searchPosts" :disabled="isSearching">
        {{ isSearching ? 'Recherche...' : 'Chercher' }}
      </button>
    </div>

    <div v-if="searchResults.length > 0" class="pc-search-results">
      <p class="description">Résultats : Cliquez pour ajouter à la sélection.</p>
      <ul>
        <li v-for="post in searchResults" :key="post.id" @click="addPost(post)">
          <span class="pc-badge">{{ post.type }}</span>
          <span v-html="post.title"></span>
          <span class="pc-add-icon">+</span>
        </li>
      </ul>
    </div>
    <div v-else-if="hasSearched && searchResults.length === 0" class="pc-no-results">
      Aucun logement trouvé pour cette recherche.
    </div>

    <div class="pc-selected-posts">
      <h4>Logements sélectionnés ({{ localSelection.length }})</h4>
      <p class="description" v-if="localSelection.length === 0">Aucun logement sélectionné.</p>
      
      <ul v-else class="pc-selection-list">
        <li v-for="(item, index) in localSelection" :key="item.id">
          <span class="pc-item-title" v-html="item.title"></span>
          
          <div class="pc-item-actions">
            <button type="button" class="button-link pc-move" @click.prevent="moveItem(index, -1)" :disabled="index === 0" title="Monter">↑</button>
            <button type="button" class="button-link pc-move" @click.prevent="moveItem(index, 1)" :disabled="index === localSelection.length - 1" title="Descendre">↓</button>
            <button type="button" class="button-link pc-delete" @click.prevent="removePost(index)">Retirer</button>
          </div>
        </li>
      </ul>
    </div>

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

const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)
const hasSearched = ref(false)

// La sélection locale (tableau d'objets {id, title})
const localSelection = ref([...props.modelValue])

watch(() => props.modelValue, (newVal) => {
  localSelection.value = [...newVal]
}, { deep: true })

const updateParent = () => {
  emit('update:modelValue', localSelection.value)
}

// Interrogation de l'API REST native
const searchPosts = async () => {
  if (searchQuery.value.trim().length < 2) return
  isSearching.value = true
  hasSearched.value = true

  try {
    // Requête parallèle sur les 2 CPTs pour plus de rapidité et de sécurité
    const [villasRes, appartsRes] = await Promise.all([
      fetch(`/wp-json/wp/v2/villa?search=${searchQuery.value}&_fields=id,title`),
      fetch(`/wp-json/wp/v2/appartement?search=${searchQuery.value}&_fields=id,title`)
    ])

    const villas = villasRes.ok ? await villasRes.json() : []
    const apparts = appartsRes.ok ? await appartsRes.json() : []

    // Formatage des données
    const format = (items, type) => items.map(i => ({ id: i.id, title: i.title.rendered, type }))
    searchResults.value = [...format(villas, 'Villa'), ...format(apparts, 'Appartement')]
  } catch (error) {
    console.error("Erreur API REST :", error)
  }
  isSearching.value = false
}

const addPost = (post) => {
  // Évite les doublons
  if (!localSelection.value.some(p => p.id === post.id)) {
    localSelection.value.push({ id: post.id, title: post.title })
    updateParent()
  }
}

const removePost = (index) => {
  localSelection.value.splice(index, 1)
  updateParent()
}

const moveItem = (index, direction) => {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= localSelection.value.length) return
  const item = localSelection.value.splice(index, 1)[0]
  localSelection.value.splice(newIndex, 0, item)
  updateParent()
}
</script>

<style scoped>
.pc-post-selector { background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; }
.pc-search-bar { display: flex; gap: 10px; margin-bottom: 15px; }
.pc-search-bar input { flex-grow: 1; }
.pc-search-results { background: #f0f0f1; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
.pc-search-results ul { margin: 10px 0 0 0; border: 1px solid #c3c4c7; background: #fff; max-height: 200px; overflow-y: auto; }
.pc-search-results li { padding: 8px 10px; border-bottom: 1px solid #f0f0f1; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 10px; }
.pc-search-results li:hover { background: #f6f7f7; }
.pc-badge { background: #007a92; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.pc-add-icon { margin-left: auto; font-weight: bold; color: #007a92; }
.pc-no-results { color: #d63638; margin-bottom: 15px; }
.pc-selected-posts h4 { margin: 0 0 10px 0; }
.pc-selection-list { border: 1px solid #c3c4c7; background: #f9f9f9; }
.pc-selection-list li { display: flex; justify-content: space-between; padding: 8px 10px; border-bottom: 1px solid #e2e4e7; margin: 0; }
.pc-item-actions { display: flex; gap: 10px; }
.pc-move { text-decoration: none; font-weight: bold; }
.pc-move:disabled { opacity: 0.3; cursor: not-allowed; }
.pc-delete { color: #d63638; }
</style>