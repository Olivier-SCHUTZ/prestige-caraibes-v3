<template>
  <Teleport to="body">
    <div
      v-if="store.isCreateModalOpen"
      class="pcr-modal-overlay"
      @click.self="store.closeCreateModal()"
    >
      <div class="pcr-modal-content pcr-modal-large">
        <header class="pcr-modal-header">
          <div class="header-titles">
            <h2 v-if="formData.id">
              ✏️ Modifier la Réservation #{{ formData.id }}
            </h2>
            <h2 v-else>✨ Nouvelle Réservation / Devis</h2>
            <p class="text-muted" style="margin: 5px 0 0 0; font-size: 0.9em">
              Le devis se calcule automatiquement en fonction de vos choix.
            </p>
          </div>
          <button class="btn-close" @click="store.closeCreateModal()">
            &times;
          </button>
        </header>

        <div class="pcr-modal-body">
          <form @submit.prevent="handleCreate" class="pcr-form-grid">
            <div class="pcr-card-section">
              <h3>1. Type & Flux</h3>
              <div class="pcr-grid-3">
                <label class="pcr-field">
                  <span>Type</span>
                  <select v-model="formData.type">
                    <option value="location">🏠 Logement</option>
                    <option value="experience">🌴 Expérience</option>
                  </select>
                </label>
                <label class="pcr-field">
                  <span>Flux</span>
                  <select v-model="formData.type_flux">
                    <option value="devis">📝 Devis</option>
                    <option value="reservation">
                      ✅ Réservation Confirmée
                    </option>
                  </select>
                </label>
                <label class="pcr-field">
                  <span>Source / Plateforme</span>
                  <select v-model="formData.source">
                    <option value="direct">Direct / Site / Tél</option>
                    <option value="airbnb">Airbnb</option>
                    <option value="booking">Booking.com</option>
                    <option value="abritel">Abritel / VRBO</option>
                  </select>
                </label>
              </div>
              <label class="pcr-field mt-15">
                <span>{{
                  formData.type === "location"
                    ? "Choix du Logement"
                    : "Choix de l'Expérience"
                }}</span>
                <select v-model="formData.item_id">
                  <option value="">-- Sélectionner --</option>
                  <template v-if="formData.type === 'location'">
                    <option
                      v-for="item in store.bookingItems?.locations"
                      :key="item.id"
                      :value="item.id"
                    >
                      {{ item.title }}
                    </option>
                  </template>
                  <template v-if="formData.type === 'experience'">
                    <option
                      v-for="item in store.bookingItems?.experiences"
                      :key="item.id"
                      :value="item.id"
                    >
                      {{ item.title }}
                    </option>
                  </template>
                </select>
              </label>
            </div>

            <div class="pcr-card-section mt-15">
              <h3>2. Séjour & Occupants</h3>

              <div
                v-if="
                  formData.type === 'experience' && availableTariffs.length > 0
                "
                class="pcr-grid-1"
                style="margin-bottom: 15px"
              >
                <label class="pcr-field">
                  <span>Type de Tarif (Obligatoire)</span>
                  <select v-model="formData.experience_tarif_type">
                    <option value="">-- Sélectionnez un tarif --</option>
                    <option
                      v-for="tarif in availableTariffs"
                      :key="tarif.key"
                      :value="tarif.key"
                    >
                      {{ tarif.label || tarif.key }}
                    </option>
                  </select>
                </label>
              </div>

              <div v-if="formData.type === 'location'" class="pcr-grid-1">
                <label class="pcr-field">
                  <span>Séjour (Arrivée - Départ)</span>
                  <input
                    type="text"
                    ref="dateRangeInput"
                    placeholder="Sélectionnez vos dates..."
                    readonly
                    style="background: #fff; cursor: pointer"
                  />
                  <small
                    v-if="store.currentLogementConfig?.cap"
                    style="color: #d97706; font-weight: 600; margin-top: 4px"
                  >
                    ⚠️ Capacité max :
                    {{ store.currentLogementConfig.cap }} personnes
                  </small>
                </label>
              </div>
              <div v-else>
                <label class="pcr-field"
                  ><span>Date de l'expérience</span
                  ><input type="date" v-model="formData.date_experience"
                /></label>
              </div>

              <div class="pcr-grid-3 mt-15">
                <div class="pcr-field">
                  <span>Adultes</span>
                  <div class="pcr-counter-group">
                    <button type="button" @click="updateGuest('adultes', -1)">
                      -
                    </button>
                    <input
                      type="number"
                      min="1"
                      v-model.number="formData.adultes"
                      readonly
                    />
                    <button type="button" @click="updateGuest('adultes', 1)">
                      +
                    </button>
                  </div>
                </div>
                <div class="pcr-field">
                  <span>Enfants</span>
                  <div class="pcr-counter-group">
                    <button type="button" @click="updateGuest('enfants', -1)">
                      -
                    </button>
                    <input
                      type="number"
                      min="0"
                      v-model.number="formData.enfants"
                      readonly
                    />
                    <button type="button" @click="updateGuest('enfants', 1)">
                      +
                    </button>
                  </div>
                </div>
                <div class="pcr-field">
                  <span>Bébés</span>
                  <div class="pcr-counter-group">
                    <button type="button" @click="updateGuest('bebes', -1)">
                      -
                    </button>
                    <input
                      type="number"
                      min="0"
                      v-model.number="formData.bebes"
                      readonly
                    />
                    <button type="button" @click="updateGuest('bebes', 1)">
                      +
                    </button>
                  </div>
                </div>
              </div>
              <div
                v-if="
                  currentExperienceConfig &&
                  currentExperienceConfig.lines.some((l) => l.enable_qty)
                "
                class="mt-15"
                style="border-top: 1px dashed #cbd5e1; padding-top: 15px"
              >
                <div class="pcr-grid-2">
                  <template
                    v-for="(line, index) in currentExperienceConfig.lines"
                    :key="line.uid || index"
                  >
                    <div v-if="line.enable_qty" class="pcr-field">
                      <span>{{ line.label }} (Prix : {{ line.price }}€)</span>
                      <input
                        type="number"
                        min="0"
                        v-model.number="
                          formData.customQty[line.uid || `line_${index}`]
                        "
                      />
                    </div>
                  </template>
                </div>
              </div>

              <div
                v-if="
                  currentExperienceConfig &&
                  currentExperienceConfig.options &&
                  currentExperienceConfig.options.length > 0
                "
                class="mt-15"
                style="
                  background: #f8fafc;
                  padding: 15px;
                  border-radius: 6px;
                  border: 1px solid #e2e8f0;
                "
              >
                <h4
                  style="margin: 0 0 10px 0; color: #334155; font-size: 0.95em"
                >
                  Options supplémentaires
                </h4>

                <div
                  v-for="(opt, index) in currentExperienceConfig.options"
                  :key="opt.uid || index"
                  style="margin-bottom: 10px"
                >
                  <label
                    style="
                      display: flex;
                      align-items: center;
                      gap: 10px;
                      cursor: pointer;
                    "
                  >
                    <input
                      v-if="formData.options[opt.uid || `option_${index}`]"
                      type="checkbox"
                      v-model="
                        formData.options[opt.uid || `option_${index}`].selected
                      "
                    />
                    <strong
                      >{{ opt.label }}
                      <span style="color: #16a34a"
                        >(+{{ opt.price }}€)</span
                      ></strong
                    >
                  </label>

                  <div
                    v-if="
                      opt.enable_qty &&
                      formData.options[opt.uid || `option_${index}`]?.selected
                    "
                    style="margin-left: 25px; margin-top: 5px; max-width: 150px"
                  >
                    <label class="pcr-field">
                      <span>Quantité requise :</span>
                      <input
                        type="number"
                        min="1"
                        v-model.number="
                          formData.options[opt.uid || `option_${index}`].qty
                        "
                      />
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div class="pcr-card-section mt-15">
              <h3>3. Client & Informations</h3>
              <div class="pcr-grid-2">
                <label class="pcr-field"
                  ><span>Prénom</span
                  ><input type="text" v-model="formData.prenom"
                /></label>
                <label class="pcr-field"
                  ><span>Nom</span><input type="text" v-model="formData.nom"
                /></label>
              </div>
              <div class="pcr-grid-2 mt-15">
                <label class="pcr-field"
                  ><span>Email</span
                  ><input type="email" v-model="formData.email"
                /></label>
                <label class="pcr-field"
                  ><span>Téléphone</span
                  ><input type="text" v-model="formData.telephone"
                /></label>
              </div>
              <div class="pcr-grid-2 mt-15">
                <label class="pcr-field"
                  ><span>Numéro de devis</span
                  ><input
                    type="text"
                    v-model="formData.numero_devis"
                    placeholder="DEV-202X..."
                /></label>
                <label class="pcr-field"
                  ><span>Notes internes</span
                  ><input type="text" v-model="formData.notes_internes"
                /></label>
              </div>
              <div class="mt-15">
                <label class="pcr-field"
                  ><span>Commentaire client</span
                  ><textarea
                    v-model="formData.commentaire_client"
                    rows="2"
                  ></textarea>
                </label>
              </div>
            </div>

            <div class="pcr-card-section mt-15">
              <h3>4. Ajustements Manuels</h3>
              <div class="pcr-grid-2">
                <div
                  style="
                    border: 1px solid #cbd5e1;
                    padding: 10px;
                    border-radius: 6px;
                  "
                >
                  <label class="pcr-field"
                    ><span>Libellé Remise</span
                    ><input type="text" v-model="formData.remise_label"
                  /></label>
                  <label class="pcr-field mt-10"
                    ><span>Montant (€)</span
                    ><input
                      type="number"
                      step="0.01"
                      min="0"
                      v-model.number="formData.remise_montant"
                      placeholder="Ex: 50"
                  /></label>
                </div>
                <div
                  style="
                    border: 1px solid #cbd5e1;
                    padding: 10px;
                    border-radius: 6px;
                  "
                >
                  <label class="pcr-field"
                    ><span>Libellé Plus-value</span
                    ><input type="text" v-model="formData.plus_label"
                  /></label>
                  <label class="pcr-field mt-10"
                    ><span>Montant (€)</span
                    ><input
                      type="number"
                      step="0.01"
                      min="0"
                      v-model.number="formData.plus_montant"
                      placeholder="Ex: 50"
                  /></label>
                </div>
              </div>
            </div>

            <div
              class="pcr-card-section mt-15"
              style="background: #f0f9ff; border-color: #bae6fd"
            >
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                "
              >
                <h3 style="color: #0369a1; border: none; margin: 0">
                  5. Résumé du Devis
                </h3>
                <span
                  v-if="store.isCalculating"
                  style="font-size: 0.85em; color: #0369a1; font-weight: bold"
                  >⏳ Calcul...</span
                >
              </div>

              <table
                v-if="store.quotePreview"
                class="pcr-table-minimal mt-15"
                style="
                  width: 100%;
                  background: white;
                  border-radius: 6px;
                  overflow: hidden;
                  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                "
              >
                <thead
                  style="background: #f8fafc; border-bottom: 2px solid #e2e8f0"
                >
                  <tr>
                    <th
                      style="
                        padding: 12px 15px;
                        text-align: left;
                        color: #475569;
                      "
                    >
                      Désignation
                    </th>
                    <th
                      style="
                        padding: 12px 15px;
                        text-align: center;
                        color: #475569;
                        width: 60px;
                      "
                    >
                      Qté
                    </th>
                    <th
                      style="
                        padding: 12px 15px;
                        text-align: right;
                        color: #475569;
                        width: 100px;
                      "
                    >
                      Total
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(line, index) in store.quotePreview.lignes_devis"
                    :key="index"
                    style="border-bottom: 1px dashed #e2e8f0"
                  >
                    <td style="padding: 10px 15px; color: #334155">
                      {{ line.clean_label || line.label }}
                    </td>
                    <td
                      style="
                        padding: 10px 15px;
                        text-align: center;
                        color: #64748b;
                        font-weight: bold;
                      "
                    >
                      {{ line.qty || "-" }}
                    </td>
                    <td
                      style="
                        padding: 10px 15px;
                        text-align: right;
                        color: #0f172a;
                      "
                    >
                      <strong>{{ line.price || line.amount + " €" }}</strong>
                    </td>
                  </tr>

                  <tr
                    v-if="formData.remise_montant > 0"
                    style="color: #16a34a; background: #f0fdf4"
                  >
                    <td style="padding: 10px 15px">
                      {{ formData.remise_label || "Remise" }}
                    </td>
                    <td style="padding: 10px 15px; text-align: center">-</td>
                    <td style="padding: 10px 15px; text-align: right">
                      <strong>- {{ formData.remise_montant }} €</strong>
                    </td>
                  </tr>

                  <tr
                    v-if="formData.plus_montant > 0"
                    style="color: #dc2626; background: #fef2f2"
                  >
                    <td style="padding: 10px 15px">
                      {{ formData.plus_label || "Plus-value" }}
                    </td>
                    <td style="padding: 10px 15px; text-align: center">-</td>
                    <td style="padding: 10px 15px; text-align: right">
                      <strong>+ {{ formData.plus_montant }} €</strong>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr style="background: #f0f9ff">
                    <td
                      colspan="2"
                      style="
                        padding: 15px;
                        text-align: right;
                        font-size: 1.1em;
                        border-top: 2px solid #0369a1;
                        color: #0369a1;
                      "
                    >
                      <strong>TOTAL :</strong>
                    </td>
                    <td
                      style="
                        padding: 15px;
                        text-align: right;
                        font-size: 1.2em;
                        border-top: 2px solid #0369a1;
                        color: #0369a1;
                      "
                    >
                      <strong>{{ formattedFinalTotal }}</strong>
                    </td>
                  </tr>
                </tfoot>
              </table>
              <div
                v-else
                class="mt-15"
                style="color: #64748b; font-size: 0.9em"
              >
                {{
                  formData.type === "location"
                    ? "Sélectionnez un logement et des dates pour afficher le devis."
                    : "Sélectionnez une expérience et un tarif pour calculer le devis."
                }}
              </div>
            </div>
          </form>
        </div>

        <footer
          class="pcr-modal-footer"
          style="justify-content: flex-end; gap: 15px"
        >
          <button
            type="button"
            class="btn-secondary"
            @click="store.closeCreateModal()"
          >
            Annuler
          </button>
          <button
            type="button"
            class="btn-success"
            @click="handleCreate"
            :disabled="store.isLoading || !store.quotePreview"
          >
            ✅
            {{
              store.isLoading
                ? "Enregistrement..."
                : formData.id
                  ? "Mettre à jour la réservation"
                  : "Enregistrer la réservation"
            }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from "vue";
import { useReservationsStore } from "../../stores/reservations-store";

const store = useReservationsStore();

const formData = ref({
  id: null,
  type: "location",
  type_flux: "devis",
  source: "direct",
  item_id: "",
  experience_tarif_type: "",
  date_arrivee: "",
  date_depart: "",
  date_experience: "",
  adultes: 2,
  enfants: 0,
  bebes: 0,
  prenom: "",
  nom: "",
  email: "",
  telephone: "",
  remise_label: "Remise exceptionnelle",
  remise_montant: "",
  plus_label: "Plus-value",
  plus_montant: "",
  commentaire_client: "",
  notes_internes: "",
  numero_devis: "",
  customQty: {},
  options: {},
});

const availableTariffs = computed(() => {
  if (formData.value.type !== "experience" || !formData.value.item_id)
    return [];
  // On utilise les données pré-chargées par WordPress sur la page
  const allTariffs = window.pcResaParams?.experienceTarifs || {};
  return allTariffs[formData.value.item_id] || [];
});

// 🚀 NOUVEAU : Récupère la configuration exacte du tarif en cours pour générer l'interface
const currentExperienceConfig = computed(() => {
  if (
    formData.value.type !== "experience" ||
    !formData.value.item_id ||
    !formData.value.experience_tarif_type
  )
    return null;
  const allTariffs = window.pcResaParams?.experienceTarifs || {};
  const expTariffs = allTariffs[formData.value.item_id] || [];
  return (
    expTariffs.find((t) => t.key === formData.value.experience_tarif_type) ||
    null
  );
});

// 🚀 NOUVEAU : Quand le tarif change, on prépare les variables par défaut pour les options et quantités
watch(currentExperienceConfig, (newConfig) => {
  formData.value.customQty = {};
  formData.value.options = {};

  if (newConfig) {
    // 1. Pré-remplir les quantités des privatisations / forfaits
    if (newConfig.lines) {
      newConfig.lines.forEach((line, index) => {
        if (line.enable_qty) {
          const key = line.uid || `line_${index}`;
          formData.value.customQty[key] = line.default_qty
            ? Number(line.default_qty)
            : 0;
        }
      });
    }
    // 2. Pré-remplir les options
    if (newConfig.options) {
      newConfig.options.forEach((opt, index) => {
        const optId = opt.uid || `option_${index}`;
        formData.value.options[optId] = {
          selected: false,
          qty: opt.default_qty ? Number(opt.default_qty) : 1,
        };
      });
    }
  }
});

// ==========================================
// 🚀 L'ÉCOUTEUR DEPUIS LE CALENDRIER !
// ==========================================
const handleOpenFromCalendar = async (event) => {
  const data = event.detail;
  console.log("🚀 BookingForm réveillé par le calendrier !", data);

  if (
    !store.bookingItems ||
    !store.bookingItems.locations ||
    store.bookingItems.locations.length === 0
  ) {
    console.log("⏳ Chargement de la liste des logements...");
    await store.fetchBookingItems();
  }

  store.isCreateModalOpen = true;

  formData.value.type = "location";
  formData.value.item_id = Number(data.logementId);
  formData.value.date_arrivee = data.start;
  formData.value.date_depart = data.end;
};

onMounted(() => {
  window.addEventListener("pc-open-dashboard-modal", handleOpenFromCalendar);
});

onUnmounted(() => {
  window.removeEventListener("pc-open-dashboard-modal", handleOpenFromCalendar);
});
// ==========================================

const isPrefilling = ref(false); // 🚀 Bloqueur de radar

// 🚀 NOUVEAU : Quand la modale s'ouvre, on injecte les données d'édition
watch(
  () => store.isCreateModalOpen,
  async (isOpen) => {
    if (isOpen) {
      if (store.prefillData) {
        isPrefilling.value = true; // 🔴 On éteint le radar automatique

        // On remplit les champs du formulaire
        Object.keys(store.prefillData).forEach((key) => {
          if (formData.value.hasOwnProperty(key)) {
            formData.value[key] = store.prefillData[key];
          }
        });

        // 🚀 On restaure le devis tel qu'il a été sauvegardé la dernière fois !
        if (
          store.prefillData.quote_lines &&
          store.prefillData.quote_lines.length > 0
        ) {
          store.quotePreview = {
            montant_total: store.prefillData.montant_total,
            lignes_devis: store.prefillData.quote_lines,
          };
        }

        // On attend que Vue mette à jour l'interface visuelle
        await nextTick();

        // On rallume le radar après un court délai pour éviter qu'il s'affole au chargement
        setTimeout(() => {
          isPrefilling.value = false; // 🟢 On rallume le radar
        }, 300);
      }
    } else {
      // 🧹 Reset total du formulaire quand on le ferme
      formData.value.id = null;
      formData.value.item_id = "";
      formData.value.prenom = "";
      formData.value.nom = "";
      formData.value.adultes = 2;
      formData.value.enfants = 0;
      formData.value.bebes = 0;
      formData.value.numero_devis = "";
      formData.value.remise_montant = "";
      formData.value.plus_montant = "";
      store.quotePreview = null;
    }
  },
);

let calcTimeout;

// 🚀 LE FIX : On demande à Vue.js de surveiller TOUT l'objet formData
watch(
  formData,
  (newVal) => {
    if (isPrefilling.value) return; // 🚀 Si on est en train de pré-remplir la modale, on interdit au radar de recalculer et d'écraser le devis !

    if (newVal.item_id && store.bookingItems) {
      const locations = store.bookingItems.locations || [];
      const experiences = store.bookingItems.experiences || [];

      if (locations.length > 0 || experiences.length > 0) {
        const isValid =
          newVal.type === "location"
            ? locations.find((l) => l.id == newVal.item_id)
            : experiences.find((e) => e.id == newVal.item_id);
        if (!isValid) newVal.item_id = "";
      }
    }

    // 1. Si aucun item n'est sélectionné, on vide le devis
    if (!newVal.item_id) {
      store.quotePreview = null;
      return;
    }

    // 2. Sécurité Logement : On attend les deux dates
    if (
      newVal.type === "location" &&
      (!newVal.date_arrivee || !newVal.date_depart)
    ) {
      return;
    }

    // 3. 🚀 SÉCURITÉ EXPÉRIENCE : La date est 100% informative !
    // On exige UNIQUEMENT un tarif pour calculer le prix.
    if (newVal.type === "experience" && !newVal.experience_tarif_type) {
      return;
    }

    // 4. Lancement du calcul (On réduit le délai à 150ms pour que ce soit super fluide)
    clearTimeout(calcTimeout);
    calcTimeout = setTimeout(() => {
      store.calculateQuote(newVal);
    }, 150);
  },
  { deep: true }, // Surveille les objets imbriqués (options et quantités personnalisées)
);

const dateRangeInput = ref(null);
let flatpickrInstance = null;

// On surveille à la fois l'ID de l'item ET l'ouverture de la modale
watch(
  () => [formData.value.item_id, store.isCreateModalOpen],
  async ([newId, isOpen]) => {
    if (isOpen && formData.value.type === "location" && newId) {
      // 1. On charge la configuration (jours bloqués, etc.)
      await store.fetchHousingConfig(newId);

      // 2. 🚀 LA MAGIE EST LÀ : On attend que le champ <input> existe physiquement à l'écran !
      await nextTick();

      // Petit délai supplémentaire pour garantir que le DOM de Vue est 100% prêt
      setTimeout(() => {
        // 3. On initialise Flatpickr
        initFlatpickr();

        // 4. On nettoie les dates (suppression de l'heure si elle vient de la BDD : "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DD")
        const arrivee = formData.value.date_arrivee
          ? formData.value.date_arrivee.split(" ")[0]
          : null;
        const depart = formData.value.date_depart
          ? formData.value.date_depart.split(" ")[0]
          : null;

        // 5. On remplit visuellement le champ
        if (arrivee && depart && flatpickrInstance) {
          flatpickrInstance.setDate([arrivee, depart], false);
        }
      }, 50);
    }
  },
);

const initFlatpickr = () => {
  console.log("📅 Tentative d'initialisation du calendrier...");

  if (!window.flatpickr) {
    console.error(
      "🚨 ERREUR CRITIQUE : La librairie Flatpickr n'est pas chargée par WordPress !",
    );
    return;
  }

  if (!dateRangeInput.value) {
    console.error(
      "🚨 ERREUR : Le champ input du calendrier est introuvable dans le DOM !",
    );
    return;
  }

  if (flatpickrInstance) flatpickrInstance.destroy();

  const config = store.currentLogementConfig;
  const disableRanges = Array.isArray(config?.icsDisable)
    ? config.icsDisable.filter((r) => r && r.from && r.to)
    : [];

  flatpickrInstance = window.flatpickr(dateRangeInput.value, {
    mode: "range",
    dateFormat: "Y-m-d", // Format technique interne lu par setDate
    altInput: true, // Crée un champ visuel masquant l'original
    altFormat: "d/m/Y", // Format d'affichage pour l'utilisateur
    minDate: "today",
    locale: window.flatpickr.l10ns?.fr || "fr",
    defaultDate:
      formData.value.date_arrivee && formData.value.date_depart
        ? [formData.value.date_arrivee, formData.value.date_depart]
        : null,
    disable: [
      function (date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        const ymd = `${year}-${month}-${day}`;
        return disableRanges.some((r) => ymd >= r.from && ymd <= r.to);
      },
    ],
    onChange: (selectedDates) => {
      if (selectedDates.length === 2) {
        const formatYMD = (d) =>
          `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
        formData.value.date_arrivee = formatYMD(selectedDates[0]);
        formData.value.date_depart = formatYMD(selectedDates[1]);
      } else {
        formData.value.date_arrivee = "";
        formData.value.date_depart = "";
      }
    },
  });
};

// 🚀 NOUVEAU : La fonction manquante pour gérer les clics sur les boutons + et -
const updateGuest = (type, step) => {
  if (type === "adultes") {
    const newVal = formData.value.adultes + step;
    if (newVal >= 1) formData.value.adultes = newVal;
  } else if (type === "enfants") {
    const newVal = formData.value.enfants + step;
    if (newVal >= 0) formData.value.enfants = newVal;
  } else if (type === "bebes") {
    const newVal = formData.value.bebes + step;
    if (newVal >= 0) formData.value.bebes = newVal;
  }
};

watch([() => formData.value.adultes, () => formData.value.enfants], () => {
  if (formData.value.type === "location" && store.currentLogementConfig?.cap) {
    const capMax = parseInt(store.currentLogementConfig.cap, 10);
    const totalGuests = formData.value.adultes + formData.value.enfants;

    if (totalGuests > capMax) {
      const overflow = totalGuests - capMax;
      if (formData.value.enfants >= overflow) {
        formData.value.enfants -= overflow;
      } else {
        formData.value.adultes -= overflow - formData.value.enfants;
        formData.value.enfants = 0;
      }
    }
  }
});

// ==========================================
// 💰 CALCULS DU DEVIS (Total & Formatage)
// ==========================================
const finalTotalComputed = computed(() => {
  if (!store.quotePreview) return 0;
  let totalBase = store.quotePreview.montant_total || 0;
  let remise = parseFloat(formData.value.remise_montant) || 0;
  let plus = parseFloat(formData.value.plus_montant) || 0;
  return Math.max(0, totalBase - remise + plus);
});

const formattedFinalTotal = computed(() => {
  return finalTotalComputed.value.toLocaleString("fr-FR", {
    style: "currency",
    currency: "EUR",
  });
});

// ==========================================
// 💾 SAUVEGARDE EN BASE DE DONNÉES
// ==========================================
const handleCreate = async () => {
  try {
    // Sécurité : On s'assure qu'un devis a bien été généré avant de sauvegarder
    if (!store.quotePreview) {
      alert("⚠️ Veuillez attendre le calcul du devis avant d'enregistrer.");
      return;
    }

    const payloadToSave = {
      ...formData.value,
      montant_total: finalTotalComputed.value, // La variable est de retour !
    };

    const result = await store.createReservation(payloadToSave);
    if (result && result.success) {
      // On prévient le grand calendrier de se mettre à jour
      window.dispatchEvent(new CustomEvent("pc-refresh-calendar"));
      // On ferme la modale
      store.closeCreateModal();
    }
  } catch (error) {
    alert("❌ Erreur lors de l'enregistrement : " + error.message);
  }
};
</script>

<style scoped>
.pcr-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(2px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 99999; /* 🚀 Assure que c'est bien par-dessus TOUT */
}
.pcr-modal-large {
  width: 95%;
  max-width: 800px;
  max-height: 90vh;
  background: #f8f9fa;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.pcr-modal-header {
  background: white;
  padding: 20px 25px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.header-titles h2 {
  margin: 0;
  font-size: 1.4rem;
  color: #333;
}
.btn-close {
  background: none;
  border: none;
  font-size: 1.8rem;
  cursor: pointer;
  color: #999;
  line-height: 1;
}
.pcr-modal-body {
  padding: 25px;
  overflow-y: auto;
  flex-grow: 1;
}
.pcr-card-section {
  background: white;
  padding: 20px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}
.pcr-card-section h3 {
  margin: 0 0 15px 0;
  font-size: 1.1rem;
  color: #1e293b;
  border-bottom: 1px solid #eee;
  padding-bottom: 8px;
}
.pcr-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}
.pcr-grid-3 {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 15px;
}
.mt-15 {
  margin-top: 15px;
}
.mt-10 {
  margin-top: 10px;
}
.text-muted {
  color: #94a3b8;
}
.pcr-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.pcr-field span {
  font-size: 0.85em;
  font-weight: 600;
  color: #475569;
}
.pcr-field input,
.pcr-field select,
.pcr-field textarea {
  padding: 10px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 0.95em;
  outline: none;
  transition: border-color 0.2s;
  font-family: inherit;
}
.pcr-field input:focus,
.pcr-field select:focus,
.pcr-field textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.pcr-counter-group {
  display: flex;
  align-items: stretch;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  overflow: hidden;
  height: 40px;
}
.pcr-counter-group button {
  background: #f1f5f9;
  border: none;
  width: 40px;
  font-size: 1.2em;
  cursor: pointer;
  color: #334155;
  transition: background 0.2s;
}
.pcr-counter-group button:hover {
  background: #e2e8f0;
}
.pcr-counter-group input {
  border: none;
  width: 100%;
  text-align: center;
  border-left: 1px solid #cbd5e1;
  border-right: 1px solid #cbd5e1;
  border-radius: 0;
  pointer-events: none;
}

.pcr-modal-footer {
  padding: 15px 25px;
  border-top: 1px solid #eee;
  background: white;
  display: flex;
  align-items: center;
}
.btn-secondary {
  padding: 10px 15px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.btn-success {
  padding: 10px 20px;
  background: #16a34a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
}
.btn-success:hover {
  background: #15803d;
}
.btn-success:disabled {
  background: #94a3b8;
  cursor: not-allowed;
}

.pcr-quote-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.pcr-quote-list li {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  border-bottom: 1px dashed #eee;
  font-size: 0.9em;
}
</style>
<style>
/* 🚀 LA PIÈCE MANQUANTE 2 : FORCER LE CALENDRIER AU-DESSUS DE LA MODALE NOIRE */
.flatpickr-calendar {
  z-index: 999999 !important;
}
</style>
