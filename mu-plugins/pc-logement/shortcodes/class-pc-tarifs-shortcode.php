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
        if (!function_exists('get_field')) {
            return '';
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        // Récupération des données globales du logement
        $base_price   = get_field('base_price_from', $post_id);
        $unit         = get_field('unite_de_prix',   $post_id);
        $extra_fee    = get_field('extra_guest_fee', $post_id);
        $extra_from   = get_field('extra_guest_from', $post_id);

        // Formatage des données complexes
        $prepared_seasons = $this->prepare_seasons($post_id, $extra_fee, $extra_from);
        $unit_label       = $this->format_unit_label($unit);

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

    /**
     * Chargement des assets (Vide ici car géré par le CSS global pour le moment)
     */
    protected function enqueue_assets()
    {
        // Optionnel : Si un jour vous séparez le CSS des prix, c'est ici qu'il faudra le charger.
    }

    /**
     * Extrait et prépare les blocs de saisons ACF
     */
    private function prepare_seasons($post_id, $global_extra_fee, $global_extra_from)
    {
        $seasons = (array) get_field('pc_season_blocks', $post_id);
        $prepared = [];

        foreach ($seasons as $row) {
            if (empty($row['season_periods']) || !is_array($row['season_periods'])) {
                continue;
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
                }, $row['season_periods'])
            ];
        }

        return $prepared;
    }

    /**
     * Nettoie et formate l'unité de prix (ex: "par Nuit")
     */
    private function format_unit_label($unit)
    {
        $unit_label = 'Par Jour';
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
        return '€' . number_format((float)$n, 0, ',', ' ');
    }

    /**
     * Formate la phrase de frais supplémentaires
     */
    private function fmt_surcharge($fee, $from)
    {
        $fee  = (float) $fee;
        $from = (int) $from;
        if ($fee > 0 && $from > 0) {
            return '+ €' . number_format($fee, 0, ',', ' ') . ' par invité/nuit après ' . $from . ' invités';
        }
        return '';
    }

    /**
     * Formate une date YYYY-MM-DD en format lisible (ex: 12 janv. 2024)
     */
    private function fmt_date($ymd)
    {
        if (!$ymd) return '';
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
