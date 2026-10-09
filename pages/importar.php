<?php
/**
 * Importação e saneamento de beneficiários em CSV
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Importar beneficiários';

if (isset($_GET['modelo'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="modelo-beneficiarios.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['nome', 'cpf', 'telefone', 'email', 'renda_per_capita', 'situacao_trabalho', 'dependentes_menores', 'endereco', 'bairro', 'cidade', 'estado'], ';');
    fputcsv($output, ['Nome Exemplo', '12345678901', '11999990000', 'exemplo@email.com', '250,00', 'desempregado', '2', 'Rua Exemplo, 10', 'Centro', 'Caçapava', 'SP'], ';');
    fclose($output);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importados = 0;
    $ignorados = 0;
    $erros = [];
    $arquivo = $_FILES['arquivo_csv'] ?? null;

    if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK || $arquivo['size'] > 5 * 1024 * 1024 || !is_uploaded_file($arquivo['tmp_name'])) {
        $_SESSION['import_summary'] = ['mensagem' => 'Envie um CSV válido de até 5 MB.', 'erros' => []];
    } else {
        $handle = fopen($arquivo['tmp_name'], 'r');
        if (!$handle) {
            $_SESSION['import_summary'] = ['mensagem' => 'Não foi possível ler o arquivo enviado.', 'erros' => []];
        } else {
            $primeiraLinha = fgets($handle);
            $delimitador = substr_count($primeiraLinha, ';') >= substr_count($primeiraLinha, ',') ? ';' : ',';
            rewind($handle);
            $cabecalhos = fgetcsv($handle, 0, $delimitador);
            if ($cabecalhos && isset($cabecalhos[0])) {
                $cabecalhos[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecalhos[0]);
            }

            $normalizar = function ($valor) {
                $valor = trim((string)$valor);
                if (function_exists('mb_convert_encoding') && !mb_check_encoding($valor, 'UTF-8')) {
                    $valor = mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
                }
                $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
                $valor = $ascii === false ? $valor : $ascii;
                return trim(strtolower(preg_replace('/[^a-z0-9]+/', '_', $valor)), '_');
            };

            $aliases = [
                'nome' => 'nome', 'name' => 'nome',
                'cpf' => 'cpf', 'documento' => 'cpf',
                'telefone' => 'telefone', 'celular' => 'telefone', 'phone' => 'telefone',
                'email' => 'email',
                'renda' => 'renda_per_capita', 'renda_per_capita' => 'renda_per_capita', 'renda_familiar_per_capita' => 'renda_per_capita',
                'situacao' => 'situacao_trabalho', 'situacao_trabalho' => 'situacao_trabalho',
                'dependentes' => 'dependentes', 'numero_de_dependentes' => 'dependentes',
                'dependentes_menores' => 'dependentes', 'filhos_menores' => 'dependentes', 'numero_de_filhos_menores' => 'dependentes',
                'endereco' => 'endereco', 'bairro' => 'bairro', 'cidade' => 'cidade', 'estado' => 'estado', 'uf' => 'estado',
            ];
            $indices = [];
            foreach ($cabecalhos ?: [] as $indice => $cabecalho) {
                $nomeCabecalho = $normalizar($cabecalho);
                if (isset($aliases[$nomeCabecalho])) {
                    $indices[$aliases[$nomeCabecalho]] = $indice;
                }
            }

            $obrigatorios = ['nome', 'cpf', 'telefone'];
            $faltantes = array_diff($obrigatorios, array_keys($indices));
            if ($faltantes) {
                $_SESSION['import_summary'] = ['mensagem' => 'Cabeçalho inválido. São obrigatórias as colunas nome, cpf e telefone.', 'erros' => []];
                fclose($handle);
            } else {
                $insert = $pdo->prepare("INSERT IGNORE INTO beneficiarios (nome, cpf, telefone, email, renda_per_capita, situacao_trabalho, dependentes, endereco, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $linha = 1;
                try {
                    $pdo->beginTransaction();
                    while (($colunas = fgetcsv($handle, 0, $delimitador)) !== false) {
                        $linha++;
                        if ($colunas === [null] || count(array_filter($colunas, function ($valor) { return trim((string)$valor) !== ''; })) === 0) {
                            continue;
                        }

                        $ler = function ($campo, $padrao = '') use ($indices, $colunas) {
                            $valor = isset($indices[$campo]) ? trim((string)($colunas[$indices[$campo]] ?? $padrao)) : $padrao;
                            if (function_exists('mb_convert_encoding') && !mb_check_encoding($valor, 'UTF-8')) {
                                $valor = mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
                            }
                            return trim($valor);
                        };
                        $nome = $ler('nome');
                        $cpf = formatCPF($ler('cpf'));
                        $telefone = formatPhone($ler('telefone'));
                        $email = $ler('email');
                        $rendaTexto = $ler('renda_per_capita', '0');
                        $rendaTexto = strpos($rendaTexto, ',') !== false ? str_replace(',', '.', str_replace('.', '', $rendaTexto)) : $rendaTexto;
                        $situacaoOriginal = $normalizar($ler('situacao_trabalho', 'desempregado'));
                        $situacoesMapeadas = [
                            'desempregado' => 'desempregado',
                            'empregado' => 'empregado',
                            'autonomo' => 'autoempreendedor',
                            'autoempreendedor' => 'autoempreendedor',
                            'aposentado' => 'aposentado',
                            'premium' => 'premium',
                            'beneficiario_de_programa_social' => 'premium',
                        ];
                        $situacao = $situacoesMapeadas[$situacaoOriginal] ?? $situacaoOriginal;
                        $situacoes = array_values(array_unique($situacoesMapeadas));
                        $dependentesTexto = $ler('dependentes', '0');
                        $dependentes = filter_var($dependentesTexto, FILTER_VALIDATE_INT);
                        $renda = is_numeric($rendaTexto) ? (float)$rendaTexto : -1;

                        if ($nome === '' || $cpf === false || $telefone === false || $renda < 0 || $dependentes === false || $dependentes < 0 || !in_array($situacao, $situacoes, true) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                            if (count($erros) < 20) {
                                $erros[] = 'Linha ' . $linha . ': dados inválidos ou incompletos.';
                            }
                            $ignorados++;
                            continue;
                        }

                        $insert->execute([
                            $nome,
                            $cpf,
                            $telefone,
                            $email === '' ? null : $email,
                            $renda,
                            $situacao,
                            $dependentes,
                            $ler('endereco') ?: null,
                            $ler('bairro') ?: null,
                            $ler('cidade', 'Caçapava') ?: 'Caçapava',
                            strtoupper(substr($ler('estado', 'SP') ?: 'SP', 0, 2)),
                        ]);

                        if ($insert->rowCount() === 1) {
                            $importados++;
                        } else {
                            $ignorados++;
                        }
                    }

                    recordAuditEvent('importar_beneficiarios', 'beneficiarios', 0, null, [
                        'importados' => $importados,
                        'ignorados' => $ignorados,
                        'versao_etl' => 1,
                    ]);
                    $pdo->commit();
                    $_SESSION['import_summary'] = [
                        'mensagem' => $importados . ' cadastro(s) importado(s); ' . $ignorados . ' linha(s) ignorada(s).',
                        'erros' => $erros,
                    ];
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['import_summary'] = ['mensagem' => 'A importação foi cancelada por um erro no arquivo.', 'erros' => []];
                }
                fclose($handle);
            }
        }
    }

    header('Location: ' . PAGES_URL . 'importar.php');
    exit();
}

$resumo = $_SESSION['import_summary'] ?? null;
unset($_SESSION['import_summary']);
?>

<div class="page-header">
    <h1><i class="fas fa-file-import me-2"></i>Importação de beneficiários</h1>
    <p class="subtitle">Saneamento e carga de cadastros históricos em CSV</p>
</div>

<?php if ($resumo): ?>
<div class="alert <?php echo $resumo['erros'] ? 'alert-warning' : 'alert-success'; ?>" role="status">
    <p class="mb-1"><?php echo htmlspecialchars($resumo['mensagem'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php foreach ($resumo['erros'] as $erro): ?><div><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2 class="h5 mb-0">Carregar planilha</h2></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-8"><label class="form-label" for="arquivo_csv">Arquivo CSV</label><input class="form-control" id="arquivo_csv" name="arquivo_csv" type="file" accept=".csv,text/csv" required></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary" type="submit"><i class="fas fa-upload me-2"></i>Validar e importar</button></div>
        </form>
        <hr>
        <a class="btn btn-outline-secondary" href="<?php echo PAGES_URL; ?>importar.php?modelo=csv"><i class="fas fa-download me-2"></i>Baixar modelo CSV</a>
        <p class="text-muted small mt-3 mb-0">Limite de 5 MB. CPFs duplicados são ignorados. Linhas inválidas são rejeitadas sem registrar dados pessoais na auditoria.</p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';
