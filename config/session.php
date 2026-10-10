<?php



ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600); // 1 hora
session_start();


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function redirect($url, $message = '', $type = 'success') {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit();
}


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}


function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, nome, email, perfil, criado_em FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function userCan($permissao) {
    if (!isLoggedIn()) {
        return false;
    }
    $permissaoAtual = $_SESSION['perfil'] ?? $_SESSION['permissao'] ?? 'usuario';
    return $permissaoAtual === $permissao;
}


function formatDateBr($date) {
    if (empty($date)) {
        return '';
    }
    $d = new DateTime($date);
    return $d->format('d/m/Y');
}


function formatDateTimeBr($datetime) {
    if (empty($datetime)) {
        return '';
    }
    $d = new DateTime($datetime);
    return $d->format('d/m/Y H:i:s');
}

function formatDate($date) {
    return formatDateBr($date);
}

function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}


function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}


function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        $class = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-error' : 'alert-info');
        echo '<div class="alert ' . $class . '">' . $message . '</div>';
    }
}
