<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Script de migration temporaire : Copie les données ACF vers le format Natif (Destinations).
 * À lancer en allant sur : ton-site.local/wp-admin/?run_pc_migration=destinations
 */
add_action('admin_init', function () {
    // Sécurité : Uniquement si on demande la migration et qu'on est admin
    if (!isset($_GET['run_pc_migration']) || $_GET['run_pc_migration'] !== 'destinations') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Accès refusé.');
    }

    echo '<h1>🌴 Lancement de la Migration des Destinations...</h1>';

    $query = new WP_Query([
        'post_type'      => 'destination',
        'posts_per_page' => -1, // On prend TOUTES les destinations
        'post_status'    => 'any'
    ]);

    $manager = class_exists('PCR_Field_Manager') ? PCR_Field_Manager::init() : null;
    $config  = class_exists('PCR_Destination_Config') ? PCR_Destination_Config::get_instance() : null;

    if (!$manager || !$config || !function_exists('get_field')) {
        wp_die('Erreur : Classes manquantes ou ACF désactivé (ACF doit être actif pour lire les anciennes données).');
    }

    $all_groups = $manager->get_field_groups();
    $mapped_fields = $config->get_mapped_fields();

    $count = 0;

    foreach ($query->posts as $post) {
        echo "<h3>Migration de : {$post->post_title} (ID: {$post->ID})</h3>";

        // 1. MIGRATION DES CHAMPS SIMPLES ET RÉPÉTEURS
        foreach ($all_groups as $group_id => $group_config) {
            if (in_array($post->post_type, $group_config['post_types'])) {
                foreach ($group_config['fields'] as $field_key => $field_config) {

                    // On vérifie si une ancienne clé méta spécifique est mappée dans la config
                    $acf_key = $mapped_fields[$field_key] ?? $field_key;

                    // On lit l'ancienne valeur depuis ACF
                    $old_value = get_field($acf_key, $post->ID);

                    if ($old_value !== null && $old_value !== false && $old_value !== '') {
                        // Nettoyage : Si c'est un tableau (comme les répéteurs dest_infos, dest_faq, etc.), on unslash simplement
                        // Si c'est une string simple, on sanitize
                        if (is_array($old_value)) {
                            $clean_value = wp_unslash($old_value);
                        } else {
                            // On utilise wp_kses_post pour autoriser le HTML dans les textareas, ou sanitize_text_field pour le reste
                            $clean_value = ($field_config['type'] === 'textarea')
                                ? wp_kses_post(wp_unslash($old_value))
                                : sanitize_text_field(wp_unslash($old_value));
                        }

                        // On sauvegarde dans le nouveau système natif
                        $manager->save_native_field($field_key, $post->ID, $clean_value);
                    }
                }
            }
        }

        echo "<p>✅ Champs migrés.</p>";
        $count++;
    }

    echo "<h2>🎉 Terminé ! $count destinations ont été migrées avec succès vers le format natif.</h2>";
    die(); // On arrête le chargement de la page admin pour bien voir le résultat
});
