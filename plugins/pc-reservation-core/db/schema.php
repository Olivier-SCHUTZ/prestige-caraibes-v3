<?php
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Gère la création / mise à jour des tables personnalisées.
 */
class PCR_Reservation_Schema
{
  public static function install()
  {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $table_res = $wpdb->prefix . 'pc_reservations';
    $table_pay = $wpdb->prefix . 'pc_payments';
    $table_msg = $wpdb->prefix . 'pc_messages';
    $table_unv = $wpdb->prefix . 'pc_unavailabilities';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // TABLE RESERVATIONS
    $sql_res = "CREATE TABLE {$table_res} (
          id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          type VARCHAR(20) NOT NULL,
          item_id BIGINT(20) UNSIGNED NOT NULL,
          mode_reservation VARCHAR(20) DEFAULT 'demande',
          origine VARCHAR(50) DEFAULT 'site',
          source VARCHAR(50) DEFAULT 'direct',
          ref_externe VARCHAR(100) DEFAULT NULL,
          type_flux VARCHAR(20) NOT NULL DEFAULT 'reservation',
          numero_devis VARCHAR(50) DEFAULT NULL,

          date_arrivee DATE DEFAULT NULL,
          date_depart DATE DEFAULT NULL,
          date_experience DATE DEFAULT NULL,
          experience_tarif_type VARCHAR(50) DEFAULT NULL,

          adultes INT(11) DEFAULT 0,
          enfants INT(11) DEFAULT 0,
          bebes INT(11) DEFAULT 0,
          infos_personnes TEXT DEFAULT NULL,

          civilite VARCHAR(10) DEFAULT NULL,
          prenom VARCHAR(100) DEFAULT NULL,
          nom VARCHAR(100) DEFAULT NULL,
          email VARCHAR(191) DEFAULT NULL,
          telephone VARCHAR(50) DEFAULT NULL,
          langue VARCHAR(10) DEFAULT 'fr',
          commentaire_client TEXT DEFAULT NULL,

          devise VARCHAR(3) DEFAULT 'EUR',
          montant_total DECIMAL(10,2) DEFAULT 0,
          montant_acompte DECIMAL(10,2) DEFAULT 0,
          montant_solde DECIMAL(10,2) DEFAULT 0,
          conditions_paiement VARCHAR(255) DEFAULT NULL,
          detail_tarif LONGTEXT DEFAULT NULL,

          caution_montant DECIMAL(10,2) DEFAULT 0,
          caution_mode VARCHAR(20) DEFAULT 'aucune',
          caution_statut VARCHAR(20) DEFAULT 'non_demande',
          caution_reference VARCHAR(191) DEFAULT NULL,
          caution_date_demande DATETIME DEFAULT NULL,
          caution_date_validation DATETIME DEFAULT NULL,
          caution_date_liberation DATETIME DEFAULT NULL,

          statut_reservation VARCHAR(30) NOT NULL DEFAULT 'en_attente_traitement',
          statut_paiement VARCHAR(30) NOT NULL DEFAULT 'non_paye',
          commentaire_interne TEXT DEFAULT NULL,
          notes_internes TEXT DEFAULT NULL,
          snapshot_politique LONGTEXT DEFAULT NULL,
          tags_internes TEXT DEFAULT NULL,
          user_responsable_id BIGINT(20) UNSIGNED DEFAULT NULL,

          date_creation DATETIME NOT NULL,
          date_maj DATETIME NOT NULL,
          date_annulation DATETIME DEFAULT NULL,

          PRIMARY KEY  (id),
          KEY idx_numero_devis (numero_devis),
          KEY idx_item_dates (item_id, date_arrivee, date_depart),
          KEY idx_type_date (type, date_creation),
          KEY idx_email (email)
        ) {$charset_collate};";

