<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Calculateur de devis pour les logements.
 * Remplacement du moteur JS legacy (pc-devis.js).
 */
class PCR_Housing_Pricing_Calculator
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Calcule le devis pour un logement.
     *
     * @param array $config Configuration tarifaire du logement (issue de pc_resa_get_logement_pricing_config)
     * @param array $args Paramètres de la recherche (dates, voyageurs, etc.)
     * @return array Résultat du calcul (success, lines, total, message)
     */
    public function calculate_quote($config, $args = [])
    {
        $response = [
            'success'      => false,
            'lines'        => [],
            'total'        => 0,
            'isSurDevis'   => false,
            'message'      => '',
            'code'         => '',
        ];

        // 1. Vérification de la configuration
        if (empty($config)) {
            $response['code']    = 'missing_config';
            $response['message'] = 'Configuration du logement manquante.';
            return $response;
        }

        // 2. Gestion du mode "Sur devis manuel"
        $manualQuote = isset($args['manualQuote']) ? (bool) $args['manualQuote'] : ! empty($config['manualQuote']);
        if ($manualQuote) {
            $response['success']    = true;
            $response['isSurDevis'] = true;
            $response['lines']      = [
                [
                    'label'  => 'En attente de devis personnalisé',
                    'amount' => 0,
                    'price'  => 'En attente'
                ]
            ];
            return $response;
        }

        // 3. Validation des dates
        if (empty($args['date_arrivee']) || empty($args['date_depart'])) {
            $response['code']    = 'missing_dates';
            $response['message'] = 'Choisissez vos dates.';
            return $response;
        }

        try {
            $start = new DateTime($args['date_arrivee']);
            $end   = new DateTime($args['date_depart']);
        } catch (Exception $e) {
            $response['code']    = 'invalid_dates';
            $response['message'] = 'Format de date invalide.';
            return $response;
        }

        if ($start >= $end) {
            $response['code']    = 'invalid_range';
            $response['message'] = 'La date de départ doit être ultérieure à l\'arrivée.';
            return $response;
        }

        // 4. Génération du tableau des nuits (pour le calcul par nuitée)
        $interval = new DateInterval('P1D');
        $period   = new DatePeriod($start, $interval, $end);
        $nights   = [];
        foreach ($period as $dt) {
            $nights[] = $dt->format('Y-m-d');
        }
        $night_count = count($nights);

        if ($night_count <= 0) {
            $response['code']    = 'invalid_range';
            $response['message'] = 'Choisissez vos dates.';
            return $response;
        }

        // 5. Validation de la capacité (Adultes + Enfants)
        $adults   = max(0, (int) ($args['adults'] ?? 0));
        $children = max(0, (int) ($args['children'] ?? 0));
        $infants  = max(0, (int) ($args['infants'] ?? 0));

        $guests_capacity = $adults + $children;
        $cap             = (int) ($config['cap'] ?? 0);

        if ($cap > 0 && $guests_capacity > $cap) {
            $response['code']    = 'over_capacity';
            $response['message'] = "Capacité max : {$cap} personnes (adultes + enfants).";
            return $response;
        }

        // 6. Validation des contraintes de durée de séjour (Min / Max)
        $reqMin = $this->get_required_min_nights($config, $nights);
        if ($reqMin > 0 && $night_count < $reqMin) {
            $plural = $reqMin > 1 ? 's' : '';
            $response['code']    = 'min_nights';
            $response['message'] = "Séjour minimum : {$reqMin} nuit{$plural}.";
            return $response;
        }

        $maxN = (int) ($config['maxNights'] ?? 0);
        if ($maxN > 0 && $night_count > $maxN) {
            $plural = $maxN > 1 ? 's' : '';
            $response['code']    = 'max_nights';
            $response['message'] = "Séjour maximum : {$maxN} nuit{$plural}.";
            return $response;
        }

        // Si on arrive ici, les validations de base sont passées, on a un feu vert !
        $response['success'] = true;

        // --- DÉBUT ÉTAPE 2 : Moteur de nuitées ---

        $lodging_total = 0.0;
        $extras_total  = 0.0;

        // Le nombre de voyageurs pris en compte pour les frais supplémentaires inclut les bébés dans l'ancien système
        $guests_for_extras = $adults + $children + $infants;

        foreach ($nights as $night) {
            // 1. Prix de la nuitée (Saison ou Base)
            $lodging_total += $this->get_night_price($config, $night);

            // 2. Frais pour voyageurs supplémentaires
            $ep = $this->get_extra_params($config, $night);
            if ($ep['fee'] > 0 && $ep['from'] > 0 && $guests_for_extras >= $ep['from']) {
                // Ex: s'il y a 5 voyageurs et que le supplément s'applique à partir du 3ème (inclus)
                // ( 5 - ( 3 - 1 ) ) = 3 voyageurs supplémentaires facturés
                $extras_total += ($guests_for_extras - ($ep['from'] - 1)) * $ep['fee'];
            }
        }

        // --- DÉBUT ÉTAPE 3 : Frais annexes, taxes et finalisation ---

        $cleaning = (float) ($config['cleaning'] ?? 0);
        $other    = (float) ($config['otherFee'] ?? 0);

        // Calcul complexe de la taxe de séjour (traduit de pc-devis.js)
        $taxe = $this->calculate_tourist_tax($config, $lodging_total, $night_count, $guests_for_extras, $adults);

        $grand_total = $lodging_total + $extras_total + $cleaning + $other + $taxe;

        // Construction des lignes du devis pour Vue.js
        $lines = [];

        if ($lodging_total > 0) {
            $lines[] = [
                'label'  => "Hébergement ($night_count nuits)",
                'amount' => $lodging_total,
                'price'  => number_format($lodging_total, 2, ',', ' ') . ' €'
            ];
        }
        if ($extras_total > 0) {
            $lines[] = [
                'label'  => 'Invités supplémentaires',
                'amount' => $extras_total,
                'price'  => number_format($extras_total, 2, ',', ' ') . ' €'
            ];
        }
        if ($cleaning > 0) {
            $lines[] = [
                'label'  => 'Frais de ménage',
                'amount' => $cleaning,
                'price'  => number_format($cleaning, 2, ',', ' ') . ' €'
            ];
        }
        if ($other > 0) {
            $other_label = ! empty($config['otherLabel']) ? $config['otherLabel'] : 'Autres frais';
            $lines[] = [
                'label'  => $other_label,
                'amount' => $other,
                'price'  => number_format($other, 2, ',', ' ') . ' €'
            ];
        }

        if ($taxe > 0) {
            $lines[] = [
                'label'  => 'Taxe de séjour',
                'amount' => $taxe,
                'price'  => number_format($taxe, 2, ',', ' ') . ' €'
            ];
        }

        $response['lines'] = $lines;
        $response['total'] = $grand_total;

        return $response;
    }

    /**
     * Traduction de requiredMinNights() :
     * Calcule le nombre de nuits minimum requis pour les dates choisies,
     * en vérifiant si une des nuits tombe dans une saison exigeant un minimum plus élevé.
     */
    private function get_required_min_nights($config, $nights)
    {
        // Fallback sur 'minNights' ou 'min'
        $req = (int) ($config['minNights'] ?? $config['min'] ?? 0);

        if (empty($config['seasons']) || ! is_array($config['seasons']) || empty($nights)) {
            return $req > 0 ? $req : 1;
        }

        foreach ($nights as $day) {
            foreach ($config['seasons'] as $season) {
                if (empty($season['periods']) || ! is_array($season['periods'])) {
                    continue;
                }
                foreach ($season['periods'] as $period) {
                    if (! empty($period['from']) && ! empty($period['to'])) {
                        if ($day >= $period['from'] && $day <= $period['to']) {
                            $season_min = (int) ($season['min_nights'] ?? 0);
                            if ($season_min > $req) {
                                $req = $season_min; // On garde le minimum le plus strict trouvé
                            }
                        }
                    }
                }
            }
        }

        return $req > 0 ? $req : 1;
    }

    /**
     * Traduction de nightPrice() :
     * Détermine le prix d'une nuit spécifique (Saison ou Base).
     */
    private function get_night_price($config, $date)
    {
        if (! empty($config['seasons']) && is_array($config['seasons'])) {
            foreach ($config['seasons'] as $season) {
                if (empty($season['periods']) || ! is_array($season['periods'])) {
                    continue;
                }
                foreach ($season['periods'] as $period) {
                    if (! empty($period['from']) && ! empty($period['to'])) {
                        if ($date >= $period['from'] && $date <= $period['to']) {
                            return (float) ($season['price'] ?? 0) > 0 ? (float) $season['price'] : (float) ($config['basePrice'] ?? 0);
                        }
                    }
                }
            }
        }
        return (float) ($config['basePrice'] ?? 0);
    }

    /**
     * Traduction de extraParamsFor() :
     * Détermine les paramètres de frais de voyageurs supplémentaires pour une nuit donnée.
     */
    private function get_extra_params($config, $date)
    {
        if (! empty($config['seasons']) && is_array($config['seasons'])) {
            foreach ($config['seasons'] as $season) {
                if (empty($season['periods']) || ! is_array($season['periods'])) {
                    continue;
                }
                foreach ($season['periods'] as $period) {
                    if (! empty($period['from']) && ! empty($period['to'])) {
                        if ($date >= $period['from'] && $date <= $period['to']) {
                            return [
                                'fee'  => (isset($season['extra_fee']) && $season['extra_fee'] !== '') ? (float) $season['extra_fee'] : (float) ($config['extraFee'] ?? 0),
                                'from' => (isset($season['extra_from']) && $season['extra_from'] !== '') ? (int) $season['extra_from'] : (int) ($config['extraFrom'] ?? 0),
                            ];
                        }
                    }
                }
            }
        }
        return [
            'fee'  => (float) ($config['extraFee'] ?? 0),
            'from' => (int) ($config['extraFrom'] ?? 0),
        ];
    }

    /**
     * Traduction de la logique complexe de taxe de séjour depuis pc-devis.js
     */
    private function calculate_tourist_tax($config, $lodging_total, $night_count, $guests_for_extras, $adults)
    {
        if ($night_count <= 0 || $adults <= 0) {
            return 0.0;
        }

        $tax_raw = $config['taxe_sejour'] ?? '';

        // 1. Aplatisseur universel (récursif)
        // Peu importe comment ACF structure ses données (Tableau, Objet, taxonomies), 
        // on parcourt tout pour en extraire le texte brut.
        $extract_text = function ($data) use (&$extract_text) {
            $text = '';
            if (is_string($data) || is_numeric($data)) {
                $text .= ' ' . $data;
            } elseif (is_object($data)) {
                if (isset($data->slug)) $text .= ' ' . $data->slug;
                if (isset($data->name)) $text .= ' ' . $data->name;
                $text .= ' ' . $extract_text((array) $data); // On force la conversion de l'objet en tableau pour fouiller dedans
            } elseif (is_array($data)) {
                foreach ($data as $val) {
                    $text .= ' ' . $extract_text($val);
                }
            }
            return $text;
        };

        $tax_str = $extract_text($tax_raw);

        // 2. Normalisation (minuscules, sans accents)
        // ex: "3 étoiles" devient "3 etoiles"
        $tax_str = strtolower(remove_accents($tax_str));

        // 3. Détection de la taxe à 5%
        $is_pct_5 = (strpos($tax_str, '5') !== false) && (strpos($tax_str, '%') !== false || strpos($tax_str, 'pourcent') !== false || strpos($tax_str, 'pct') !== false);

        // 4. Détection du système d'étoiles (ex: 3 etoiles, 4_etoiles)
        // L'expression régulière cherche un chiffre de 1 à 5, suivi optionnellement d'espaces/tirets/underscores, suivi de "etoile"
        preg_match('/([1-5])[\s\-_]*etoile/', $tax_str, $matches);
        $stars = ! empty($matches[1]) ? (int) $matches[1] : null;

        $class_rates = [1 => 0.8, 2 => 0.9, 3 => 1.5, 4 => 2.3, 5 => 3.0];
        $taxe = 0.0;

        if ($is_pct_5 && $guests_for_extras > 0) {
            $a = $lodging_total / $night_count / $guests_for_extras;
            $b = 0.05 * $a;
            $taxe = $b * $night_count * $adults;
        } elseif ($stars && isset($class_rates[$stars])) {
            $taxe = $class_rates[$stars] * $adults * $night_count;
        }

        return $taxe;
    }
}
