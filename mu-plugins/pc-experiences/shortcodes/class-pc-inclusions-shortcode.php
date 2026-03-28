<?php

/**
 * Shortcode : [experience_inclusions]
 * Affiche les inclusions, exclusions, recommandations et accessibilité.
 *
 * @package PC_Experiences
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

class PC_Experience_Inclusions_Shortcode extends PC_Experience_Shortcode_Base
{

    /**
     * Nom du shortcode.
     *
     * @var string
     */
    protected $shortcode_name = 'experience_inclusions';

    /**
     * Rendu HTML du shortcode.
     *
     * @param array $atts Attributs.
     */
    protected function render(array $atts = []): void
    {
        $post_id = $this->get_experience_id();
        if (!$post_id) return;

        // --- 1. DÉCODEUR V3 & TRADUCTEUR ---
        $format_choices = function ($raw) {
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) $raw = $decoded;
            }

            // Dictionnaire pour les accents et formulations spécifiques (historique ACF)
            $dictionary = [
                'creme_solaire'      => 'Crème solaire',
                'maillot_de_bain'    => 'Maillot de bain',
                'eau_collations'     => 'Eau et collations',
                'appareil_photo'     => 'Appareil photo',
                'non_accessible_pmr' => 'Non accessible PMR',
                'accessible_pmr'     => 'Accessible PMR'
            ];

            if (is_array($raw)) {
                $flat = array_map(function ($item) use ($dictionary) {
                    // Si c'est un format Vue.js V3 propre
                    if (is_array($item)) {
                        return $item['label'] ?? $item['value'] ?? '';
                    }
                    // Si c'est une ancienne clé ACF : on vérifie le dictionnaire
                    if (isset($dictionary[$item])) {
                        return $dictionary[$item];
                    }
                    // Sinon, on "humanise" la clé (ex: "tee_shirt" -> "Tee shirt")
                    return ucfirst(str_replace(['_', '-'], ' ', $item));
                }, $raw);
                return array_filter($flat);
            }

            // Sécurité pour une valeur unique
            if ($raw) {
                return isset($dictionary[$raw]) ? [$dictionary[$raw]] : [ucfirst(str_replace(['_', '-'], ' ', $raw))];
            }
            return [];
        };

        // --- 2. RÉCUPÉRATION DES DONNÉES ---
        $prix_comprend = trim((string) PCR_Fields::get('exp_prix_comprend', $post_id));
        $prix_ne_comprend_pas = trim((string) PCR_Fields::get('exp_prix_ne_comprend_pas', $post_id));

        $a_prevoir_arr = $format_choices(PCR_Fields::get('exp_a_prevoir', $post_id));
        $accessibilite_arr = $format_choices(PCR_Fields::get('exp_accessibilite', $post_id));

        // Si tout est vide, on arrête le rendu
        if (empty($prix_comprend) && empty($prix_ne_comprend_pas) && empty($a_prevoir_arr) && empty($accessibilite_arr)) {
            return;
        }

        // --- 3. RENDU HTML (STYLE "LOGEMENT" pc-equipements) ---
        ob_start(); ?>

        <div class="pc-exp-inclusions-wrapper pc-equipements-wrapper pc-regles-wrapper">
            <div class="pc-equipements-grid">

                <?php if (!empty($prix_comprend)) : ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </span>
                            <h4 class="pc-equip-title">Le prix comprend</h4>
                        </div>
                        <div class="pc-equip-content pc-v3-content-raw pc-list-check">
                            <?php echo wpautop($prix_comprend); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($prix_ne_comprend_pas)) : ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                            </span>
                            <h4 class="pc-equip-title">Le prix ne comprend pas</h4>
                        </div>
                        <div class="pc-equip-content pc-v3-content-raw pc-list-cross">
                            <?php echo wpautop($prix_ne_comprend_pas); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($a_prevoir_arr)) : ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="5" width="16" height="16" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="5"></line>
                                    <line x1="8" y1="2" x2="8" y2="5"></line>
                                    <line x1="4" y1="11" x2="20" y2="11"></line>
                                    <path d="M10 16h4"></path>
                                </svg>
                            </span>
                            <h4 class="pc-equip-title">À prévoir</h4>
                        </div>
                        <ul class="pc-equip-list">
                            <?php foreach ($a_prevoir_arr as $item): ?>
                                <li><?php echo esc_html($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($accessibilite_arr)) : ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="5" r="2"></circle>
                                    <path d="M19 10H5"></path>
                                    <path d="M12 10v12"></path>
                                    <path d="M8 22l4-10 4 10"></path>
                                </svg>
                            </span>
                            <h4 class="pc-equip-title">Accessibilité</h4>
                        </div>
                        <ul class="pc-equip-list">
                            <?php foreach ($accessibilite_arr as $item): ?>
                                <li><?php echo esc_html($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <style>
            .pc-exp-inclusions-wrapper {
                margin: 3rem 0;
                font-family: var(--pc-font-family-body, system-ui, sans-serif);
            }

            .pc-equipements-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            @media (min-width: 768px) {
                .pc-equipements-grid {
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                }
            }

            .pc-equip-box {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: var(--pc-border-radius, 16px);
                padding: 1.5rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                height: 100%;
            }

            .pc-equip-header {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }

            .pc-equip-icon {
                color: var(--pc-primary, #0e2b5c);
                width: 24px;
                height: 24px;
                display: flex;
            }

            .pc-equip-title {
                font-size: 1.15rem;
                font-weight: 600;
                color: #1e293b;
                margin: 0;
            }

            /* Listes Standards (À prévoir / Accessibilité) */
            .pc-equip-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .pc-equip-list li {
                position: relative;
                padding-left: 1.5rem;
                margin-bottom: 0.75rem;
                color: #475569;
                line-height: 1.6;
            }

            .pc-equip-list li::before {
                content: "•";
                position: absolute;
                left: 0;
                color: var(--pc-primary, #0e2b5c);
                font-weight: bold;
                font-size: 1.2rem;
                line-height: 1.2;
            }

            /* Contenu WYSIWYG (Le prix comprend / ne comprend pas) */
            .pc-equip-content {
                color: #475569;
                line-height: 1.6;
            }

            .pc-equip-content p {
                margin-bottom: 0.75rem;
            }

            .pc-equip-content ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .pc-equip-content li {
                position: relative;
                padding-left: 1.75rem;
                margin-bottom: 0.75rem;
            }

            /* Puces spécifiques "Comprend" (Vert) */
            .pc-list-check li::before {
                content: "✓";
                position: absolute;
                left: 0;
                top: 0;
                color: #10b981;
                font-weight: bold;
                font-size: 1.2rem;
            }

            /* Puces spécifiques "Ne comprend pas" (Rouge) */
            .pc-list-cross li::before {
                content: "✕";
                position: absolute;
                left: 0;
                top: 0;
                color: #ef4444;
                font-weight: bold;
                font-size: 1.2rem;
            }
        </style>

<?php echo ob_get_clean();
    }
}
