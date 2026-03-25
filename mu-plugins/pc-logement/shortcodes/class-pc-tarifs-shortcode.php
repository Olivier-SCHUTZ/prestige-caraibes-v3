<?php

/**
 * Composant Shortcode : Tableau des Tarifs [pc_tarifs_table]
 */

if (!defined('ABSPATH')) {
    exit;
}

class PC_Tarifs_Shortcode extends PC_Shortcode_Base
{

    protected $tag = 'pc_tarifs_table';

    /**
     * Rendu principal du shortcode
     */
    public function render($atts, $content = null)
    {
        $post_id = get_the_ID();

        // 🚀 CORRECTION : On a retiré le !function_exists('get_field')
        if (!$post_id) {
            return '';
        }

        // Récupération des données globales du logement
        $base_price   = PCR_Fields::get('base_price_from', $post_id);
        $unit         = PCR_Fields::get('unite_de_prix',   $post_id);
        $extra_fee    = PCR_Fields::get('extra_guest_fee', $post_id);
        $extra_from   = PCR_Fields::get('extra_guest_from', $post_id);

        // Formatage des données complexes
        $prepared_seasons = $this->prepare_seasons($post_id, $extra_fee, $extra_from);
        $unit_label       = $this->format_unit_label($unit);

        // --- DÉBUT DU RENDU HTML ---
        ob_start(); ?>
        <section class="pc-prices" aria-label="Tarifs">
            <div class="pc-price__head">
                <div class="pc-price__head-left"></div>
                <div class="pc-price__unit"><?php echo esc_html($unit_label); ?></div>
            </div>

            <div class="pc-price__row">
                <div class="pc-price__title">Tarif Par Défaut</div>
                <div class="pc-price__amount"><?php echo esc_html($this->fmt_eur($base_price)); ?></div>
                <?php if ($txt = $this->fmt_surcharge($extra_fee, $extra_from)) : ?>
                    <div class="pc-price__note"><?php echo esc_html($txt); ?></div>
                <?php endif; ?>
            </div>

            <?php foreach ($prepared_seasons as $s): ?>
                <div class="pc-price__card">
                    <div class="pc-price__row">
                        <div class="pc-price__title"><?php echo esc_html($s['name']); ?></div>
                        <div class="pc-price__amount">
                            <?php echo esc_html($this->fmt_eur($s['price'] !== '' ? $s['price'] : $base_price)); ?>
                        </div>
                        <?php if ($txt = $this->fmt_surcharge($s['efee'], $s['efrom'])) : ?>
                            <div class="pc-price__note"><?php echo esc_html($txt); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($s['periods'])): ?>
                        <ul class="pc-price__periods">
                            <?php foreach ($s['periods'] as $p): if (!$p['label']) continue; ?>
                                <li class="pc-price__period"><?php echo esc_html($p['label']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
<?php return ob_get_clean();
    }

    protected function enqueue_assets()
    {
        // Géré par le CSS global
    }

