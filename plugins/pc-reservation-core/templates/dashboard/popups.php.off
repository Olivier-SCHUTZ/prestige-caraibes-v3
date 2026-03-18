<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="pc-cancel-reservation-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box">
        <h3 class="pc-popup-title">Annuler cette réservation ?</h3>
        <p class="pc-popup-text">
            Êtes-vous sûr de vouloir annuler cette réservation ?<br>
            <small>Si le client a déjà payé, pensez au remboursement via Stripe.</small>
        </p>
        <div class="pc-popup-actions">
            <button class="pc-btn pc-btn--primary" data-pc-popup-confirm>Confirmer l'annulation</button>
            <button class="pc-btn pc-btn--line" data-pc-popup-close>Retour</button>
        </div>
    </div>
</div>

<div id="pc-capture-caution-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box" style="max-width: 450px;">
        <h3 class="pc-popup-title">Encaisser sur la caution</h3>

        <p class="pc-popup-text">
            Montant total bloqué : <strong id="pc-capture-max-display"></strong> €
        </p>

        <form id="pc-capture-form" style="text-align:left; margin-bottom:20px;">
            <input type="hidden" id="pc-capture-resa-id">
            <input type="hidden" id="pc-capture-ref">

            <label class="pc-label-force">Montant à prélever (€)</label>
            <input type="number" id="pc-capture-amount" step="0.01" min="1" class="pc-input-force">

            <label class="pc-label-force">Motif / Observation</label>
            <textarea id="pc-capture-note" rows="3" class="pc-input-force"
                placeholder="Ex: Ménage non fait, Casse vaisselle..."></textarea>
        </form>

        <div class="pc-popup-actions">
            <button type="button" class="pc-btn pc-btn--primary" id="pc-capture-confirm-btn" style="background:#dc2626; border-color:#dc2626;">
                Confirmer l'encaissement
            </button>
            <button type="button" class="pc-btn pc-btn--line" onclick="document.getElementById('pc-capture-caution-popup').hidden = true;">
                Annuler
            </button>
        </div>
    </div>
</div>

<div id="pc-rotate-caution-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box" style="max-width: 400px;">
        <h3 class="pc-popup-title">🔄 Renouveler la Caution</h3>

        <p class="pc-popup-text">
            Cette action va :<br>
            1. Créer une <strong>nouvelle empreinte</strong> bancaire sur la carte du client.<br>
            2. Annuler (libérer) l'ancienne empreinte immédiatement.
        </p>

        <p class="pc-popup-text" style="font-size:0.9rem; color:#64748b; background:#f1f5f9; padding:10px; border-radius:6px;">
            💡 Utile si le séjour dure plus de 7 jours.<br>
            Le client ne sera pas débité, c'est transparent pour lui.
        </p>

        <form id="pc-rotate-form">
            <input type="hidden" id="pc-rotate-resa-id">
            <input type="hidden" id="pc-rotate-ref">
        </form>

        <div class="pc-popup-actions">
            <button type="button" class="pc-btn pc-btn--primary" id="pc-rotate-confirm-btn" style="background:#2563eb; border-color:#2563eb;">
                Confirmer le renouvellement
            </button>
            <button type="button" class="pc-btn pc-btn--line" onclick="document.getElementById('pc-rotate-caution-popup').hidden = true;">
                Annuler
            </button>
        </div>
    </div>
</div>

<div id="pc-send-message-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box" style="max-width: 500px; text-align:left; background:#fff; padding:20px; border-radius:8px; margin:50px auto; position:relative;">
        <h3 class="pc-popup-title">✉️ Envoyer un message</h3>
        <p>Destinataire : <strong id="pc-msg-client-name">Client</strong></p>

        <form id="pc-msg-form">
            <input type="hidden" id="pc-msg-resa-id">

            <label style="display:block; margin:10px 0 5px; font-weight:600;">Modèle de message :</label>
            <select id="pc-msg-template" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                <option value="">-- Sélectionner un modèle --</option>
                <option value="custom">✍️ Nouveau message (Libre)</option>
                <?php foreach ($templates_list as $t) : ?>
                    <option value="<?php echo esc_attr($t['id']); ?>"><?php echo esc_html($t['title']); ?></option>
                <?php endforeach; ?>
            </select>

            <div id="pc-msg-custom-area" style="display:none; margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Sujet :</label>
                <input type="text" id="pc-msg-custom-subject" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">

                <label style="display:block; margin-bottom:5px; font-size:0.9rem;">Message :</label>
                <textarea id="pc-msg-custom-body" rows="6" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:sans-serif;" placeholder="Bonjour..."></textarea>
            </div>

            <div id="pc-msg-template-hint" style="background:#fff7ed; border:1px solid #fed7aa; padding:10px; border-radius:6px; font-size:0.85rem; color:#9a3412; margin:15px 0;">
                💡 Le message sera personnalisé automatiquement au moment de l'envoi.
            </div>

            <div id="pc-msg-feedback" style="display:none; padding:10px; border-radius:6px; font-size:0.9rem; margin-bottom:15px; text-align:center;"></div>
        </form>

        <div class="pc-popup-actions" style="justify-content:flex-end; display:flex; gap:10px;">
            <button type="button" class="pc-btn pc-btn--line" onclick="document.getElementById('pc-send-message-popup').hidden = true;">
                Annuler
            </button>
            <button type="button" class="pc-btn pc-btn--primary" id="pc-msg-send-btn">
                Envoyer
            </button>
        </div>
    </div>