    // TABLE PAYMENTS
    $sql_pay = "CREATE TABLE {$table_pay} (
          id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          reservation_id BIGINT(20) UNSIGNED NOT NULL,
          type_paiement VARCHAR(20) DEFAULT 'acompte',
          methode VARCHAR(20) DEFAULT 'stripe',
          montant DECIMAL(10,2) DEFAULT 0,
          devise VARCHAR(3) DEFAULT 'EUR',
          statut VARCHAR(30) DEFAULT 'en_attente',
          date_creation DATETIME NOT NULL,
          date_echeance DATETIME DEFAULT NULL,
          date_paiement DATETIME DEFAULT NULL,
          date_annulation DATETIME DEFAULT NULL,
          gateway VARCHAR(20) DEFAULT NULL,
          gateway_reference VARCHAR(191) DEFAULT NULL,
          gateway_status VARCHAR(50) DEFAULT NULL,
          url_paiement TEXT DEFAULT NULL,
          raw_response LONGTEXT DEFAULT NULL,
          note_interne TEXT DEFAULT NULL,
          user_id BIGINT(20) UNSIGNED DEFAULT NULL,
          date_maj DATETIME NOT NULL,
          PRIMARY KEY  (id),
          KEY idx_reservation (reservation_id),
          KEY idx_statut_date (statut, date_creation)
        ) {$charset_collate};";

    // TABLE MESSAGES - ✨ REFONTE CHANNEL MANAGER
    $sql_msg = "CREATE TABLE {$table_msg} (
          id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          reservation_id BIGINT(20) UNSIGNED NOT NULL,
          conversation_id BIGINT(20) UNSIGNED NOT NULL,
          
          /* === CANAUX & SOURCES === */
          canal VARCHAR(20) DEFAULT 'email',
          channel_source VARCHAR(50) DEFAULT 'email',
          external_id VARCHAR(191) DEFAULT NULL,
          
          /* === ACTEURS & DIRECTION === */
          direction VARCHAR(20) DEFAULT 'sortant',
          sender_type ENUM('host', 'guest', 'system') DEFAULT 'host',
          
          /* === CONTENU === */
          type VARCHAR(50) DEFAULT 'automatique',
          sujet VARCHAR(255) DEFAULT NULL,
          corps LONGTEXT DEFAULT NULL,
          template_code VARCHAR(100) DEFAULT NULL,
          
          /* === DESTINATAIRES (Compatibilité) === */
          dest_nom VARCHAR(191) DEFAULT NULL,
          dest_email VARCHAR(191) DEFAULT NULL,
          dest_tel VARCHAR(50) DEFAULT NULL,
          exp_nom VARCHAR(191) DEFAULT NULL,
          exp_email VARCHAR(191) DEFAULT NULL,
          exp_tel VARCHAR(50) DEFAULT NULL,
          
          /* === STATUTS & SUIVI === */
          statut_envoi VARCHAR(20) DEFAULT 'brouillon',
          read_at DATETIME DEFAULT NULL,
          delivered_at DATETIME DEFAULT NULL,
          
          /* === DATES === */
          date_creation DATETIME NOT NULL,
          date_envoi DATETIME DEFAULT NULL,
          date_maj DATETIME NOT NULL,
          
          /* === INTÉGRATIONS EXTERNES === */
          provider_message_id VARCHAR(191) DEFAULT NULL,
          metadata LONGTEXT DEFAULT NULL,
          
          /* === COMPATIBILITÉ ASCENDANTE === */
          est_automatique TINYINT(1) DEFAULT 0,
          
          /* === TRAÇABILITÉ === */
          user_id BIGINT(20) UNSIGNED DEFAULT NULL,
          note_interne TEXT DEFAULT NULL,
          
          PRIMARY KEY  (id),
          KEY idx_reservation (reservation_id),
          KEY idx_conversation (conversation_id),
          KEY idx_canal_date (canal, date_creation),
          KEY idx_channel_source (channel_source),
          KEY idx_read_status (read_at),
          KEY idx_sender_type (sender_type),
          KEY idx_external_id (external_id)
        ) {$charset_collate};";

    // TABLE INDISPONIBILITES MANUELLES
    $sql_unv = "CREATE TABLE {$table_unv} (
          id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
          item_id BIGINT(20) UNSIGNED NOT NULL,
          date_debut DATE NOT NULL,
          date_fin DATE NOT NULL,
          type_source VARCHAR(20) DEFAULT 'manuel',
          reservation_id BIGINT(20) UNSIGNED DEFAULT NULL,
          motif VARCHAR(255) DEFAULT NULL,
          date_creation DATETIME NOT NULL,
          date_maj DATETIME NOT NULL,
          user_id BIGINT(20) UNSIGNED DEFAULT NULL,
          PRIMARY KEY  (id),
          KEY idx_item_dates (item_id, date_debut, date_fin)
        ) {$charset_collate};";

    dbDelta($sql_res);
    dbDelta($sql_pay);
    dbDelta($sql_msg);
    dbDelta($sql_unv);

    // ✨ MIGRATION CHANNEL MANAGER : Mise à jour des messages existants
    self::migrate_existing_messages();
  }

  /**
   * ✨ MIGRATION DESIGN-AWARE : Met à jour les messages existants avec les nouvelles colonnes
   * Garantit la compatibilité ascendante parfaite
   */
  private static function migrate_existing_messages()
  {
    global $wpdb;
    $table = $wpdb->prefix . 'pc_messages';

    // 1. Vérifier si la migration a déjà été effectuée
    $migration_key = 'pc_messages_channel_manager_migration_v1';
    if (get_option($migration_key)) {
      return; // Déjà migrée
    }

    // 2. Mise à jour des messages existants avec valeurs par défaut Design-Aware
    $updated = $wpdb->query("
      UPDATE {$table} 
      SET 
        conversation_id = COALESCE(conversation_id, reservation_id),
        channel_source = COALESCE(channel_source, 'email'),
        sender_type = CASE 
          WHEN sender_type IS NULL THEN 'host' 
          ELSE sender_type 
        END
      WHERE conversation_id IS NULL 
         OR channel_source IS NULL 
         OR sender_type IS NULL
    ");

    // 3. Marquer la migration comme terminée
    add_option($migration_key, current_time('mysql'));

    // 4. Log pour debug si nécessaire
    if ($updated > 0) {
      error_log("[PC CHANNEL MANAGER] Migration réussie : {$updated} messages mis à jour.");
    }
  }
}
