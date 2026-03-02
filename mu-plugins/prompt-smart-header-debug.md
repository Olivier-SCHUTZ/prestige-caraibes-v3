# 🔧 PROMPT: Réparer le Smart Header Scroll System

## 📋 CONTEXTE

J'ai un système de header WordPress refactorisé avec une architecture modulaire (PHP + CSS) qui fonctionne parfaitement, SAUF le système de smart header qui devrait faire disparaître le header quand on scrolle vers le bas et le faire réapparaître quand on scrolle vers le haut.

## 🚨 PROBLÈME ACTUEL

Le **Smart Header Scroll System ne fonctionne PAS** - le header reste toujours visible au lieu de se cacher/montrer selon la direction du scroll.

## 📁 FICHIERS CONCERNÉS

### JavaScript actuel (intégré dans header-main.js) :

```javascript
function initSmartHeader() {
  const header = findHeaderRoot();
  header.classList.add("pc-hg-smart");

  // Logique scroll avec hide/show
  // Classes: .pc-solid, .pc-hidden
  // Transform: translateY(-100%)
}
```

### CSS actuel (header-smart.css) :

```css
.pc-hg-smart.pc-hidden {
  transform: translateY(-100%);
}
```

## 🎯 OBJECTIF

Je veux que le header :

1. **Se CACHE** quand l'utilisateur scrolle vers le BAS
2. **RÉAPPARAISSE** quand l'utilisateur scrolle vers le HAUT
3. **Reste visible** en haut de page
4. **Devienne solide** après quelques pixels de scroll

## 🛠️ DEUX OPTIONS POSSIBLES

### OPTION A: Réparer l'existant

- Diagnostiquer pourquoi le JavaScript actuel ne fonctionne pas
- Corriger la logique de détection de scroll
- Vérifier que les classes CSS sont bien appliquées

### OPTION B: Système moderne

- Remplacer par un système Intersection Observer moderne
- Utiliser des techniques de performance optimisées
- Code plus léger et plus fiable

## 📂 STRUCTURE DES FICHIERS

```
mu-plugins/pc-header/
├── assets/js/header-main.js (contient initSmartHeader)
├── assets/css/components/header-smart.css
└── [autres fichiers du header refactorisé]
```

## ⚡ ACTION DEMANDÉE

Choisis la meilleure option et **implémente une solution qui fonctionne parfaitement** pour ce comportement de scroll hide/show sur le header.

## 🔍 INFORMATIONS TECHNIQUES

- WordPress avec mu-plugins
- Header avec ID `#pc-header` et classe `.pc-hg-smart`
- CSS : position fixed, transform translateY pour hide/show
- JavaScript Vanilla (pas de jQuery)
- Support mobile + desktop requis

---

**Peux-tu analyser et corriger ce système de smart header scroll pour qu'il fonctionne parfaitement ?**
