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

echo renderGuiaDocumento($guia);
