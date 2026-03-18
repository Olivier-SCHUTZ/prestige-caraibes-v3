# 📄 Rapport d'Audit - Système de Documents PDF

## Migration vers Architecture Vue 3 + Pinia

**Date du rapport :** 18/03/2026  
**Version du plugin :** pc-reservation-core  
**Auditeur :** Cline AI Assistant

---

## 1. État des lieux de l'existant

### 📁 Inventaire des fichiers PHP/JS actuels

#### **Backend PHP - Architecture DDD Modern**

```
📂 includes/
├── class-documents.php                           ← Façade/Proxy (rétrocompatibilité)
├── ajax/controllers/
│   └── class-document-ajax-controller.php        ← Contrôleur AJAX moderne
└── services/document/
    ├── class-document-service.php                ← Service principal (Orchestrateur)
    ├── class-document-repository.php             ← Repository (BDD)
    ├── class-document-financial-calculator.php   ← Calculateur financier
    └── renderers/                                ← Renderers spécialisés
        ├── class-base-renderer.php               ← Classe abstraite parente
        ├── class-invoice-renderer.php            ← Factures/Devis/Avoirs
        ├── class-deposit-renderer.php            ← Factures d'acompte
        ├── class-contract-renderer.php           ← Contrats de location
        ├── class-voucher-renderer.php            ← Vouchers expériences
        └── class-custom-renderer.php             ← Modèles personnalisés
```

#### **Frontend JavaScript Legacy**

```
📂 assets/js/modules/
└── documents.js                                  ← Module jQuery/Vanilla (À MIGRER)
```

#### **Frontend Vue 3 (En cours)**

```
📂 src/components/dashboard/
└── ReservationModal.vue                         ← Onglet Documents (placeholder)
```

### 🛠️ Moteur de génération PDF

**DomPDF v3.1** configuré via Composer :

```json
{
  "require": {
    "dompdf/dompdf": "^3.1"
  }
}
```

**Configuration DomPDF** dans `PCR_Document_Service::generate()` :

- `isRemoteEnabled: true` (chargement images externes)
- `defaultFont: 'Helvetica'`
- Format A4 Portrait
- Mémoire augmentée à 512M
- Timeout de 120 secondes

### 📋 Structure des modèles de documents

#### **Templates Natifs** (Hardcodés dans le Service)

- **Devis** (`devis`) - via `PCR_Invoice_Renderer`
- **Facture principale** (`facture`) - via `PCR_Invoice_Renderer`
- **Facture d'acompte** (`facture_acompte`) - via `PCR_Deposit_Renderer`
- **Contrat de location** (`contrat`) - via `PCR_Contract_Renderer`
- **Voucher expériences** (`voucher`) - via `PCR_Voucher_Renderer`
- **Avoir** (`avoir`) - via `PCR_Invoice_Renderer`

#### **Templates Personnalisés**

- CPT `pc_pdf_template` avec éditeur WordPress
- Champs ACF pour contexte (global/location/experience)
- Rendu via `PCR_Custom_Renderer`

### 💾 Stockage physique et base de données

#### **Stockage des fichiers**

```
wp-content/uploads/pc-reservation/documents/{reservation_id}/
├── FAC-2026-0001.pdf
├── DEV-2026-12345.pdf
├── Contrat de location Client Nom, Villa Name.pdf
└── VOUCHER-RESA-67890.pdf
```

#### **Tracking en base de données**

Table `wp_pc_documents` :

```sql
CREATE TABLE wp_pc_documents (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id bigint(20) UNSIGNED NOT NULL,
    type_doc varchar(50) NOT NULL,
    numero_doc varchar(50) DEFAULT NULL,
    nom_fichier varchar(191) NOT NULL,
    chemin_fichier text NOT NULL,
    url_fichier text NOT NULL,
    date_creation datetime DEFAULT CURRENT_TIMESTAMP,
    user_id bigint(20) UNSIGNED DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_doc (reservation_id, type_doc),
    KEY reservation_id (reservation_id)
);
```

---

