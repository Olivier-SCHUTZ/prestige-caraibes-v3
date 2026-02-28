<?php

/**
 * Helper : Traitement et formatage des champs (ACF et autres) pour les Expériences.
 * * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité : empêche l'accès direct au fichier
}

class PC_Experience_Field_Helper
{

    /**
     * Résout le libellé du type de tarif (supporte "custom").
     * Anciennement pc_exp_type_label().
     *
     * @param array $row     Ligne du répéteur exp_types_de_tarifs
     * @param array $choices Choices ACF (value => label) du select exp_type
     * @return string        Le libellé formaté
     */
    public static function resolve_pricing_type_label(array $row, array $choices = []): string
    {
        $type_value   = isset($row['exp_type']) ? (string)$row['exp_type'] : '';
        $custom_label = isset($row['exp_type_custom']) ? trim((string)$row['exp_type_custom']) : '';

        // Cas "Autre (personnalisé)" avec texte saisi
        if ($type_value === 'custom' && $custom_label !== '') {
            $label = wp_strip_all_tags($custom_label);
            $label = mb_substr($label, 0, 100);
            return $label !== '' ? $label : __('Type personnalisé', 'pc');
        }

        // Libellé depuis choices
        if ($type_value !== '' && isset($choices[$type_value])) {
            return (string)$choices[$type_value];
        }

        // Fallback lisible
        return $type_value !== '' ? ucwords(str_replace(['_', '-'], ' ', $type_value)) : __('Type', 'pc');
    }
}
