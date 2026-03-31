<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Document_Financial_Calculator
 * * Service du domaine responsable de l'analyse et du calcul des données financières
 * (HT, TVA, TTC, reste à payer) pour la génération des documents.
 * Implémente le pattern Singleton.
 */
class PCR_Document_Financial_Calculator
{
    /**
     * @var PCR_Document_Financial_Calculator|null
     */
    private static $instance = null;

    /**
     * Retourne l'instance unique de la classe.
     *
     * @return PCR_Document_Financial_Calculator
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur privé.
     */
    private function __construct() {}

    /**
     * Analyse le JSON detail_tarif, nettoie les prix, gère les quantités et calcule les totaux.
     *
     * @param object $resa L'objet réservation.
     * @return array Tableau contenant les lignes détaillées et les totaux globaux.
     */
    public function calculate_for_reservation($resa)
    {
        $lines = json_decode($resa->detail_tarif, true);
        if (!is_array($lines)) {
            $lines = [];
        }

        // Récupération des Taux de manière sécurisée (Natif > ACF)
        $item_id = $resa->item_id;
        $pcr_exists = class_exists('PCR_Fields');
        $has_acf = function_exists('get_field');

        $tva_logement = (float) ($pcr_exists ? PCR_Fields::get('taux_tva', $item_id) : ($has_acf ? get_field('taux_tva', $item_id) : 0));
        $tva_menage_val = $pcr_exists ? PCR_Fields::get('taux_tva_menage', $item_id) : ($has_acf ? get_field('taux_tva_menage', $item_id) : '');
        $tva_menage = ($tva_menage_val !== '' && $tva_menage_val !== null) ? (float) $tva_menage_val : 8.5;
        $tva_plus_value = 8.5;

        $data = [
            'lines' => [],
            'total_ht' => 0,
            'total_tva' => 0,
            'total_ttc' => 0,
        ];

        foreach ($lines as $line) {
            // Sécurisation si la clé 'label' est manquante ou mal formatée
            $label_raw = isset($line['label']) ? (string) $line['label'] : (isset($line['description']) ? (string) $line['description'] : 'Ligne sans titre');

            // --- 1. NETTOYAGE ROBUSTE DU PRIX ---
            // On récupère la valeur brute
            $price_raw = isset($line['price']) ? $line['price'] : (isset($line['amount']) ? $line['amount'] : 0);

            // Conversion en chaîne pour traitement
            $str = (string) $price_raw;
            // Décodage entités HTML (ex: &nbsp;)
            $str = html_entity_decode($str);
            // Suppression des espaces insécables (code ASCII 160 et UTF-8 C2A0)
            $str = str_replace(["\xc2\xa0", "\xa0", " "], "", $str);
            // On ne garde que chiffres, point, virgule, et le signe moins
            $clean_price = preg_replace('/[^0-9,.-]/', '', $str);
            // Remplacement virgule par point pour le float
            $clean_price = str_replace(',', '.', $clean_price);

            $total_line_ttc = (float) $clean_price;

            // --- 2. LOGIQUE QUANTITÉ ---
            $quantity = 1;
            $description = $label_raw;

            // 🚀 THE FIX : On utilise d'abord nos nouvelles données propres (JSON V2)
            if (isset($line['qty']) && (int)$line['qty'] > 0) {
                $quantity = (int)$line['qty'];
                $description = !empty($line['clean_label']) ? $line['clean_label'] : $label_raw;
            }
            // Fallback de sécurité pour les très vieilles factures qui n'ont pas encore le JSON V2
            else {
                if (preg_match('/^(?:(\d+)\s*[x×X]\s*)/u', $label_raw, $matches) || preg_match('/^(\d+)\s+/u', $label_raw, $matches)) {
                    $quantity = (int) $matches[1];
                    $description = preg_replace('/^(?:\d+\s*[x×X]\s*|\d+\s+)/u', '', $label_raw);
                } elseif (preg_match('/(?:\s*[x×X]\s*(\d+))$/u', $label_raw, $matches)) {
                    $quantity = (int) $matches[1];
                    $description = preg_replace('/(?:\s*[x×X]\s*\d+)$/u', '', $label_raw);
                }
            }

            // On nettoie les espaces en trop
            $description = trim($description);

            // Le calcul du Prix Unitaire redevient parfait ! (Ex: 20€ total / 4 Qté = 5€ l'unité)
            $unit_ttc = ($quantity > 0) ? $total_line_ttc / $quantity : 0;

            // --- 3. DÉTECTION TAUX TVA ---
            $taux_applicable = $tva_logement;
            $label_lower = mb_strtolower($label_raw);

            if (strpos($label_lower, 'taxe de séjour') !== false) {
                $taux_applicable = 0;
            } elseif (strpos($label_lower, 'ménage') !== false || strpos($label_lower, 'menage') !== false) {
                $taux_applicable = $tva_menage;
            } elseif (strpos($label_lower, 'plus value') !== false || strpos($label_lower, 'plus-value') !== false) {
                $taux_applicable = $tva_plus_value;
            } elseif (strpos($label_lower, 'remise') !== false) {
                // Pour une remise, on garde le taux du logement (pour réduire la TVA proportionnellement)
                $taux_applicable = $tva_logement;
            }

            // --- 4. CALCUL HT / TVA ---
            if ($taux_applicable > 0) {
                $total_line_ht = $total_line_ttc / (1 + ($taux_applicable / 100));
                $total_line_tva = $total_line_ttc - $total_line_ht;
                $unit_ht = $unit_ttc / (1 + ($taux_applicable / 100));
            } else {
                $total_line_ht = $total_line_ttc;
                $total_line_tva = 0;
                $unit_ht = $unit_ttc;
            }

            // Ajout à la liste
            $data['lines'][] = [
                'description' => $description,
                'quantity'    => $quantity,
                'unit_ht'     => $unit_ht,
                'taux_tva'    => $taux_applicable,
                'total_ht'    => $total_line_ht,
                'total_tva'   => $total_line_tva,
                'total_ttc'   => $total_line_ttc
            ];

            // --- 5. CUMUL DES TOTAUX ---
            $data['total_ht']  += $total_line_ht;
            $data['total_tva'] += $total_line_tva;
            $data['total_ttc'] += $total_line_ttc;
        }

        // --- 6. GESTION DES PAIEMENTS ---
        $data['deja_paye'] = 0;
        $data['date_dernier_paiement'] = null;

        // Interaction avec le module de paiement existant
        if (class_exists('PCR_Payment')) {
            $payments = PCR_Payment::get_for_reservation($resa->id);
            if ($payments) {
                foreach ($payments as $p) {
                    if ($p->statut === 'paye') {
                        $data['deja_paye'] += (float) $p->montant;
                        $data['date_dernier_paiement'] = $p->date_paiement;
                    }
                }
            }
        }

        // Reste à payer (sécurité anti-négatif)
        $data['reste_a_payer'] = max(0, $data['total_ttc'] - $data['deja_paye']);

        return $data;
    }
}