## 2. Principe de fonctionnement actuel

### 🔄 Flux complet de génération

#### **Étape 1 : Déclenchement**

- Admin clique sur "Générer la facture" dans la modale
- JavaScript `documents.js` capture l'événement
- Appel AJAX vers `wp_ajax_pc_generate_document`

#### **Étape 2 : Traitement backend**

```php
PCR_Documents::ajax_generate_document()
  ↓
PCR_Document_Service::generate($template_id, $reservation_id, $force_regenerate)
  ↓
1. Détection & validation du type de document
2. Vérification des règles métier (ex: acompte avant solde)
3. Aiguillage vers le bon Renderer
4. Calcul des données financières
5. Génération HTML
6. Conversion PDF via DomPDF
7. Sauvegarde physique + BDD
```

#### **Étape 3 : Injection des données dynamiques**

```php
PCR_Document_Financial_Calculator::calculate_for_reservation($resa)
  ↓
1. Parse du JSON detail_tarif
2. Nettoyage robuste des prix (entités HTML, espaces insécables)
3. Calcul HT/TVA selon les taux (logement, ménage, taxe séjour)
4. Récupération des paiements via PCR_Payment
5. Calcul du reste à payer
```

#### **Étape 4 : Rendu PDF et retour**

```php
Renderer spécialisé → HTML + CSS
  ↓
DomPDF → Fichier PDF
  ↓
Sauvegarde → wp-content/uploads/pc-reservation/documents/
  ↓
Response JSON → {success: true, url: "https://...", doc_number: "..."}
```

---

## 3. Stratégie de raccordement (Vue 3 ↔ PHP)

### 🏗️ Architecture proposée

#### **Création d'un Document Store Pinia**

```javascript
// src/stores/document-store.js
export const useDocumentStore = defineStore("documents", () => {
  const documents = ref([]);
  const templates = ref([]);
  const isGenerating = ref(false);
  const generationProgress = ref(0);

  // Actions : loadDocuments, loadTemplates, generateDocument, downloadDocument
  // Gestion des erreurs et états de loading
});
```

#### **Composant Vue Documents**

```vue
<!-- src/components/documents/DocumentsManager.vue -->
<template>
  <div class="documents-manager">
    <!-- Sélecteur de template -->
    <DocumentTemplateSelector />

    <!-- Actions de génération -->
    <DocumentActions />

    <!-- Liste des documents existants -->
    <DocumentsList />

    <!-- Prévisualisation PDF -->
    <PdfPreviewModal />
  </div>
</template>
```

### 🌐 Endpoints AJAX à utiliser/créer

#### **Endpoints existants** (dans `PCR_Document_Ajax_Controller`)

```php
✅ pc_get_documents_templates    // Récupère les modèles disponibles
✅ pc_get_reservation_files      // Liste des PDF générés
✅ pc_generate_document          // Génération (legacy dans PCR_Documents)
```

#### **Nouveaux endpoints à créer**

```php
🆕 pc_delete_document           // Suppression sécurisée
🆕 pc_preview_document          // Aperçu sans sauvegarde
🆕 pc_download_document         // Téléchargement sécurisé avec nonce
🆕 pc_document_generation_status // Polling pour progress bar
```

### ⚡ Gestion des retours Vue 3

#### **États de chargement**

```javascript
const documentStore = useDocumentStore();

// Génération avec spinner
await documentStore.generateDocument(templateId, reservationId, {
  onProgress: (progress) => {
    // Mise à jour de la progress bar
  },
  onSuccess: (pdfUrl) => {
    // Auto-téléchargement ou aperçu
  },
  onError: (error) => {
    // Toast d'erreur + gestion des cas spéciaux
  },
});
```

#### **Téléchargement via Blob/URL**

```javascript
const downloadDocument = async (documentUrl) => {
  try {
    const response = await fetch(documentUrl, {
      headers: { "X-WP-Nonce": wpNonce },
    });
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();

    window.URL.revokeObjectURL(url);
  } catch (error) {
    showErrorToast("Erreur de téléchargement");
  }
};
```

