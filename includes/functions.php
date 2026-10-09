<?php
/**
 * Funções gerais da aplicação - Parte 1 (Conexão e Autenticação)
 */

/**
 * Inicia a conexão com o banco de dados
 */
function connectDB() {
    global $pdo;
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Erro de conexão: ' . $e->getMessage());
        die('<div class="alert alert-error">Não foi possível conectar ao banco de dados. Verifique o XAMPP e tente novamente.</div>');
    }
}

/**
 * Redireciona para página de login se não estiver logado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(PAGES_URL . 'login.php');
    }
}

/**
 * Redireciona para página de acesso negado se não tiver permissão
 */
function requirePermission($permissao) {
    if (!userCan($permissao)) {
        redirect(PAGES_URL . 'dashboard.php', 'Acesso negado. Você não tem permissão para acessar esta área.', 'error');
    }
}

/**
 * Gera um token de segurança
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Funções gerais da aplicação - Parte 2 (Validação e Formatação)
 */

/**
 * Valida e formata um CPF
 */
function formatCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    return $cpf;
}

/**
 * Valida e formata um CNPJ
 */
function formatCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) !== 14) {
        return false;
    }
    return $cnpj;
}

/**
 * Valida e formata um telefone
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) >= 12 && substr($phone, 0, 2) === '55') {
        $phone = substr($phone, 2);
    }
    if (strlen($phone) < 10 || strlen($phone) > 11) {
        return false;
    }
    return $phone;
}

/**
 * Gera um código de documento (documento único)
 */
function generateDocumentCode($length = 8) {
    return 'DOC-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, $length));
}

/**
 * Valida se um valor é numérico
 */
function isNumericValue($value) {
    return is_numeric($value) && $value >= 0;
}

/**
 * Valida se uma data é válida no formato brasileiro
 */
function isValidDateBr($date) {
    $d = DateTime::createFromFormat('d/m/Y', $date);
    return $d && $d->format('d/m/Y') === $date;
}

/**
 * Valida se uma data é válida no formato MySQL
 */
function isValidDateMySql($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function calculatePriorityBreakdown(array $beneficiary) {
    $income = (float)($beneficiary['renda_per_capita'] ?? 0);
    $incomeScore = $income <= 150 ? 40 : ($income <= 300 ? 30 : ($income <= 500 ? 20 : ($income <= 800 ? 10 : 0)));

    $employmentScores = [
        'desempregado' => 30,
        'aposentado' => 20,
        'premium' => 15,
        'autoempreendedor' => 10,
        'empregado' => 5,
    ];
    $employmentScore = $employmentScores[$beneficiary['situacao_trabalho'] ?? ''] ?? 0;

    $dependents = (int)($beneficiary['dependentes'] ?? 0);
    $dependentsScore = $dependents >= 4 ? 30 : ($dependents >= 2 ? 20 : ($dependents === 1 ? 10 : 0));

    return [
        'renda' => $incomeScore,
        'trabalho' => $employmentScore,
        'dependentes' => $dependentsScore,
        'total' => $incomeScore + $employmentScore + $dependentsScore,
    ];
}

function recordAuditEvent($action, $table, $recordId = 0, $oldData = null, $newData = null) {
    global $pdo;

    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO audit_log (usuario_id, acao, tabela, registro_id, dados_antigos, dados_novos, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        (int)$_SESSION['user_id'],
        $action,
        $table,
        (int)$recordId,
        $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

function recordStockMovement($itemId, $quantityInitial, $quantityFinal, $type, $guideId = null) {
    global $pdo;

    $quantityMoved = abs((int)$quantityFinal - (int)$quantityInitial);
    if ($quantityMoved === 0) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO itens_guias_estoque (guia_id, item_id, quantidade_inicial, quantidade_final, quantidade_movimentada, tipo_movimentacao) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$guideId, $itemId, $quantityInitial, $quantityFinal, $quantityMoved, $type]);
}

/**
 * Funções gerais da aplicação - Parte 3 (Busca e Estatísticas)
 */

/**
 * Busca os itens do estoque
 */
function getItems($limit = 50, $offset = 0) {
    global $pdo;
    $stmt = $pdo->query("SELECT id, codigo, nome, quantidade, descricao, observacao, created_at, updated_at FROM itens ORDER BY nome LIMIT $limit OFFSET $offset");
    return $stmt->fetchAll();
}

/**
 * Retorna os limites de controle de estoque (em unidades)
 */
function getStockThresholds() {
    return [
        'maximo' => ESTOQUE_LIMITE_MAXIMO,
        'atencao' => ESTOQUE_LIMITE_ATENCAO,
    ];
}

/**
 * Classifica o nível de estoque de um item.
 * - 'limite': atingiu a capacidade máxima (gera aviso de estoque cheio).
 * - 'atencao': no limite de segurança ou abaixo (gera aviso de reposição).
 * - 'normal': dentro da faixa ideal.
 */
function classifyStockLevel($quantidade) {
    $thresholds = getStockThresholds();
    if ((int)$quantidade >= $thresholds['maximo']) {
        return 'limite';
    }
    if ((int)$quantidade <= $thresholds['atencao']) {
        return 'atencao';
    }
    return 'normal';
}

/**
 * Lista os itens que atingiram a capacidade máxima do estoque
 */
function getItensNoLimite($limit = null) {
    global $pdo;
    $sql = "SELECT id, nome, quantidade FROM itens WHERE quantidade >= ? ORDER BY quantidade DESC, nome";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([ESTOQUE_LIMITE_MAXIMO]);
    return $stmt->fetchAll();
}

/**
 * Conta os itens que atingiram a capacidade máxima do estoque
 */
function countItensNoLimite() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE quantidade >= ?");
    $stmt->execute([ESTOQUE_LIMITE_MAXIMO]);
    return (int)$stmt->fetchColumn();
}