</div>

<div id="pc-read-message-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box" style="max-width: 600px; text-align:left; background:#fff; padding:25px; border-radius:10px; margin:50px auto; position:relative;">
        <button type="button" onclick="document.getElementById('pc-read-message-popup').hidden = true;"
            style="position:absolute; top:10px; right:15px; border:none; background:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        <h3 class="pc-popup-title" style="margin-top:0;">Détail du message</h3>
        <div id="pc-read-message-content" style="max-height:60vh; overflow-y:auto; line-height:1.5; font-size:0.95rem; white-space:pre-wrap;"></div>
        <div style="text-align:right; margin-top:20px;">
            <button type="button" class="pc-btn pc-btn--secondary" onclick="document.getElementById('pc-read-message-popup').hidden = true;">Fermer</button>
        </div>
    </div>
</div>

<div id="pc-pdf-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:90%; height:90%; border-radius:8px; display:flex; flex-direction:column; box-shadow:0 10px 25px rgba(0,0,0,0.5);">

        <div style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:18px; display:flex; align-items:center; gap:10px;">
                📄 Prévisualisation du document
            </h3>
            <button type="button" id="pc-close-pdf-modal" style="background:none; border:none; font-size:24px; cursor:pointer; color:#666; line-height:1;">&times;</button>
        </div>

        <div style="flex:1; background:#f0f0f0; padding:0; overflow:hidden; position:relative;">
            <iframe id="pc-pdf-iframe" src="" style="width:100%; height:100%; border:none;" title="Aperçu PDF"></iframe>
        </div>

        <div style="padding:10px 15px; background:#f9f9f9; text-align:right; border-top:1px solid #eee;">
            <button type="button" class="pc-btn pc-btn--ghost" onclick="document.getElementById('pc-close-pdf-modal').click()">Fermer et Actualiser</button>
        </div>
    </div>
</div>

<div id="pc-invoice-blocked-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box" style="max-width: 400px; padding: 30px;">
        <span class="pc-popup-blocked-icon">🚫</span>
        <h3 class="pc-popup-title pc-popup-blocked-title">Action Bloquée</h3>
        <p class="pc-popup-text pc-popup-blocked-msg" id="pc-invoice-blocked-msg">
        </p>
        <div class="pc-popup-actions">
            <button type="button" class="pc-btn pc-btn--primary" style="background:#dc2626; border-color:#dc2626;" onclick="document.getElementById('pc-invoice-blocked-popup').hidden = true;">
                J'ai compris
            </button>
        </div>
    </div>
</div>

<div id="pc-overlap-popup" class="pc-popup-overlay" hidden>
    <div class="pc-popup-box" style="max-width: 450px; border-top: 5px solid #ef4444;">
        <div style="font-size: 4rem; line-height: 1; margin-bottom: 1rem;">⛔</div>
        <h3 class="pc-popup-title" style="color: #b91c1c;">Chevauchement détecté !</h3>

        <p class="pc-popup-text" style="font-weight: 500;">
            Attention, vous sélectionnez une période qui contient déjà des indisponibilités (réservations ou blocages).
        </p>

        <div style="background: #fef2f2; border: 1px solid #fca5a5; padding: 12px; border-radius: 6px; font-size: 0.9rem; color: #7f1d1d; margin-bottom: 20px; text-align: left;">
            <strong>Conseil :</strong><br>
            Laissez le statut sur <u>"Sur devis"</u> ou <u>"Brouillon"</u> pour ne pas créer de conflit technique dans les calendriers externes.<br>
        </div>

        <div class="pc-popup-actions">
            <button type="button" class="pc-btn pc-btn--secondary" id="pc-overlap-cancel">
                Annuler la sélection
            </button>
            <button type="button" class="pc-btn pc-btn--primary" id="pc-overlap-confirm" style="background: #dc2626; border-color: #dc2626;">
                J'ai compris, forcer la date
            </button>
        </div>
    </div>
</div>