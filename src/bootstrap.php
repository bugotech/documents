<?php

if (! defined('DOMPDF_DIR')) {
    if (isset($_ENV['APP_ENV']) && ($_ENV['APP_ENV'] == 'dev')) {
        define('DOMPDF_DIR', __DIR__ . '/../vendor/dompdf/dompdf');
    } else {
        define('DOMPDF_DIR', __DIR__ . '/../../../dompdf/dompdf');
    }
}

require_once __DIR__ . '/DomPDF/bootstrap.php';