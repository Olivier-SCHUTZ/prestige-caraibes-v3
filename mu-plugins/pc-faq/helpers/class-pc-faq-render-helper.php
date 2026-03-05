<?php

/**
 * Helper de rendu pour les FAQ
 * Gère la génération du HTML (Accordéons) et la compatibilité des clés ACF
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_FAQ_Render_Helper
{
    /**
     * Génère le HTML de l'accordéon FAQ à partir des lignes ACF.
     * Fusionne la logique des anciens shortcodes pour une non-régression totale.
     *
     * @param array|object $rows Données du repeater ACF.
     * @param array $args Arguments optionnels (title, classes, open_first).
     * @return string HTML de l'accordéon.
     */
    public static function render_accordion($rows, $args = [])
    {
        if (!is_array($rows) || empty($rows)) {
            return '';
        }

        // Valeurs par défaut unifiées
        $defaults = [
            'title'      => '',
            'classes'    => 'pc-faq-accordion',
            'open_first' => false,
        ];
        $args = wp_parse_args($args, $defaults);

        // Les clés possibles (rétrocompatibilité avec l'ancien système "fuzzy match")
        $q_keys = ['question', 'exp_question', 'dest_question', 'log_question', 'faq_question'];
        $a_keys = ['reponse', 'exp_reponse', 'dest_reponse', 'log_reponse', 'answer', 'exp_answer', 'dest_answer', 'log_answer', 'faq_reponse', 'faq_answer'];

        ob_start();

        // Titre optionnel (utilisé par destination, experience, logement)
        if ($args['title'] !== '') {
            echo '<h3 class="pc-faq-title">' . esc_html($args['title']) . '</h3>';
        }

        // Nettoyage et application des classes CSS
        $classes = preg_replace('/[^a-z0-9_\- ]/i', '', $args['classes']);
        echo '<div class="' . esc_attr($classes) . '">';

        $index = 0;
        foreach ($rows as $row) {
            $q_raw = self::read_key($row, $q_keys);
            $a_raw = self::read_key($row, $a_keys);

            // Nettoyage de sécurité
            $q = trim(wp_strip_all_tags((string)$q_raw, true));
            $a = trim((string)wp_kses_post($a_raw));

            if ($q === '' || $a === '') {
                continue;
            }

            // Ajout des paragraphes automatiques (unification du comportement)
            $a = wpautop($a);

            // Gestion de l'ouverture automatique du premier élément
            $open_attr = ($args['open_first'] && $index === 0) ? ' open' : '';

            echo '<details class="pc-faq-item"' . $open_attr . '>';
            echo '<summary class="pc-faq-q">' . esc_html($q) . '</summary>';
            echo '<div class="pc-faq-a">' . $a . '</div>';
            echo '</details>';

            $index++;
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Helper interne : lit une valeur depuis un array|object, sinon cherche un nom "proche".
     * Reproduction exacte de l'ancienne logique pour éviter toute régression.
     */
    private static function read_key($row, $candidates)
    {
        // 1. Accès direct (Array)
        if (is_array($row)) {
            foreach ($candidates as $k) {
                if (isset($row[$k]) && $row[$k] !== '') {
                    return (string)$row[$k];
                }
            }
        }
        // 2. Accès direct (Object)
        elseif (is_object($row)) {
            foreach ($candidates as $k) {
                if (isset($row->{$k}) && $row->{$k} !== '') {
                    return (string)$row->{$k};
                }
            }
        }

        // 3. Recherche "large" par similarité (ex: my_exp_question_fr)
        $kv = is_array($row) ? $row : (is_object($row) ? get_object_vars($row) : []);
        foreach ($kv as $k => $v) {
            if ($v === '') continue;

            $kn = strtolower(remove_accents((string)$k));
            foreach ($candidates as $cand) {
                $cn = strtolower(remove_accents((string)$cand));
                if ($cn !== '' && strpos($kn, $cn) !== false) {
                    return (string)$v;
                }
            }
        }

        return '';
    }
}
