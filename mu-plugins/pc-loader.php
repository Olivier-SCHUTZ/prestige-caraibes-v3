<?php
/**
 * PC — Loader d'env : charge les modules staging quand PC_ENV=staging.
 */
if (defined('PC_ENV') && PC_ENV === 'staging') {
    require __DIR__ . '/pc-sandbox-menu-prefix.php';
}
