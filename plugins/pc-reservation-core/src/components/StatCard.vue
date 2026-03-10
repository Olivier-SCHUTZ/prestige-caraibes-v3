<template>
  <div class="stat-card" :class="{ 'has-alert': hasAlert }">
    <h3>{{ title }}</h3>
    <div class="stat-value" :class="{ revenue: isRevenue }">
      {{ value }}
    </div>
    <div v-if="hasAlert && alertText" class="stat-alert">
      {{ alertText }}
    </div>
  </div>
</template>

<script setup>
// Déclaration des "Props" : les paramètres que ce composant peut recevoir de l'extérieur
defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  isRevenue: {
    type: Boolean,
    default: false,
  },
  hasAlert: {
    type: Boolean,
    default: false,
  },
  alertText: {
    type: String,
    default: "",
  },
});
</script>

<style scoped>
/* Le CSS est transféré ici. L'attribut "scoped" garantit qu'il ne s'appliquera QU'À ce composant ! */
.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  transition: transform 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
}

.stat-card h3 {
  font-size: 0.9rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 0.5rem 0;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #0f172a;
}

.stat-value.revenue {
  color: #10b981;
}

.stat-card.has-alert {
  border-color: #f59e0b;
  background-color: #fffbeb;
}

.stat-alert {
  margin-top: 0.5rem;
  font-size: 0.8rem;
  color: #d97706;
  font-weight: 600;
  background: #fef3c7;
  padding: 2px 8px;
  border-radius: 4px;
}
</style>
