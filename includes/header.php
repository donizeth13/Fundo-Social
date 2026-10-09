<?php
/**
 * Header do sistema
 * Inclui navbar e cabeçalho de todas as páginas protegidas
 */

// Verificar conexão com o banco
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
connectDB();

// Incluir funções
require_once __DIR__ . '/../includes/functions.php';

// Verificar se usuário está logado
requireLogin();

// Buscar estatísticas
$stats = getStatistics();

// Buscar número de guias ativas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM guias WHERE status = 'ativo'");
$guiasAtivas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Buscar número de itens em estoque
$stmt = $pdo->query("SELECT SUM(quantidade) as total FROM itens WHERE quantidade > 0");
$estoque = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$estoque = $estoque ?: 0;

// Buscar número de beneficiários
$stmt = $pdo->query("SELECT COUNT(*) as total FROM beneficiarios");
$totalBeneficiarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Buscar o usuário logado
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>js/script.js" defer></script>
</head>
<body class="bg-light min-vh-100 d-flex flex-column">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo PAGES_URL; ?>dashboard.php">
                <i class="fas fa-hand-holding-usd"></i>
                <span class="brand-full"><?php echo APP_NAME; ?></span>
                <span class="brand-compact">Fundo Social</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Painel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>estoque.php">
                            <i class="fas fa-box"></i> Estoque
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>guias.php">
                            <i class="fas fa-file-signature"></i> Guias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>beneficiarios.php">
                            <i class="fas fa-user-injured"></i> Beneficiários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>triagem.php">
                            <i class="fas fa-clipboard-check"></i> Triagem
                        </a>
                    </li>
                    <?php if (userCan('admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>auditoria.php">
                            <i class="fas fa-clipboard-list"></i> Auditoria
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo PAGES_URL; ?>importar.php">
                            <i class="fas fa-file-import"></i> Importar CSV
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="navbar-text me-3">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['nome']); ?>
                    <span class="badge bg-light text-primary"><?php echo htmlspecialchars($user['perfil'] ?? 'usuario'); ?></span>
                </div>
                <div class="navbar-text">
                    <button class="btn btn-outline-light btn-sm" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid flex-grow-1 pb-5" style="padding-top: 1rem;">
        <?php displayFlashMessage(); ?>
        <?php if (isset($content)): ?>
            <?php echo $content; ?>
        <?php endif; ?>
    </main>

    <footer class="footer bg-primary text-white mt-auto" style="padding: 1rem 0; width: 100%;">
        <div class="container text-center">
            <small>
                <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Versão <?php echo APP_VERSION; ?>
            </small>
        </div>
    </footer>

    <script>
        function logout() {
            fetch('<?php echo PAGES_URL; ?>logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(() => window.location.href = '<?php echo PAGES_URL; ?>login.php')
            .catch(() => window.location.href = '<?php echo PAGES_URL; ?>login.php');
        }
    </script>
</body>
</html>
