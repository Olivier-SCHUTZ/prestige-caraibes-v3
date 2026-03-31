<?php

/**
 * Gestionnaire des données structurées (Schema JSON-LD) pour la fiche Destination
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Destination_Schema_Manager
{
    /**
     * Enregistre les hooks
     */
    public function register()
    {
        add_action('wp_head', [$this, 'output_faq_schema'], 30);
    }

    /**
     * Génère et affiche le Schema JSON-LD pour la FAQ
     */
    public function output_faq_schema()
    {
        if (!is_singular('destination')) {
            return;
        }

        if (!class_exists('PCR_Fields')) {
            return;
        }

        $pid = get_queried_object_id();
        $faqs = (array) PCR_Fields::get('dest_faq', $pid);

        if (empty($faqs)) {
            return;
        }

        $items = [];
        foreach ($faqs as $row) {
            $q = trim(wp_strip_all_tags($row['question'] ?? ''));
            $a = trim(wp_strip_all_tags($row['reponse'] ?? ''));

            if ($q && $a) {
                $items[] = [
                    '@type' => 'Question',
                    'name' => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $a
                    ]
                ];
            }
        }

        if (empty($items)) {
            return;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}
