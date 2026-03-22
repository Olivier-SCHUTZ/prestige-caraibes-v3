<?php

/**
 * Wrapper statique pour la récupération des champs.
 * Assure la transition en douceur entre ACF Pro et le système natif.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PCR_Fields
{

    /**
     * Récupère la valeur d'un champ avec Fallback automatique sur ACF.
     * C'est notre pont anti-régression !
     * * @param string $key L'identifiant du champ.
     * @param int|bool $post_id L'ID du post (optionnel).
     * @param mixed $default Valeur de repli si rien n'est trouvé.
     * @return mixed
     */
    public static function get($key, $post_id = false, $default = null)
    {
        if (! $post_id) {
            $post_id = get_the_ID();
        }

        // 1. Tenter de récupérer la valeur via notre nouveau système natif
        if (class_exists('PCR_Field_Manager')) {
            $native_value = PCR_Field_Manager::init()->get_native_field($key, $post_id);

            // Si on a trouvé une valeur native (non nulle), on la retourne
            if ($native_value !== null) {
                return $native_value;
            }
        }

        // 2. FALLBACK : Si rien n'est trouvé en natif, on utilise ACF Pro
        if (function_exists('get_field')) {
            $acf_value = get_field($key, $post_id);

            // ACF peut retourner false ou '' si vide, on s'assure de bien intercepter
            if ($acf_value !== null && $acf_value !== '' && $acf_value !== false) {
                return $acf_value;
            }
        }

        // 3. Si vraiment rien n'est trouvé nulle part, on retourne la valeur par défaut
        return $default;
    }
}
