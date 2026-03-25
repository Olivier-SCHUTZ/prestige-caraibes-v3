<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pont d'intégration entre PCR_Fields et Elementor
 * Permet d'utiliser nos champs natifs dans les widgets d'images Elementor.
 */
add_action('elementor/dynamic_tags/register', function ($dynamic_tags_manager) {

    class PCR_Elementor_Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag
    {
        public function get_name()
        {
            return 'pcr-image-tag';
        }

        public function get_title()
        {
            return '📸 Image PCR Native';
        }

        public function get_group()
        {
            return 'post'; // Rangement dans la catégorie Post/Publication
        }

        public function get_categories()
        {
            // Autorisation magique : Elementor acceptera ce tag POUR LES IMAGES et LES FONDS
            return [\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY];
        }

        protected function register_controls()
        {
            // Ajoute un champ texte pour que tu puisses taper ta clé
            $this->add_control(
                'pcr_field_key',
                [
                    'label' => 'Clé du champ image',
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'placeholder' => 'ex: hero_desktop_url',
                ]
            );
        }

        public function get_value(array $options = [])
        {
            $key = $this->get_settings('pcr_field_key');
            if (empty($key)) {
                return [];
            }

            $post_id = get_the_ID();
            if (!$post_id) {
                return [];
            }

            // 🚀 Appel à notre Wrapper Anti-Régression
            $image_data = PCR_Fields::get($key, $post_id);

            if (empty($image_data)) {
                return [];
            }

            $image_id = 0;
            $image_url = '';

            // Format 1 : Le système natif renvoie un ID (Nombre)
            if (is_numeric($image_data)) {
                $image_id = (int) $image_data;
                $image_url = wp_get_attachment_image_url($image_id, 'full');
            }
            // Format 2 : Le système renvoie une URL directe
            elseif (is_string($image_data)) {
                $image_url = $image_data;
                $image_id = attachment_url_to_postid($image_url);
            }
            // Format 3 : Fallback (Le vieux format tableau d'ACF)
            elseif (is_array($image_data)) {
                $image_id = $image_data['ID'] ?? ($image_data['id'] ?? 0);
                $image_url = $image_data['url'] ?? '';
            }

            // On retourne la donnée formatée exactement comme Elementor l'exige
            return [
                'id' => $image_id,
                'url' => $image_url,
            ];
        }
    }

    // On enregistre notre nouveau tag
    $dynamic_tags_manager->register(new PCR_Elementor_Image_Tag());
});
