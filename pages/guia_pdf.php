<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/guia_documento.php';

connectDB();
requireLogin();
requirePermission('admin');

$id = (int)($_GET['id'] ?? 0);
$guia = $id > 0 ? getGuiaById($id) : false;
if (!$guia) {
    http_response_code(404);
    exit('Guia não encontrada.');
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit('Biblioteca PDF indisponível. Execute composer install no projeto.');
}

require_once $autoload;

try {
    $options = new Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml(renderGuiaDocumento($guia, false), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $numero = preg_replace('/[^A-Za-z0-9_-]/', '-', $guia['numero'] ?? (string)$guia['id']);
    $dompdf->stream('guia-' . $numero . '.pdf', ['Attachment' => true]);
} catch (Throwable $e) {
    error_log('Falha ao gerar PDF da guia ' . $id . ': ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível gerar o PDF desta guia.');
}