#### **Document Store dédié** ✅ RECOMMANDÉ

```javascript
// Séparation des responsabilités
const useDocumentStore = defineStore('documents', {
  state: () => ({
    availableTemplates: [],
    generatedDocuments: [],
    isLoading: false,
    currentReservationId: null
  }),

  actions: {
    async loadTemplatesForReservation(reservationId),
    async loadDocumentsForReservation(reservationId),
    async generateDocument(templateId, reservationId, options),
    async deleteDocument(documentId),
    async previewDocument(templateId)
  }
})
```

---

## 4. Sécurité Blindée (Critique)

### 🛡️ Analyse de la sécurité actuelle

#### **Points forts existants**

```php
✅ Vérification nonces : check_ajax_referer('pc_resa_manual_create', 'nonce')
✅ Contrôle des droits : current_user_can('edit_posts')
✅ Sanitisation : sanitize_text_field(), sanitize_file_name()
✅ Base Ajax Controller : PCR_Base_Ajax_Controller::verify_access()
```

#### **Stockage sécurisé**

```php
// Dossiers par réservation (isolation)
$upload_dir = wp_upload_dir();
$abs_path = $upload_dir['basedir'] . '/pc-reservation/documents/' . $reservation_id;

// Nommage sécurisé
$filename = sanitize_file_name($doc_number) . '.pdf';
```

### ⚠️ Vulnérabilités critiques identifiées

#### **1. Protection contre Path Traversal**

```php
// PROBLÈME POTENTIEL dans class-document-ajax-controller.php ligne ~140
$file_path = $resa_folder . '/' . $filename;

// SOLUTION : Validation stricte
private function validateFilePath($filename, $reservationId) {
    // 1. Vérifier que le nom ne contient pas de ../
    if (strpos($filename, '..') !== false) {
        throw new InvalidArgumentException('Chemin invalide détecté');
    }

    // 2. S'assurer que le fichier est dans le bon dossier
    $expected_dir = wp_upload_dir()['basedir'] . '/pc-reservation/documents/' . $reservationId;
    $real_path = realpath($expected_dir . '/' . basename($filename));

    if (!$real_path || strpos($real_path, $expected_dir) !== 0) {
        throw new InvalidArgumentException('Accès interdit au fichier');
    }

    return $real_path;
}
```

#### **2. Protection des URLs directes**

```php
// AJOUTER dans .htaccess du dossier uploads/pc-reservation/
<Files "*.pdf">
    Order Deny,Allow
    Deny from all
</Files>

// Accès uniquement via endpoint sécurisé
add_action('wp_ajax_pc_download_document_secure', 'pc_secure_document_download');
function pc_secure_document_download() {
    // Vérification nonce + droits
    // Vérification que l'utilisateur peut accéder à cette réservation
    // Stream sécurisé du fichier
}
```

#### **3. Échappement des données pour DomPDF**

```php
// Dans les Renderers, TOUJOURS échapper :
protected function escapeForPdf($data) {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Usage dans les templates :
echo $this->escapeForPdf($resa->nom);
echo $this->escapeForPdf($resa->prenom);
```

### 🔒 Améliorations sécurité strictes à implémenter

#### **Routes AJAX sécurisées**

```php
class PCR_Secure_Document_Controller extends PCR_Base_Ajax_Controller {

    public static function ajax_secure_download() {
        parent::verify_access('pc_document_download', 'nonce');

        $reservation_id = (int) $_POST['reservation_id'];
        $filename = sanitize_file_name($_POST['filename']);

        // Vérifier que l'utilisateur peut accéder à cette réservation
        if (!self::user_can_access_reservation($reservation_id)) {
            wp_send_json_error(['message' => 'Accès refusé']);
        }

        // Validation chemin
        $file_path = self::validateSecurePath($filename, $reservation_id);

        // Stream sécurisé
        self::streamPdfFile($file_path, $filename);
    }

    private static function streamPdfFile($file_path, $filename) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private');

        readfile($file_path);
        exit;
    }
}
```

