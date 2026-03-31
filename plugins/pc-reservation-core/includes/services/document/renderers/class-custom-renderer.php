<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PCR_Custom_Renderer
 * * Gère le rendu HTML/PDF spécifique pour les documents libres (modèles créés dans le CPT pc_pdf_template).
 */
class PCR_Custom_Renderer extends PCR_Base_Document_Renderer
{
    /**
     * Rendu du document personnalisé.
     *
     * @param object $resa       L'objet réservation.
     * @param string $doc_number Le numéro de document.
     * @param array  $args       Doit contenir 'template_id'.
     * @return string            HTML complet.
     */
    public function render($resa, $doc_number, $args = [])
    {
        $template_id = $args['template_id'] ?? 0;

        // 1. Récupération du contenu
        $post = get_post($template_id);
        if (!$post) return "Erreur : Modèle introuvable (ID $template_id)";

        // 2. Calculs Financiers & Durée
        $fin = PCR_Document_Financial_Calculator::get_instance()->calculate_for_reservation($resa);

        $ts_arr = strtotime($resa->date_arrivee);
        $ts_dep = strtotime($resa->date_depart);
        $duree  = ceil(($ts_dep - $ts_arr) / 86400);

        // Construction adresse
        $adresse_client = trim(($resa->adresse ?? '') . ' ' . ($resa->code_postal ?? '') . ' ' . ($resa->ville ?? ''));
        if (empty($adresse_client)) $adresse_client = "Adresse non renseignée";

        // 3. Définition des Variables
        $variables = [
            // Client
            '{prenom_client}' => $this->escapeForPdf($resa->prenom ?? ''),
            '{nom_client}'    => $this->escapeForPdf(strtoupper($resa->nom ?? '')),
            '{email_client}'  => $this->escapeForPdf($resa->email ?? ''),
            '{telephone}'     => $this->escapeForPdf($resa->telephone ?? ''),
            '{adresse_client}' => $this->escapeForPdf($adresse_client),

            // Séjour
            '{date_arrivee}'  => date_i18n('d/m/Y', $ts_arr),
            '{date_depart}'   => date_i18n('d/m/Y', $ts_dep),
            '{duree_sejour}'  => $duree . ' nuit(s)',
            '{logement}'      => get_the_title($resa->item_id),
            '{numero_resa}'   => $resa->id,

            // Finances (Montants seuls)
            '{montant_total}' => number_format($fin['total_ttc'], 2, ',', ' ') . ' €',
            '{acompte_paye}'  => number_format($fin['deja_paye'], 2, ',', ' ') . ' €',
            '{solde_restant}' => number_format($fin['reste_a_payer'], 2, ',', ' ') . ' €',
        ];

        // 4. Remplacement
        $content = wpautop($post->post_content);
        $content = str_replace(array_keys($variables), array_values($variables), $content);

        // 5. Branding sécurisé
        $pcr_exists = class_exists('PCR_Fields');
        $has_acf = function_exists('get_field');

        $logo_url = get_option('option_pc_pdf_logo') ?: get_option('pc_pdf_logo') ?: ($pcr_exists ? PCR_Fields::get('pc_pdf_logo', 'option') : ($has_acf ? get_field('pc_pdf_logo', 'option') : ''));
        $logo = $this->get_image_base64($logo_url);

        $color = get_option('option_pc_pdf_primary_color') ?: get_option('pc_pdf_primary_color') ?: ($pcr_exists ? PCR_Fields::get('pc_pdf_primary_color', 'option') : ($has_acf ? get_field('pc_pdf_primary_color', 'option') : ''));
        $color = $color ?: '#000000';

        $company = [
            'name'    => get_option('option_pc_legal_name') ?: get_option('pc_legal_name') ?: ($pcr_exists ? PCR_Fields::get('pc_legal_name', 'option') : ($has_acf ? get_field('pc_legal_name', 'option') : '')),
            'siret'   => get_option('option_pc_legal_siret') ?: get_option('pc_legal_siret') ?: ($pcr_exists ? PCR_Fields::get('pc_legal_siret', 'option') : ($has_acf ? get_field('pc_legal_siret', 'option') : '')),
            'address' => get_option('option_pc_legal_address') ?: get_option('pc_legal_address') ?: ($pcr_exists ? PCR_Fields::get('pc_legal_address', 'option') : ($has_acf ? get_field('pc_legal_address', 'option') : '')),
            'email'   => get_option('option_pc_legal_email') ?: get_option('pc_legal_email') ?: ($pcr_exists ? PCR_Fields::get('pc_legal_email', 'option') : ($has_acf ? get_field('pc_legal_email', 'option') : '')),
        ];

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <?php echo $this->get_common_css($color); ?>
            <style>
                .custom-content {
                    font-size: 12px;
                    line-height: 1.6;
                    color: #333;
                    margin-top: 20px;
                }

                .custom-content h1,
                .custom-content h2 {
                    color: <?php echo $color; ?>;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>

        <body>
            <div class="header">
                <div class="left">
                    <?php if ($logo): ?><img src="<?php echo $logo; ?>" class="logo"><?php else: ?><h2><?php echo $company['name']; ?></h2><?php endif; ?>
                </div>
                <div class="right doc-info">
                    <div class="doc-type"><?php echo strtoupper($post->post_title); ?></div>
                    <div class="doc-meta">
                        <strong>N° :</strong> <?php echo $doc_number; ?><br>
                        <strong>Date :</strong> <?php echo date_i18n('d/m/Y'); ?><br>
                        <strong>Réf :</strong> #<?php echo $resa->id; ?>
                    </div>
                </div>
                <div class="clear"></div>
            </div>

            <div class="addresses">
                <div class="addr-box">
                    <strong><?php echo $company['name']; ?></strong><br>
                    <?php echo nl2br($company['address']); ?>
                </div>
                <div class="addr-box client">
                    <strong>À l'attention de :</strong><br>
                    <?php echo $this->escapeForPdf(($resa->prenom ?? '') . ' ' . strtoupper($resa->nom ?? '')); ?><br>
                    <?php if (!empty($adresse_client) && $adresse_client !== "Adresse non renseignée") echo $this->escapeForPdf($adresse_client) . '<br>'; ?>
                    <?php echo $this->escapeForPdf($resa->email ?? ''); ?><br>
                    <?php echo $this->escapeForPdf($resa->telephone ?? ''); ?>
                </div>
                <div class="clear"></div>
            </div>

            <div class="custom-content">
                <?php echo $content; ?>
            </div>

            <div class="footer">
                <?php echo $company['name']; ?> - SIRET : <?php echo $company['siret']; ?> - <?php echo $company['email']; ?>
            </div>

            <?php
            // INJECTION CGV (SI LIÉES DANS L'ADMIN)
            $custom_cgv_content = '';
            if (!empty($template_id) && is_numeric($template_id)) {
                $custom_cgv_content = class_exists('PCR_Fields')
                    ? PCR_Fields::get('pc_linked_cgv', $template_id)
                    : (function_exists('get_field') ? get_field('pc_linked_cgv', $template_id) : '');
            }
            if (!empty($custom_cgv_content)) {
                echo '<div style="page-break-before: always;"></div>';
                echo '<h3 class="uppercase" style="border-bottom:1px solid #ccc; padding-bottom:5px; margin-bottom:15px;">Conditions Générales</h3>';
                echo '<div style="font-size:10px; text-align:justify; color:#444;">' . wpautop($custom_cgv_content) . '</div>';
            }
            ?>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
