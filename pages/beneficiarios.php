<?php
/**
 * Página de Gestão de Beneficiários
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Beneficiários';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'adicionar' || $action === 'atualizar') {
        $nome = cleanInput($_POST['nome'] ?? '');
        $cpf = formatCPF($_POST['cpf'] ?? '');
        $telefone = formatPhone($_POST['telefone'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $renda = (float)($_POST['renda_per_capita'] ?? 0);
        $situacao = cleanInput($_POST['situacao_trabalho'] ?? 'desempregado');
        $dependentes = max(0, (int)($_POST['dependentes'] ?? 0));
        $endereco = cleanInput($_POST['endereco'] ?? '');
        $bairro = cleanInput($_POST['bairro'] ?? '');
        $cidade = cleanInput($_POST['cidade'] ?? 'Caçapava');
        $estado = strtoupper(substr(cleanInput($_POST['estado'] ?? 'SP'), 0, 2));

        if ($nome === '' || $cpf === false || $telefone === false || $renda < 0 || ($action === 'atualizar' && $id <= 0)) {
            $_SESSION['flash_message'] = 'Confira o nome, CPF, telefone e renda informados.';
            $_SESSION['flash_type'] = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                if ($action === 'adicionar') {
                    $stmt = $pdo->prepare("INSERT INTO beneficiarios (nome, cpf, telefone, email, renda_per_capita, situacao_trabalho, dependentes, endereco, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $cpf, $telefone, $email, $renda, $situacao, $dependentes, $endereco, $bairro, $cidade, $estado]);
                    recordAuditEvent('cadastrar_beneficiario', 'beneficiarios', (int)$pdo->lastInsertId());
                    $_SESSION['flash_message'] = 'Beneficiário cadastrado com sucesso!';
                } else {
                    $stmt = $pdo->prepare("UPDATE beneficiarios SET nome = ?, cpf = ?, telefone = ?, email = ?, renda_per_capita = ?, situacao_trabalho = ?, dependentes = ?, endereco = ?, bairro = ?, cidade = ?, estado = ? WHERE id = ?");
                    $stmt->execute([$nome, $cpf, $telefone, $email, $renda, $situacao, $dependentes, $endereco, $bairro, $cidade, $estado, $id]);
                    recordAuditEvent('atualizar_beneficiario', 'beneficiarios', $id);
                    $_SESSION['flash_message'] = 'Beneficiário atualizado com sucesso!';
                }
                $pdo->commit();
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Falha ao salvar beneficiário: ' . $e->getMessage());
                $sqlState = $e->errorInfo[0] ?? (string)$e->getCode();
                $_SESSION['flash_message'] = $sqlState === '23000'
                    ? 'Este CPF já está cadastrado.'
                    : 'Não foi possível salvar o beneficiário. Confira os dados e tente novamente.';
                $_SESSION['flash_type'] = 'error';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Falha ao registrar beneficiário: ' . $e->getMessage());
                $_SESSION['flash_message'] = 'Não foi possível concluir o cadastro agora. Tente novamente.';
                $_SESSION['flash_type'] = 'error';
            }
        }
    } elseif ($action === 'excluir' && $id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM beneficiarios WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                recordAuditEvent('excluir_beneficiario', 'beneficiarios', $id);
            }
            $_SESSION['flash_message'] = 'Beneficiário excluído com sucesso!';
            $_SESSION['flash_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = 'Não foi possível excluir este beneficiário porque ele possui guias vinculadas.';
            $_SESSION['flash_type'] = 'error';
        }
    }

    header('Location: ' . PAGES_URL . 'beneficiarios.php');
    exit();
}

$search = trim($_GET['search'] ?? '');
$beneficiarios = getBeneficiarios(50, 0, $search);
?>

<div class="page-header">
    <h1><i class="fas fa-user-injured me-2"></i>Beneficiários</h1>
    <p class="subtitle">Cadastro e gestão de famílias e indivíduos em situação de vulnerabilidade</p>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Novo Beneficiário</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="adicionar">
            <div class="col-md-4">
                <label for="nome" class="form-label">Nome completo *</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="col-md-3">
                <label for="cpf" class="form-label">CPF *</label>
                <input type="text" class="form-control" id="cpf" name="cpf" maxlength="14" required>
            </div>
            <div class="col-md-2">
                <label for="telefone" class="form-label">Telefone *</label>
                <input type="tel" class="form-control" id="telefone" name="telefone" maxlength="20" placeholder="(12) 99999-9999 ou +55" required>
            </div>
            <div class="col-md-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email">
            </div>
            <div class="col-md-3">
                <label for="renda_per_capita" class="form-label">Renda per capita (R$) *</label>
                <input type="number" class="form-control" id="renda_per_capita" name="renda_per_capita" min="0" step="0.01" required>
            </div>
            <div class="col-md-3">
                <label for="situacao_trabalho" class="form-label">Situação de trabalho *</label>
                <select class="form-select" id="situacao_trabalho" name="situacao_trabalho" required>
                    <option value="desempregado">Desempregado</option>
                    <option value="empregado">Empregado</option>
                    <option value="autoempreendedor">Autônomo</option>
                    <option value="aposentado">Aposentado</option>
                    <option value="premium">Beneficiário de programa social</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="dependentes" class="form-label">Dependentes menores</label>
                <input type="number" class="form-control" id="dependentes" name="dependentes" min="0" value="0">
            </div>
            <div class="col-md-4">
                <label for="endereco" class="form-label">Endereço</label>
                <input type="text" class="form-control" id="endereco" name="endereco">
            </div>
            <div class="col-md-3">
                <label for="bairro" class="form-label">Bairro</label>
                <input type="text" class="form-control" id="bairro" name="bairro">
            </div>
            <div class="col-md-3">
                <label for="cidade" class="form-label">Cidade</label>
                <input type="text" class="form-control" id="cidade" name="cidade" value="">
            </div>
            <div class="col-md-2">
                <label for="estado" class="form-label">UF</label>
                <input type="text" class="form-control" id="estado" name="estado" maxlength="2" value="SP">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar beneficiário</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Beneficiários cadastrados</h5>
        <span class="badge bg-info"><?php echo count($beneficiarios); ?> registros</span>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="search" class="form-control" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Buscar por nome ou CPF">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search me-1"></i>Buscar</button>
                <a class="btn btn-outline-secondary" href="<?php echo PAGES_URL; ?>beneficiarios.php">Limpar</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr><th>Nome</th><th>CPF</th><th>Telefone</th><th>Renda</th><th>Situação</th><th>Dependentes menores</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($beneficiarios as $beneficiario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($beneficiario['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($beneficiario['cpf'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($beneficiario['telefone'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo formatCurrency($beneficiario['renda_per_capita']); ?></td>
                        <td><?php echo htmlspecialchars($beneficiario['situacao_trabalho'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$beneficiario['dependentes']; ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editBeneficiarioModal"
                                data-id="<?php echo (int)$beneficiario['id']; ?>"
                                data-nome="<?php echo htmlspecialchars($beneficiario['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-cpf="<?php echo htmlspecialchars($beneficiario['cpf'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-telefone="<?php echo htmlspecialchars($beneficiario['telefone'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-email="<?php echo htmlspecialchars($beneficiario['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-renda="<?php echo htmlspecialchars($beneficiario['renda_per_capita'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-situacao="<?php echo htmlspecialchars($beneficiario['situacao_trabalho'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-dependentes="<?php echo (int)$beneficiario['dependentes']; ?>"
                                data-endereco="<?php echo htmlspecialchars($beneficiario['endereco'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-bairro="<?php echo htmlspecialchars($beneficiario['bairro'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-cidade="<?php echo htmlspecialchars($beneficiario['cidade'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-estado="<?php echo htmlspecialchars($beneficiario['estado'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-edit"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este beneficiário?')">
                                <input type="hidden" name="action" value="excluir">
                                <input type="hidden" name="id" value="<?php echo (int)$beneficiario['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$beneficiarios): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Nenhum beneficiário encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editBeneficiarioModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar beneficiário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="action" value="atualizar">
                    <input type="hidden" name="id" id="editId">
                    <div class="col-md-6"><label class="form-label" for="editNome">Nome</label><input class="form-control" id="editNome" name="nome" required></div>
                    <div class="col-md-3"><label class="form-label" for="editCpf">CPF</label><input class="form-control" id="editCpf" name="cpf" required></div>
                    <div class="col-md-3"><label class="form-label" for="editTelefone">Telefone</label><input class="form-control" id="editTelefone" name="telefone" required></div>
                    <div class="col-md-4"><label class="form-label" for="editEmail">Email</label><input class="form-control" id="editEmail" name="email" type="email"></div>
                    <div class="col-md-3"><label class="form-label" for="editRenda">Renda per capita</label><input class="form-control" id="editRenda" name="renda_per_capita" type="number" min="0" step="0.01" required></div>
                    <div class="col-md-3"><label class="form-label" for="editSituacao">Situação</label><select class="form-select" id="editSituacao" name="situacao_trabalho"><option value="desempregado">Desempregado</option><option value="empregado">Empregado</option><option value="autoempreendedor">Autônomo</option><option value="aposentado">Aposentado</option><option value="premium">Beneficiário de programa social</option></select></div>
                    <div class="col-md-2"><label class="form-label" for="editDependentes">Dependentes menores</label><input class="form-control" id="editDependentes" name="dependentes" type="number" min="0"></div>
                    <div class="col-md-5"><label class="form-label" for="editEndereco">Endereço</label><input class="form-control" id="editEndereco" name="endereco"></div>
                    <div class="col-md-3"><label class="form-label" for="editBairro">Bairro</label><input class="form-control" id="editBairro" name="bairro"></div>
                    <div class="col-md-2"><label class="form-label" for="editCidade">Cidade</label><input class="form-control" id="editCidade" name="cidade"></div>
                    <div class="col-md-2"><label class="form-label" for="editEstado">UF</label><input class="form-control" id="editEstado" name="estado" maxlength="2"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Salvar alterações</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editBeneficiarioModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    ['id', 'nome', 'cpf', 'telefone', 'email', 'renda', 'situacao', 'dependentes', 'endereco', 'bairro', 'cidade', 'estado'].forEach(function (key) {
        const field = document.getElementById(key === 'id' ? 'editId' : 'edit' + key.charAt(0).toUpperCase() + key.slice(1));
        const datasetKey = key === 'situacao' ? 'situacao' : key;
        field.value = button.dataset[datasetKey] || '';
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';