#### **Logging d'audit**

```php
// Tracer tous les accès aux documents
private static function logDocumentAccess($action, $reservation_id, $filename, $user_id) {
    error_log(sprintf(
        '[PC-DOCUMENTS] %s - User:%d - Resa:%d - File:%s - IP:%s',
        $action,
        $user_id,
        $reservation_id,
        $filename,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ));
}
```

---

## 5. Plan de Refactoring détaillé

### 📋 Phase 1 : Création des stores et services (2-3h)

#### **Étape 1.1 : Document Store Pinia**

```bash
# Fichier : src/stores/document-store.js
✅ CRÉER le store avec les actions principales
✅ Gestion des états de loading et erreurs
✅ Cache des templates par type de réservation
```

#### **Étape 1.2 : Service API Documents**

```bash
# Fichier : src/services/document-api.js
✅ CRÉER les fonctions d'appel AJAX
✅ Gestion des headers de sécurité (nonce)
✅ Parsing des réponses serveur
```

### 📋 Phase 2 : Composants Vue (4-5h)

#### **Étape 2.1 : Composant principal**

```vue
<!-- Fichier : src/components/documents/DocumentsManager.vue -->
<template>
  <div class="documents-manager">
    <DocumentTemplateSelector
      :templates="documentStore.templates"
      :loading="documentStore.isLoadingTemplates"
      @template-selected="handleTemplateSelection"
    />

    <DocumentGenerationPanel
      :selectedTemplate="selectedTemplate"
      :isGenerating="documentStore.isGenerating"
      :progress="documentStore.progress"
      @generate="handleGeneration"
    />

    <DocumentsList
      :documents="documentStore.documents"
      :loading="documentStore.isLoadingDocuments"
      @download="handleDownload"
      @preview="handlePreview"
      @delete="handleDelete"
    />
  </div>
</template>
```

#### **Étape 2.2 : Sous-composants spécialisés**

```bash
✅ DocumentTemplateSelector.vue  - Sélection des modèles
✅ DocumentGenerationPanel.vue   - Actions de génération
✅ DocumentsList.vue             - Liste des PDF générés
✅ PdfPreviewModal.vue          - Modale d'aperçu
```

### 📋 Phase 3 : Backend sécurisé (3-4h)

#### **Étape 3.1 : Nouveaux endpoints**

```php
// Fichier : includes/ajax/controllers/class-secure-document-controller.php
✅ CRÉER ajax_secure_download()
✅ CRÉER ajax_delete_document()
✅ CRÉER ajax_preview_document()
✅ CRÉER ajax_generation_progress()
```

#### **Étape 3.2 : Renforcement sécurité**

```php
✅ AJOUTER validateFilePath() dans tous les contrôleurs
✅ MODIFIER les renderers pour échapper les données
✅ CRÉER .htaccess protection dans uploads/
✅ AJOUTER logging d'audit
```

### 📋 Phase 4 : Intégration dans ReservationModal (2h)

#### **Étape 4.1 : Remplacement du placeholder**

```vue
<!-- Dans ReservationModal.vue, remplacer : -->
<div v-if="activeTab === 'documents'" class="pcr-tab-content">
  <div class="pcr-card-section text-center p-30">
    <h3>Génération PDF</h3>
    <p class="text-muted">Ce module sera migré lors de la Phase 4 !</p>
  </div>
</div>

<!-- Par : -->
<div v-if="activeTab === 'documents'" class="pcr-tab-content">
  <DocumentsManager
    :reservationId="store.selectedReservation?.id"
    :reservationType="store.selectedReservation?.type"
  />
</div>
```

### 📋 Phase 5 : Nettoyage du legacy (1h)

#### **Étape 5.1 : Suppression du code JavaScript obsolète**

```bash
❌ SUPPRIMER assets/js/modules/documents.js (entièrement)
❌ SUPPRIMER les références dans dashboard-core.js :
   - window.pc_reload_documents
   - window.pc_load_templates
   - PCR.Documents.init()
```

#### **Étape 5.2 : Nettoyage des templates PHP**

