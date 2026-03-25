<?php

/**
 * Composant Shortcode : Règles, Politiques et Notes du Logement [pc_politique]
 * Réplique le design de [pc_equipements] pour une harmonie visuelle parfaite.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Politique_Shortcode extends PC_Shortcode_Base
{
    protected $tag = 'pc_politique';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $post_id = get_the_ID();

        if (!$post_id) {
            return '';
        }

        $regles = $this->get_formatted_rules($post_id);

        if (empty($regles)) {
            return '';
        }

        // On réutilise EXACTEMENT la structure HTML et les classes de pc-equipements
        ob_start(); ?>
        <div class="pc-equipements-wrapper pc-regles-wrapper">
            <div class="pc-equipements-grid">
                <?php foreach ($regles as $regle): ?>
                    <div class="pc-equip-box">
                        <div class="pc-equip-header">
                            <span class="pc-equip-icon" aria-hidden="true"><?php echo $regle['svg']; ?></span>
                            <h4 class="pc-equip-title"><?php echo esc_html($regle['label']); ?></h4>
                        </div>
                        <ul class="pc-equip-list">
                            <?php foreach ($regle['items'] as $item): ?>
                                <li><?php echo esc_html($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php return ob_get_clean();
    }

    protected function enqueue_assets()
    {
        // Les styles sont déjà gérés par pc-equipements.css, on ne charge rien de plus !
        return;
    }

    /**
     * Helper : Récupère et formate les règles avec un ton légal et professionnel
     */
    private function get_formatted_rules($post_id)
    {
        $results = [];

        // --- RÉCUPÉRATION DES DONNÉES (Strictement via PCR_Fields) ---
        $pay_mode       = PCR_Fields::get('pc_pay_mode', $post_id) ?: 'acompte_plus_solde';
        $deposit_type   = PCR_Fields::get('pc_deposit_type', $post_id) ?: 'pourcentage';
        $deposit_val    = (float) PCR_Fields::get('pc_deposit_value', $post_id);
        $delay_days     = (int) PCR_Fields::get('pc_balance_delay_days', $post_id);

        $caution_type   = PCR_Fields::get('pc_caution_type', $post_id) ?: 'aucune';
        $caution_amount = (float) PCR_Fields::get('pc_caution_amount', $post_id);

        // Nettoyage pour les comparaisons
        $pay_mode     = is_array($pay_mode) ? ($pay_mode[0] ?? '') : $pay_mode;
        $caution_type = is_array($caution_type) ? ($caution_type[0] ?? '') : $caution_type;

        // =========================================================
        // 1. ÉCHÉANCIER DE PAIEMENT
        // =========================================================
        $paiement_items = [];
        if ($pay_mode === 'totalite') {
            $paiement_items[] = "Le règlement intégral du séjour est exigé au moment de la réservation afin de confirmer celle-ci.";
        } else {
            // Logique Acompte + Solde
            $symbole = ($deposit_type === 'montant_fixe') ? '€' : '%';
            $paiement_items[] = sprintf(
                "Un acompte de %s %s du montant total est exigé lors de la réservation pour valider votre séjour.",
                $deposit_val,
                $symbole
            );
            if ($delay_days > 0) {
                $paiement_items[] = sprintf(
                    "Le paiement du solde devra être acquitté au plus tard %d jours avant la date prévue de votre arrivée.",
                    $delay_days
                );
            }
        }

        if (!empty($paiement_items)) {
            $results[] = [
                'label' => 'Conditions de Règlement',
                'svg'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
                'items' => $paiement_items
            ];
        }

        // =========================================================
        // 2. POLITIQUE D'ANNULATION (Texte Fixe)
        // =========================================================
        $results[] = [
            'label' => 'Conditions d\'Annulation',
            'svg'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="9" y1="14" x2="15" y2="20"/><line x1="15" y1="14" x2="9" y2="20"/></svg>',
            'items' => [
                "Annulation survenant 31 jours ou plus avant l'arrivée : retenue de 6% sur les sommes pré-payées (frais de gestion bancaire).",
                "Entre 31 et 21 jours avant l'arrivée : pénalité équivalant à 50% du montant total du séjour.",
                "Entre 20 et 15 jours avant l'arrivée : pénalité équivalant à 75% du montant total du séjour.",
                "Moins de 15 jours avant l'arrivée : retenue intégrale (100%) du montant du séjour."
            ]
        ];

        // =========================================================
        // 3. DÉPÔT DE GARANTIE (CAUTION)
        // =========================================================
        $caution_items = [];

        // On vérifie qu'une politique de caution a bien été sélectionnée dans le Dashboard
        $has_caution_policy = !empty(PCR_Fields::get('pc_caution_type', $post_id));

        if ($has_caution_policy) {
            // Le texte s'adapte élégamment selon si un montant a été saisi ou non
            $montant_texte = ($caution_amount > 0) ? sprintf(" d'un montant de %s €", number_format($caution_amount, 0, ',', ' ')) : "";

            if ($caution_type === 'aucune') {
                $caution_items[] = "Le dépôt de garantie" . $montant_texte . " sera à régler directement auprès du propriétaire (ou de votre hôte) lors de votre arrivée pour la remise des clés.";
            } else {
                $caution_items[] = "Un dépôt de garantie" . $montant_texte . " est requis par l'agence pour ce logement.";
                $caution_items[] = "Cette somme vous sera intégralement restituée à l'issue de votre séjour, sous réserve qu'aucune dégradation ne soit constatée lors de l'état des lieux de sortie.";
            }

            $results[] = [
                'label' => 'Dépôt de Garantie',
                'svg'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>',
                'items' => $caution_items
            ];
        }

        return $results;
    }
}