/**
 * Conta os itens com estoque em atenção (no limite de segurança ou abaixo)
 */
function countItensEmAtencao() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE quantidade <= ?");
    $stmt->execute([ESTOQUE_LIMITE_ATENCAO]);
    return (int)$stmt->fetchColumn();
}

/**
 * Busca um item pelo ID
 */
function getItemById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM itens WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca as guias de autorização
 */
function getGuias($limit = 50, $offset = 0, $status = null) {
    global $pdo;
    $sql = "SELECT g.*, i.nome as item_nome, u.nome as usuario_nome, b.nome as beneficiario_nome
            FROM guias g
            LEFT JOIN itens i ON g.item_id = i.id
            LEFT JOIN usuarios u ON g.usuario_id = u.id
            LEFT JOIN beneficiarios b ON g.beneficiario_id = b.id";
    $params = [];
    
    if ($status !== null) {
        $sql .= " WHERE g.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY g.data_criacao DESC";
    $sql .= " LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Busca guia pelo ID
 */
function getGuiaById($id) {
    global $pdo;
    $sql = "SELECT g.*, i.nome as item_nome, u.nome as usuario_nome, b.nome as beneficiario_nome, b.cpf as beneficiario_cpf
            FROM guias g
            LEFT JOIN itens i ON g.item_id = i.id
            LEFT JOIN usuarios u ON g.usuario_id = u.id
            LEFT JOIN beneficiarios b ON g.beneficiario_id = b.id
            WHERE g.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca beneficiários
 */
function getBeneficiarios($limit = 50, $offset = 0, $search = '') {
    global $pdo;
    $sql = "SELECT * FROM beneficiarios";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE nome LIKE ? OR cpf LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY nome LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Busca beneficiário pelo ID
 */
function getBeneficiarioById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM beneficiarios WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca usuários
 */
function getUsuarios($limit = 50, $offset = 0, $search = '') {
    global $pdo;
    $sql = "SELECT * FROM usuarios";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE nome LIKE ? OR email LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY nome LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Busca usuário pelo ID
 */
function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca estatísticas do sistema
 */
function getStatistics() {
    global $pdo;
    $stats = [];
    
    // Total de itens
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM itens");
    $stats['total_itens'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de guias
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM guias");
    $stats['total_guias'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Guias ativas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM guias WHERE status = 'ativo'");
    $stats['guias_ativas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de beneficiários
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM beneficiarios");
    $stats['total_beneficiarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Itens em estoque (quantidade > 0)
    $stmt = $pdo->query("SELECT SUM(quantidade) as total FROM itens WHERE quantidade > 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['estoque_disponivel'] = $result['total'] ?? 0;
    
    // Item mais require
    $stmt = $pdo->query("SELECT item_id, COUNT(*) as total FROM guias GROUP BY item_id ORDER BY total DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['item_mais_requerido'] = $result ? $result['item_id'] : null;
    
    return $stats;
}

/**
 * Atualiza a quantidade de um item no estoque
 */
function updateItemQuantidade($itemId, $quantidadeAtual, $quantidadeAdicionar) {
    global $pdo;
    if ((int)$quantidadeAdicionar < 0) {
        return false;
    }
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $stmt = $pdo->prepare("SELECT quantidade FROM itens WHERE id = ? FOR UPDATE");
        $stmt->execute([$itemId]);
        $quantidadeBanco = $stmt->fetchColumn();
        if ($quantidadeBanco === false || (int)$quantidadeBanco !== (int)$quantidadeAtual) {
            throw new RuntimeException('O estoque foi alterado por outra operação.');
        }

        $novaQuantidade = (int)$quantidadeBanco + (int)$quantidadeAdicionar;
        if ($novaQuantidade < 0) {
            throw new RuntimeException('A movimentação não pode deixar o estoque negativo.');
        }

        $stmt = $pdo->prepare("UPDATE itens SET quantidade = ? WHERE id = ?");
        $stmt->execute([$novaQuantidade, $itemId]);
        if ($novaQuantidade !== (int)$quantidadeBanco) {
            $tipo = $novaQuantidade > (int)$quantidadeBanco ? 'entrada' : 'saida';
            recordStockMovement($itemId, (int)$quantidadeBanco, $novaQuantidade, $tipo);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/**
 * Reseta a senha de um usuário
 */
function resetUserPassword($userId, $novoHash) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, senha_resetada = 1, resetado_em = NOW() WHERE id = ?");
    return $stmt->execute([$novoHash, $userId]);
}

/**
 * Exporta dados em CSV
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Cabeçalho
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Dados
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}