```php
❌ SUPPRIMER dans templates/dashboard/popups.php :
   - #pc-pdf-preview-modal
   - #pc-invoice-blocked-popup

❌ SUPPRIMER dans templates/dashboard/list.php :
   - Les anciennes actions documents PHP inline
```

### 🔧 Snippets de code de base

#### **Contrôleur de téléchargement sécurisé**

```php
public static function ajax_secure_download() {
    parent::verify_access('pc_document_download', 'nonce');

    $reservation_id = (int) $_POST['reservation_id'];
    $filename = sanitize_file_name($_POST['filename']);

    // Validation propriétaire
    $resa = PCR_Reservation::get_by_id($reservation_id);
    if (!$resa || !current_user_can('edit_post', $reservation_id)) {
        wp_send_json_error(['message' => 'Accès refusé à cette réservation']);
    }

    // Validation chemin sécurisé
    $upload_dir = wp_upload_dir();
    $expected_dir = $upload_dir['basedir'] . '/pc-reservation/documents/' . $reservation_id;
    $file_path = $expected_dir . '/' . basename($filename);

    if (!file_exists($file_path) || strpos(realpath($file_path), $expected_dir) !== 0) {
        wp_send_json_error(['message' => 'Fichier introuvable ou accès interdit']);
    }

    // Audit log
    error_log("[PC-DOCS] Download - User:" . get_current_user_id() . " - File:" . $filename);

    // Stream
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($file_path);
    exit;
}
```

#### **Méthode de génération dans le store Pinia**

```javascript
const generateDocument = async (templateId, reservationId, options = {}) => {
  isGenerating.value = true;
  progress.value = 0;

  try {
    const formData = new FormData();
    formData.append("action", "pc_generate_document");
    formData.append("template_id", templateId);
    formData.append("reservation_id", reservationId);
    formData.append("force", options.forceRegenerate ? "true" : "false");
    formData.append("nonce", window.pcReservationNonce);

    const response = await fetch(window.ajaxUrl, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      // Recharger la liste des documents
      await loadDocumentsForReservation(reservationId);

      // Auto-téléchargement ou aperçu
      if (options.autoPreview) {
        previewDocument(result.data.url);
      }

      return result.data;
    } else {
      throw new Error(result.data?.message || "Erreur de génération");
    }
  } catch (error) {
    console.error("[DocumentStore] Generation error:", error);
    throw error;
  } finally {
    isGenerating.value = false;
    progress.value = 0;
  }
};
```

---

## 🎯 Résumé des livrables

### ✅ Fichiers à créer

- `src/stores/document-store.js`
- `src/services/document-api.js`
- `src/components/documents/DocumentsManager.vue`
- `src/components/documents/DocumentTemplateSelector.vue`
- `src/components/documents/DocumentGenerationPanel.vue`
- `src/components/documents/DocumentsList.vue`
- `src/components/documents/PdfPreviewModal.vue`
- `includes/ajax/controllers/class-secure-document-controller.php`
- `uploads/pc-reservation/.htaccess`

### ❌ Fichiers à supprimer

- `assets/js/modules/documents.js`
- Références documents dans `templates/dashboard/popups.php`
- Références documents dans `dashboard-core.js`

### 🔧 Fichiers à modifier

- `src/components/dashboard/ReservationModal.vue` (remplacer placeholder)
- `includes/services/document/renderers/*.php` (échappement sécurisé)
- `includes/ajax/controllers/class-ajax-router.php` (nouveaux endpoints)

---

## ⏱️ Estimation temporelle totale : **12-15 heures**

**Phase 1 :** Stores & Services (3h)  
**Phase 2 :** Composants Vue (5h)  
**Phase 3 :** Backend sécurisé (4h)  
**Phase 4 :** Intégration (2h)  
**Phase 5 :** Nettoyage (1h)

---

_📋 Ce rapport constitue la feuille de route complète pour migrer le système de documents vers l'architecture Vue 3 + Pinia en conservant toute la logique métier existante et en renforçant significativement la sécurité._