    /**
     * Extrait et prépare les blocs de saisons (Compatible Ancien ACF + Nouveau Natif)
     */
    private function prepare_seasons($post_id, $global_extra_fee, $global_extra_from)
    {
        $raw_seasons = PCR_Fields::get('pc_season_blocks', $post_id);

        // --- 🕵️‍♂️ LE RECONSTRUCTEUR MAGIQUE ACF (Si c'est un Répéteur) ---
        // Si ACF a enregistré un simple chiffre (le nombre de saisons)
        if (is_numeric($raw_seasons) && $raw_seasons > 0) {
            $reconstructed = [];
            for ($i = 0; $i < intval($raw_seasons); $i++) {
                $prefix = "pc_season_blocks_{$i}_";

                // Reconstruire les périodes de cette saison
                $periods_count = PCR_Fields::get($prefix . 'season_periods', $post_id);
                $periods = [];

                if (is_numeric($periods_count) && $periods_count > 0) {
                    for ($j = 0; $j < intval($periods_count); $j++) {
                        $p_prefix = $prefix . "season_periods_{$j}_";
                        $periods[] = [
                            'date_from' => PCR_Fields::get($p_prefix . 'date_from', $post_id),
                            'date_to'   => PCR_Fields::get($p_prefix . 'date_to', $post_id),
                        ];
                    }
                }

                $reconstructed[] = [
                    'season_name'             => PCR_Fields::get($prefix . 'season_name', $post_id),
                    'season_note'             => PCR_Fields::get($prefix . 'season_note', $post_id),
                    'season_price'            => PCR_Fields::get($prefix . 'season_price', $post_id),
                    'season_min_nights'       => PCR_Fields::get($prefix . 'season_min_nights', $post_id),
                    'season_extra_guest_fee'  => PCR_Fields::get($prefix . 'season_extra_guest_fee', $post_id),
                    'season_extra_guest_from' => PCR_Fields::get($prefix . 'season_extra_guest_from', $post_id),
                    'season_periods'          => $periods
                ];
            }
            $raw_seasons = $reconstructed;
        }
        // --- DÉCODEUR NOUVEAU FORMAT (JSON/Sérialisé) ---
        elseif (is_string($raw_seasons)) {
            $decoded = json_decode($raw_seasons, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw_seasons = $decoded;
            } else {
                $raw_seasons = maybe_unserialize($raw_seasons);
            }
        }

        // Si ce n'est toujours pas un tableau après ça, on renvoie un tableau vide
        if (!is_array($raw_seasons)) {
            return [];
        }

        $prepared = [];

        foreach ($raw_seasons as $row) {
            // Sécurité si la saison est mal formatée
            if (empty($row) || !is_array($row)) {
                continue;
            }

            // Gestion de la sous-liste de périodes
            $periods = $row['season_periods'] ?? [];
            if (is_string($periods)) {
                $decoded_periods = json_decode($periods, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $periods = $decoded_periods;
                } else {
                    $periods = maybe_unserialize($periods);
                }
            }

            if (!is_array($periods)) {
                $periods = [];
            }

            $efee  = $row['season_extra_guest_fee'] ?? '';
            $efrom = $row['season_extra_guest_from'] ?? '';

            $prepared[] = [
                'name'    => !empty($row['season_name']) ? trim($row['season_name']) : 'Saison',
                'note'    => trim($row['season_note'] ?? ''),
                'price'   => $row['season_price'] ?? '',
                'min'     => $row['season_min_nights'] ?? '',
                'efee'    => ($efee !== '' ? $efee : $global_extra_fee),
                'efrom'   => ($efrom !== '' ? (int)$efrom : (int)$global_extra_from),
                'periods' => array_map(function ($p) {
                    $f = $p['date_from'] ?? '';
                    $t = $p['date_to']   ?? '';
                    return [
                        'from'  => $f,
                        'to'    => $t,
                        'label' => $this->fmt_range($f, $t)
                    ];
                }, $periods)
            ];
        }

        return $prepared;
    }

    /**
     * Nettoie et formate l'unité de prix (ex: "par Nuit")
     */
    private function format_unit_label($unit)
    {
        $unit_label = 'Par Nuit'; // Standard Airbnb
        if ($unit) {
            $u = trim(mb_strtolower($unit, 'UTF-8'));
            $u = preg_replace('/^\s*par\s+/u', '', $u);
            $unit_label = 'Par ' . mb_convert_case($u, MB_CASE_TITLE, 'UTF-8');
        }
        return $unit_label;
    }

    /**
     * Formate un montant en Euros
     */
    private function fmt_eur($n)
    {
        if ($n === '' || $n === null) return '';
        return number_format((float)$n, 0, ',', ' ') . ' €'; // Format Français (ex: 150 €)
    }

    /**
     * Formate la phrase de frais supplémentaires
     */
    private function fmt_surcharge($fee, $from)
    {
        $fee  = (float) $fee;
        $from = (int) $from;
        if ($fee > 0 && $from > 0) {
            return '+ ' . number_format($fee, 0, ',', ' ') . ' € / pers. suppl. après ' . $from . ' voyageurs';
        }
        return '';
    }

    /**
     * Formate une date YYYY-MM-DD en format lisible (ex: 12 janv. 2024)
     */
    private function fmt_date($ymd)
    {
        if (!$ymd || strlen($ymd) < 10) return '';
        $y = substr($ymd, 0, 4);
        $m = substr($ymd, 5, 2);
        $d = ltrim(substr($ymd, 8, 2), '0');

        $mois = [
            '01' => 'janv.',
            '02' => 'févr.',
            '03' => 'mars',
            '04' => 'avr.',
            '05' => 'mai',
            '06' => 'juin',
            '07' => 'juil.',
            '08' => 'août',
            '09' => 'sept.',
            '10' => 'oct.',
            '11' => 'nov.',
            '12' => 'déc.'
        ];

        $labelM = isset($mois[$m]) ? $mois[$m] : $m;
        return $d . ' ' . $labelM . ' ' . $y;
    }

    /**
     * Formate une période (ex: 12 janv. 2024 - 15 févr. 2024)
     */
    private function fmt_range($from, $to)
    {
        if (!$from && !$to) return '';
        return trim($this->fmt_date($from) . ' - ' . $this->fmt_date($to));
    }
}
