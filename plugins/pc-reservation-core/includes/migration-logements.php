<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Script de migration temporaire : Copie les données ACF vers le format Natif.
 * À lancer en allant sur : ton-site.local/wp-admin/?run_pc_migration=logements
 */
add_action('admin_init', function () {
    // Sécurité : Uniquement si on demande la migration et qu'on est admin
    if (!isset($_GET['run_pc_migration']) || $_GET['run_pc_migration'] !== 'logements') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé.');
    }

    echo '<h1>🚀 Lancement de la Migration des Logements...</h1>';

    $query = new WP_Query([
        'post_type'      => ['villa', 'appartement'],
        'posts_per_page' => -1, // On prend TOUS les logements
        'post_status'    => 'any'
    ]);

    $manager = class_exists('PCR_Field_Manager') ? PCR_Field_Manager::init() : null;
    $config  = class_exists('PCR_Housing_Config') ? PCR_Housing_Config::get_instance() : null;

    if (!$manager || !$config || !function_exists('get_field')) {
        wp_die('Erreur : Classes manquantes ou ACF désactivé (ACF doit être actif pour lire les anciennes données).');
    }

    $all_groups = $manager->get_field_groups();
    $special_keys = $config->get_special_meta_keys();

    $count = 0;

    foreach ($query->posts as $post) {
        echo "<h3>Migration de : {$post->post_title} (ID: {$post->ID})</h3>";

        // 1. MIGRATION DES CHAMPS SIMPLES
        foreach ($all_groups as $group_id => $group_config) {
            if (in_array($post->post_type, $group_config['post_types'])) {
                foreach ($group_config['fields'] as $field_key => $field_config) {

                    // On vérifie si c'est une vieille clé ACF bizarre (avec tirets)
                    $acf_key = $special_keys[$field_key] ?? $field_key;

                    // On lit l'ancienne valeur depuis ACF
                    $old_value = get_field($acf_key, $post->ID);

                    if ($old_value !== null && $old_value !== '') {
                        // On sauvegarde dans le nouveau système propre
                        $clean_value = is_array($old_value) ? wp_unslash($old_value) : wp_unslash(sanitize_text_field($old_value));
                        $manager->save_native_field($field_key, $post->ID, $clean_value);
                    }
                }
            }
        }

        // 2. MIGRATION DES TARIFS (Répéteurs)
        $acf_seasons = get_field('pc_season_blocks', $post->ID);
        $acf_promos  = get_field('pc_promo_blocks', $post->ID);

        // On crée un faux objet JSON comme le ferait Vue.js pour utiliser ta fonction de sauvegarde existante
        $rates_data = [];
        if (!empty($acf_seasons)) {
            // Conversion du format ACF vers le format attendu par ton repository
            $rates_data['seasons'] = array_map(function ($s) {
                return [
                    'name' => $s['season_name'],
                    'price' => $s['season_price'],
                    'note' => $s['season_note'],
                    'minNights' => $s['season_min_nights'],
                    'guestFee' => $s['season_extra_guest_fee'],
                    'guestFrom' => $s['season_extra_guest_from'],
                    'periods' => array_map(function ($p) {
                        return ['start' => $p['date_from'], 'end' => $p['date_to']];
                    }, $s['season_periods'] ?? [])
                ];
            }, $acf_seasons);
        }

        if (!empty($acf_promos)) {
            $rates_data['promos'] = array_map(function ($p) {
                return [
                    'name' => $p['nom_de_la_promotion'],
                    'promo_type' => $p['promo_type'],
                    'value' => $p['promo_value'],
                    'validUntil' => $p['promo_valid_until'],
                    'periods' => array_map(function ($per) {
                        return ['start' => $per['date_from'], 'end' => $per['date_to']];
                    }, $p['promo_periods'] ?? [])
                ];
            }, $acf_promos);
        }

        if (!empty($rates_data)) {
            // Appel direct à ta fonction pour sauvegarder nativement les tarifs
            PCR_Housing_Repository::save_rates($post->ID, $rates_data);
            echo "<p>✅ Tarifs migrés.</p>";
        }

        echo "<p>✅ Champs simples migrés.</p>";
        $count++;
    }

    echo "<h2>🎉 Terminé ! $count logements ont été migrés avec succès vers le format natif.</h2>";
    die(); // On arrête le chargement de la page admin pour bien voir le résultat
});
