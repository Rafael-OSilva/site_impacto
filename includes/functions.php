<?php
// Função para calcular vendas do dia (ATUALIZADA)
function calcularVendasHoje($connection, $data)
{
    try {
        $query = "SELECT COUNT(*) as quantidade, SUM(valor_total) as total 
                  FROM vendas 
                  WHERE DATE(data_venda) = :data 
                  AND status = 'concluida'";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':data', $data);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['quantidade' => 0, 'total' => 0];
    }
}

// FUNÇÃO CORRIGIDA: Obter saldo inicial
function obterSaldoInicial($connection, $data)
{
    try {
        // Primeiro tenta buscar do caixa fechado da data específica
        $query = "SELECT valor_inicial FROM caixa 
                  WHERE DATE(data_abertura) = :data 
                  AND status = 'fechado'
                  ORDER BY id DESC LIMIT 1";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':data', $data);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['valor_inicial']) {
            return floatval($result['valor_inicial']);
        }

        // Se não encontrou, busca do caixa aberto atual (para relatórios do dia atual)
        $query_aberto = "SELECT valor_inicial FROM caixa 
                        WHERE status = 'aberto' 
                        ORDER BY id DESC LIMIT 1";
        $stmt_aberto = $connection->prepare($query_aberto);
        $stmt_aberto->execute();
        $result_aberto = $stmt_aberto->fetch(PDO::FETCH_ASSOC);

        if ($result_aberto && $result_aberto['valor_inicial']) {
            return floatval($result_aberto['valor_inicial']);
        }

        // Se ainda não encontrou, busca o saldo final do dia anterior
        $data_anterior = date('Y-m-d', strtotime($data . ' -1 day'));
        $query_anterior = "SELECT valor_final FROM caixa 
                          WHERE DATE(data_fechamento) = :data_anterior 
                          AND status = 'fechado'
                          ORDER BY id DESC LIMIT 1";
        $stmt_anterior = $connection->prepare($query_anterior);
        $stmt_anterior->bindParam(':data_anterior', $data_anterior);
        $stmt_anterior->execute();
        $result_anterior = $stmt_anterior->fetch(PDO::FETCH_ASSOC);

        if ($result_anterior && $result_anterior['valor_final']) {
            return floatval($result_anterior['valor_final']);
        }

        return 0.00;
    } catch (PDOException $e) {
        error_log("Erro ao obter saldo inicial: " . $e->getMessage());
        return 0.00;
    }
}

// Função para obter últimas vendas (ATUALIZADA)
function obterUltimasVendas($connection, $limite = 5)
{
    try {
        $query = "SELECT v.*, fp.nome as forma_pagamento 
                  FROM vendas v
                  LEFT JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                  WHERE (v.status = 'concluida' OR v.status = '' OR v.status IS NULL) 
                  ORDER BY data_venda DESC 
                  LIMIT :limite";
        $stmt = $connection->prepare($query);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Função para verificar status do caixa
function verificarStatusCaixa($connection)
{
    try {
        $query = "SELECT status FROM caixa 
                  ORDER BY id DESC LIMIT 1";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['status'] == 'aberto';
    } catch (PDOException $e) {
        return false;
    }
}

// Função para formatar moeda
function formatarMoeda($valor)
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para verificar login
function verificarLogin()
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../login.php');
        exit;
    }
}

function abrirCaixa($connection, $usuario_id, $valor_inicial, $observacao = '')
{
    try {
        // Primeiro verificar se já existe caixa aberto
        $query_verificar = "SELECT id FROM caixa WHERE status = 'aberto'";
        $stmt_verificar = $connection->prepare($query_verificar);
        $stmt_verificar->execute();

        if ($stmt_verificar->rowCount() > 0) {
            return false; // Já existe caixa aberto
        }

        // Inserir novo registro de caixa aberto
        $query = "INSERT INTO caixa (data_abertura, usuario_id, valor_inicial, observacao) 
                  VALUES (NOW(), :usuario_id, :valor_inicial, :observacao)";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':valor_inicial', $valor_inicial);
        $stmt->bindParam(':observacao', $observacao);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao abrir caixa: " . $e->getMessage());
        return false;
    }
}

// Função para obter formas de pagamento
function obterFormasPagamento($connection)
{
    try {
        $query = "SELECT * FROM formas_pagamento WHERE ativo = 1 ORDER BY nome";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter formas de pagamento: " . $e->getMessage());
        return [];
    }
}

// FUNÇÃO ATUALIZADA: Registrar uma venda
function registrarVenda($connection, $caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $status = 'concluida')
{
    try {
        // Se for "A Receber" (ID 5 ou 7), deixar status vazio
        if ($forma_pagamento_id == 5 || $forma_pagamento_id == 7) {
            $status = ''; // Status vazio para vendas a receber
        }

        $sql = "INSERT INTO vendas (caixa_id, usuario_id, data_venda, valor_total, forma_pagamento_id, descricao, status, visivel_contas_receber) 
                VALUES (?, ?, NOW(), ?, ?, ?, ?, 1)";

        $stmt = $connection->prepare($sql);
        $stmt->execute([$caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $status]);

        return $connection->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erro ao registrar venda: " . $e->getMessage());
        return false;
    }
}

// Função para obter o próximo número de venda
function obterProximoNumeroVenda($connection)
{
    try {
        $query = "SELECT MAX(id) as ultimo_id FROM vendas";
        $stmt = $connection->prepare($query);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['ultimo_id'] ?? 0) + 1;
    } catch (PDOException $e) {
        error_log("Erro ao obter próximo número de venda: " . $e->getMessage());
        return 1;
    }
}

function obterCaixaAberto($connection)
{
    try {
        $query = "SELECT * FROM caixa WHERE status = 'aberto' ORDER BY id DESC LIMIT 1";
        $stmt = $connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter caixa aberto: " . $e->getMessage());
        return null;
    }
}

function registrarRetirada($connection, $caixa_id, $usuario_id, $valor, $motivo)
{
    try {
        $query = "INSERT INTO retiradas (caixa_id, usuario_id, data_retirada, valor, motivo) 
                  VALUES (:caixa_id, :usuario_id, NOW(), :valor, :motivo)";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':caixa_id', $caixa_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':valor', $valor);
        $stmt->bindParam(':motivo', $motivo);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao registrar retirada: " . $e->getMessage());
        return false;
    }
}

function excluirRetirada($connection, $retirada_id, $usuario_id)
{
    try {
        $query = "DELETE FROM retiradas WHERE id = :id";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':id', $retirada_id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao excluir retirada (ID: $retirada_id) por usuário (ID: $usuario_id): " . $e->getMessage());
        return false;
    }
}

function obterRetiradasDoCaixaAberto($connection, $caixa_id)
{
    try {
        $query = "SELECT r.*, u.nome as nome_usuario 
                  FROM retiradas r
                  JOIN usuarios u ON r.usuario_id = u.id
                  WHERE r.caixa_id = :caixa_id 
                  ORDER BY r.data_retirada DESC";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':caixa_id', $caixa_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter retiradas: " . $e->getMessage());
        return [];
    }
}

// Função para calcular resumo do caixa
function calcularResumoCaixaAberto($connection, $caixa_id)
{
    $resumo = [
        'valor_inicial' => 0,
        'vendas_dinheiro' => 0,
        'vendas_a_receber' => 0,
        'total_retiradas' => 0,
        'saldo_disponivel' => 0,
        'total_vendas_concluidas' => 0
    ];

    try {
        // 1. Valor inicial
        $caixa = obterCaixaAberto($connection);
        $resumo['valor_inicial'] = $caixa['valor_inicial'] ?? 0;

        // 2. Vendas em Dinheiro (ID 1) - APENAS CONCLUÍDAS
        $query_vendas_dinheiro = "SELECT SUM(valor_total) as total FROM vendas 
                         WHERE caixa_id = :caixa_id AND forma_pagamento_id = 1 AND status = 'concluida'";
        $stmt_vendas_dinheiro = $connection->prepare($query_vendas_dinheiro);
        $stmt_vendas_dinheiro->bindParam(':caixa_id', $caixa_id);
        $stmt_vendas_dinheiro->execute();
        $total_vendas_dinheiro = $stmt_vendas_dinheiro->fetch(PDO::FETCH_ASSOC);
        $resumo['vendas_dinheiro'] = $total_vendas_dinheiro['total'] ?? 0;

        // 3. Vendas "A Receber" 
        $query_vendas_a_receber = "SELECT SUM(valor_total) as total FROM vendas 
                         WHERE caixa_id = :caixa_id AND (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                         AND (status = '' OR status IS NULL OR status = 'a_receber')";
        $stmt_vendas_a_receber = $connection->prepare($query_vendas_a_receber);
        $stmt_vendas_a_receber->bindParam(':caixa_id', $caixa_id);
        $stmt_vendas_a_receber->execute();
        $total_vendas_a_receber = $stmt_vendas_a_receber->fetch(PDO::FETCH_ASSOC);
        $resumo['vendas_a_receber'] = $total_vendas_a_receber['total'] ?? 0;

        // 4. Total de Retiradas
        $query_retiradas = "SELECT SUM(valor) as total FROM retiradas WHERE caixa_id = :caixa_id";
        $stmt_retiradas = $connection->prepare($query_retiradas);
        $stmt_retiradas->bindParam(':caixa_id', $caixa_id);
        $stmt_retiradas->execute();
        $total_retiradas = $stmt_retiradas->fetch(PDO::FETCH_ASSOC);
        $resumo['total_retiradas'] = $total_retiradas['total'] ?? 0;

        // 5. Total de vendas concluídas
        $query_total_concluidas = "SELECT SUM(valor_total) as total FROM vendas 
                         WHERE caixa_id = :caixa_id AND status = 'concluida'";
        $stmt_total_concluidas = $connection->prepare($query_total_concluidas);
        $stmt_total_concluidas->bindParam(':caixa_id', $caixa_id);
        $stmt_total_concluidas->execute();
        $total_concluidas = $stmt_total_concluidas->fetch(PDO::FETCH_ASSOC);
        $resumo['total_vendas_concluidas'] = $total_concluidas['total'] ?? 0;

        // 6. Saldo disponível em dinheiro
        $resumo['saldo_disponivel'] = $resumo['valor_inicial'] + $resumo['vendas_dinheiro'] - $resumo['total_retiradas'];

        return $resumo;
    } catch (PDOException $e) {
        error_log("Erro ao calcular resumo do caixa: " . $e->getMessage());
        return $resumo;
    }
}

// Função para relatório de vendas
function obterRelatorioVendas($connection, $data_inicial, $data_final, $incluir_a_receber = false)
{
    $relatorio = [
        'vendas' => [],
        'resumo_pagamentos' => [],
        'total_geral' => 0,
        'quantidade_vendas' => 0,
        'total_a_receber' => 0
    ];

    try {
        $data_final_ajustada = $data_final . ' 23:59:59';

        // Condições de status
        $condicoes_status = "v.status = 'concluida'";
        if ($incluir_a_receber) {
            $condicoes_status = "(v.status = 'concluida' OR v.status = 'a_receber' OR v.status = '' OR v.status IS NULL)";
        }

        // 1. Detalhamento das vendas
        $query_vendas = "SELECT v.*, fp.nome as forma_pagamento, u.nome as nome_usuario
                         FROM vendas v
                         JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                         JOIN usuarios u ON v.usuario_id = u.id
                         WHERE v.data_venda BETWEEN :data_inicial AND :data_final
                         AND $condicoes_status
                         ORDER BY v.data_venda DESC";
        $stmt_vendas = $connection->prepare($query_vendas);
        $stmt_vendas->bindParam(':data_inicial', $data_inicial);
        $stmt_vendas->bindParam(':data_final', $data_final_ajustada);
        $stmt_vendas->execute();
        $relatorio['vendas'] = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
        $relatorio['quantidade_vendas'] = count($relatorio['vendas']);

        // 2. Resumo por forma de pagamento
        $query_resumo = "SELECT fp.nome, COUNT(v.id) as quantidade, SUM(v.valor_total) as total
                         FROM vendas v
                         JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                         WHERE v.data_venda BETWEEN :data_inicial AND :data_final
                         AND $condicoes_status
                         GROUP BY fp.nome
                         ORDER BY total DESC";
        $stmt_resumo = $connection->prepare($query_resumo);
        $stmt_resumo->bindParam(':data_inicial', $data_inicial);
        $stmt_resumo->bindParam(':data_final', $data_final_ajustada);
        $stmt_resumo->execute();
        $relatorio['resumo_pagamentos'] = $stmt_resumo->fetchAll(PDO::FETCH_ASSOC);

        // 3. Total Geral
        foreach ($relatorio['resumo_pagamentos'] as $resumo) {
            $relatorio['total_geral'] += $resumo['total'];
        }

        // 4. Calcular total a receber separadamente
        $query_a_receber = "SELECT SUM(valor_total) as total 
                           FROM vendas 
                           WHERE data_venda BETWEEN :data_inicial AND :data_final
                           AND (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                           AND (status = '' OR status IS NULL OR status = 'a_receber')";
        $stmt_a_receber = $connection->prepare($query_a_receber);
        $stmt_a_receber->bindParam(':data_inicial', $data_inicial);
        $stmt_a_receber->bindParam(':data_final', $data_final_ajustada);
        $stmt_a_receber->execute();
        $total_a_receber = $stmt_a_receber->fetch(PDO::FETCH_ASSOC);
        $relatorio['total_a_receber'] = $total_a_receber['total'] ?? 0;

        return $relatorio;
    } catch (PDOException $e) {
        error_log("Erro ao obter relatório de vendas: " . $e->getMessage());
        return $relatorio;
    }
}

// FUNÇÃO CORRIGIDA: Obter contas a receber
function obterContasAReceber($connection, $filtros = [])
{
    try {
        // Buscar vendas onde forma_pagamento_id é "A Receber" (5 ou 7)
        // E status está vazio/NULL (pendente) E são visíveis
        $where_conditions = ["(v.forma_pagamento_id = 5 OR v.forma_pagamento_id = 7)"];
        $where_conditions[] = "(v.status = '' OR v.status IS NULL)";
        $where_conditions[] = "v.visivel_contas_receber = 1";

        $params = [];

        // Aplicar filtros
        if (!empty($filtros['cliente'])) {
            $where_conditions[] = "v.descricao LIKE ?";
            $params[] = "%" . $filtros['cliente'] . "%";
        }

        if (!empty($filtros['data'])) {
            $where_conditions[] = "DATE(v.data_venda) = ?";
            $params[] = $filtros['data'];
        }

        if (!empty($filtros['valor'])) {
            switch ($filtros['valor']) {
                case 'menor100':
                    $where_conditions[] = "v.valor_total < 100";
                    break;
                case '100a500':
                    $where_conditions[] = "v.valor_total BETWEEN 100 AND 500";
                    break;
                case 'maior500':
                    $where_conditions[] = "v.valor_total > 500";
                    break;
            }
        }

        $where_sql = implode(' AND ', $where_conditions);

        $query = "SELECT v.*, fp.nome as forma_pagamento, u.nome as operador 
                  FROM vendas v 
                  INNER JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id 
                  INNER JOIN usuarios u ON v.usuario_id = u.id 
                  WHERE $where_sql
                  ORDER BY v.data_venda DESC";

        error_log("Query contas a receber: " . $query);
        error_log("Parâmetros: " . implode(', ', $params));

        $stmt = $connection->prepare($query);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Contas a receber encontradas: " . count($resultados));

        return $resultados;
    } catch (PDOException $e) {
        error_log("Erro ao obter contas a receber: " . $e->getMessage());
        return [];
    }
}

// FUNÇÃO CORRIGIDA: Receber pagamento de conta
function receberPagamentoConta($connection, $venda_id, $usuario_id)
{
    try {
        // Verificar se a venda existe e está pendente (status vazio ou NULL)
        $query_verificar = "SELECT * FROM vendas 
                           WHERE id = ? 
                           AND (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                           AND (status = '' OR status IS NULL)";
        $stmt_verificar = $connection->prepare($query_verificar);
        $stmt_verificar->execute([$venda_id]);
        $venda = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

        if (!$venda) {
            error_log("Venda não encontrada ou já foi recebida: " . $venda_id);
            return false;
        }

        // Atualizar status para 'concluida' e marcar como não visível
        $query = "UPDATE vendas SET 
                    status = 'concluida', 
                    data_recebimento = NOW(),
                    visivel_contas_receber = 0 
                  WHERE id = ?";

        $stmt = $connection->prepare($query);
        $result = $stmt->execute([$venda_id]);

        if ($result && $stmt->rowCount() > 0) {
            error_log("Venda #" . $venda_id . " marcada como recebida e oculta");

            // Registrar entrada no caixa se houver caixa aberto
            $caixa_aberto = obterCaixaAberto($connection);
            if ($caixa_aberto) {
                registrarEntradaCaixa($connection, $venda['valor_total'], $usuario_id, "Recebimento conta - Venda #" . $venda_id);
                error_log("Entrada registrada no caixa para venda #" . $venda_id);
            }

            return true;
        }

        error_log("Nenhuma linha afetada ao atualizar venda #" . $venda_id);
        return false;
    } catch (PDOException $e) {
        error_log("Erro ao receber pagamento da conta #" . $venda_id . ": " . $e->getMessage());
        return false;
    }
}

function obterRelatorioVendasPorCaixa($connection, $caixa_id)
{
    $relatorio = [
        'vendas' => [],
        'resumo_pagamentos' => [],
        'total_geral' => 0,
        'total_a_receber' => 0
    ];

    try {
        // Detalhamento
        $query_vendas = "SELECT v.*, fp.nome as forma_pagamento
                         FROM vendas v
                         JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                         WHERE v.caixa_id = :caixa_id 
                         AND (v.status = 'concluida' OR v.status = 'a_receber' OR v.status = '' OR v.status IS NULL)
                         ORDER BY v.data_venda DESC";
        $stmt_vendas = $connection->prepare($query_vendas);
        $stmt_vendas->bindParam(':caixa_id', $caixa_id);
        $stmt_vendas->execute();
        $relatorio['vendas'] = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);

        // Resumo
        $query_resumo = "SELECT fp.nome, COUNT(v.id) as quantidade, SUM(v.valor_total) as total
                         FROM vendas v
                         JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                         WHERE v.caixa_id = :caixa_id AND v.status = 'concluida'
                         GROUP BY fp.nome ORDER BY total DESC";
        $stmt_resumo = $connection->prepare($query_resumo);
        $stmt_resumo->bindParam(':caixa_id', $caixa_id);
        $stmt_resumo->execute();
        $relatorio['resumo_pagamentos'] = $stmt_resumo->fetchAll(PDO::FETCH_ASSOC);

        // Total Geral (apenas concluídas)
        foreach ($relatorio['resumo_pagamentos'] as $resumo) {
            $relatorio['total_geral'] += $resumo['total'];
        }

        // Total a Receber
        $query_a_receber = "SELECT SUM(valor_total) as total 
                           FROM vendas 
                           WHERE caixa_id = :caixa_id 
                           AND (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                           AND (status = '' OR status IS NULL OR status = 'a_receber')";
        $stmt_a_receber = $connection->prepare($query_a_receber);
        $stmt_a_receber->bindParam(':caixa_id', $caixa_id);
        $stmt_a_receber->execute();
        $total_a_receber = $stmt_a_receber->fetch(PDO::FETCH_ASSOC);
        $relatorio['total_a_receber'] = $total_a_receber['total'] ?? 0;

        return $relatorio;
    } catch (PDOException $e) {
        error_log("Erro ao obter relatório do caixa: " . $e->getMessage());
        return $relatorio;
    }
}

function finalizarFechamentoCaixa($connection, $caixa_id, $usuario_id, $valor_final_informado, $observacoes)
{
    try {
        $resumo_caixa = calcularResumoCaixaAberto($connection, $caixa_id);
        $saldo_esperado = $resumo_caixa['saldo_disponivel'];
        $diferenca = $valor_final_informado - $saldo_esperado;

        $query = "UPDATE caixa SET 
                    data_fechamento = NOW(),
                    usuario_fechamento = :usuario_fechamento,
                    valor_final = :valor_final,
                    diferenca = :diferenca,
                    observacoes_fechamento = :observacoes,
                    status = 'fechado'
                  WHERE id = :caixa_id AND status = 'aberto'";

        $stmt = $connection->prepare($query);
        $stmt->bindParam(':usuario_fechamento', $usuario_id);
        $stmt->bindParam(':valor_final', $valor_final_informado);
        $stmt->bindParam(':diferenca', $diferenca);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':caixa_id', $caixa_id);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao finalizar fechamento do caixa: " . $e->getMessage());
        return false;
    }
}

// Função para obter estatísticas de contas a receber
function obterEstatisticasContasAReceber($connection)
{
    try {
        $query = "SELECT 
                    COUNT(*) as total_contas,
                    SUM(valor_total) as valor_total,
                    AVG(valor_total) as media_valor,
                    MIN(valor_total) as menor_valor,
                    MAX(valor_total) as maior_valor,
                    MIN(data_venda) as data_mais_antiga
                  FROM vendas 
                  WHERE (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                  AND (status = '' OR status IS NULL OR status = 'a_receber')";

        $stmt = $connection->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas de contas a receber: " . $e->getMessage());
        return [];
    }
}

// FUNÇÃO ATUALIZADA: Gerar relatório PDF (inclui todas as contas, não apenas visíveis)
function gerarRelatorioContasAReceberPDF($connection, $filtros = [])
{
    require_once('../lib/tcpdf/tcpdf.php');

    // Buscar TODAS as contas a receber (sem filtro de visibilidade)
    $contas_receber = obterTodasContasAReceber($connection, $filtros);
    $total_a_receber = 0;

    foreach ($contas_receber as $conta) {
        $total_a_receber += $conta['valor_total'];
    }

    // Criar PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Caixa Impacto');
    $pdf->SetAuthor('Sistema Caixa Impacto');
    $pdf->SetTitle('Relatório de Contas a Receber');
    $pdf->AddPage();

    // Conteúdo do PDF
    $html = '
    <h1 style="text-align: center;">Relatório de Contas a Receber</h1>
    <p style="text-align: center;">Emitido em: ' . date('d/m/Y H:i') . '</p>
    <p><strong>Total de Contas:</strong> ' . count($contas_receber) . '</p>
    <p><strong>Valor Total a Receber:</strong> R$ ' . number_format($total_a_receber, 2, ',', '.') . '</p>
    ';

    if (!empty($contas_receber)) {
        $html .= '
        <table border="1" cellpadding="4" style="font-size: 10px;">
            <tr>
                <th>Data</th>
                <th>Nº Venda</th>
                <th>Valor</th>
                <th>Cliente</th>
                <th>Operador</th>
                <th>Status</th>
            </tr>
        ';

        foreach ($contas_receber as $conta) {
            $descricao = $conta['descricao'] ?? '';
            $nome_cliente = 'Cliente não informado';

            if (strpos($descricao, 'Cliente:') !== false) {
                $parts = explode('|', $descricao);
                $nome_cliente = trim(str_replace('Cliente:', '', $parts[0]));
            } else {
                $nome_cliente = $descricao;
            }

            $status = ($conta['status'] == 'concluida') ? 'RECEBIDA' : 'PENDENTE';
            $cor_status = ($conta['status'] == 'concluida') ? '#28a745' : '#dc3545';

            $html .= '
            <tr>
                <td>' . date('d/m/Y', strtotime($conta['data_venda'])) . '</td>
                <td>#' . $conta['id'] . '</td>
                <td>R$ ' . number_format($conta['valor_total'], 2, ',', '.') . '</td>
                <td>' . htmlspecialchars($nome_cliente) . '</td>
                <td>' . htmlspecialchars($conta['operador']) . '</td>
                <td style="color: ' . $cor_status . '; font-weight: bold;">' . $status . '</td>
            </tr>
            ';
        }

        $html .= '</table>';
    } else {
        $html .= '<p>Nenhuma conta a receber encontrada.</p>';
    }

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('contas_receber_' . date('Y-m-d') . '.pdf', 'I');
    exit;
}

// NOVA FUNÇÃO: Obter TODAS as contas a receber (para relatórios)
function obterTodasContasAReceber($connection, $filtros = [])
{
    try {
        // Similar à função original, mas sem o filtro de visibilidade
        $where_conditions = ["(v.forma_pagamento_id = 5 OR v.forma_pagamento_id = 7)"];
        $where_conditions[] = "(v.status = '' OR v.status IS NULL OR v.status = 'a_receber' OR v.status = 'concluida')";

        $params = [];

        // Aplicar filtros (mesma lógica da função original)
        if (!empty($filtros['cliente'])) {
            $where_conditions[] = "v.descricao LIKE ?";
            $params[] = "%" . $filtros['cliente'] . "%";
        }

        if (!empty($filtros['data'])) {
            $where_conditions[] = "DATE(v.data_venda) = ?";
            $params[] = $filtros['data'];
        }

        if (!empty($filtros['valor'])) {
            switch ($filtros['valor']) {
                case 'menor100':
                    $where_conditions[] = "v.valor_total < 100";
                    break;
                case '100a500':
                    $where_conditions[] = "v.valor_total BETWEEN 100 AND 500";
                    break;
                case 'maior500':
                    $where_conditions[] = "v.valor_total > 500";
                    break;
            }
        }

        $where_sql = implode(' AND ', $where_conditions);

        $query = "SELECT v.*, fp.nome as forma_pagamento, u.nome as operador 
                  FROM vendas v 
                  INNER JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id 
                  INNER JOIN usuarios u ON v.usuario_id = u.id 
                  WHERE $where_sql
                  ORDER BY v.data_venda DESC";

        $stmt = $connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter todas as contas a receber: " . $e->getMessage());
        return [];
    }
}

// NOVA FUNÇÃO: Buscar venda por ID
function buscarVendaPorId($connection, $venda_id)
{
    try {
        $query = "SELECT * FROM vendas WHERE id = ?";
        $stmt = $connection->prepare($query);
        $stmt->execute([$venda_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar venda por ID: " . $e->getMessage());
        return false;
    }
}

// NOVA FUNÇÃO: Registrar entrada no caixa
function registrarEntradaCaixa($connection, $valor, $usuario_id, $descricao)
{
    try {
        $caixa_aberto = obterCaixaAberto($connection);
        if (!$caixa_aberto) {
            return false; // Não há caixa aberto
        }

        $query = "INSERT INTO movimentacoes_caixa (caixa_id, usuario_id, tipo, valor, descricao, data_movimentacao) 
                  VALUES (?, ?, 'entrada', ?, ?, NOW())";
        $stmt = $connection->prepare($query);
        return $stmt->execute([$caixa_aberto['id'], $usuario_id, $valor, $descricao]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar entrada no caixa: " . $e->getMessage());
        return false;
    }
}

// NOVA FUNÇÃO: Calcular dias em atraso
function calcularDiasAtraso($data_venda)
{
    $data_venda = new DateTime($data_venda);
    $hoje = new DateTime();
    $diferenca = $hoje->diff($data_venda);
    return $diferenca->days;
}

// ADICIONE ESTAS FUNÇÕES NO FINAL DO SEU functions.php

// FUNÇÃO CORRIGIDA: Obter saldo final
function obterSaldoFinal($connection, $data)
{
    try {
        // Primeiro tenta buscar do caixa fechado da data específica
        $query = "SELECT valor_final FROM caixa 
                  WHERE DATE(data_fechamento) = :data 
                  AND status = 'fechado'
                  ORDER BY id DESC LIMIT 1";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(':data', $data);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['valor_final']) {
            return floatval($result['valor_final']);
        }

        // Se não encontrou fechamento, calcula baseado no saldo inicial + vendas - retiradas
        $saldo_inicial = obterSaldoInicial($connection, $data);

        // Total de vendas concluídas do dia
        $query_vendas = "SELECT COALESCE(SUM(valor_total), 0) as total_vendas 
                        FROM vendas 
                        WHERE DATE(data_venda) = :data 
                        AND status = 'concluida'";
        $stmt_vendas = $connection->prepare($query_vendas);
        $stmt_vendas->bindParam(':data', $data);
        $stmt_vendas->execute();
        $total_vendas = $stmt_vendas->fetch(PDO::FETCH_ASSOC)['total_vendas'] ?? 0;

        // Total de retiradas do dia
        $query_retiradas = "SELECT COALESCE(SUM(valor), 0) as total_retiradas 
                           FROM retiradas 
                           WHERE DATE(data_retirada) = :data";
        $stmt_retiradas = $connection->prepare($query_retiradas);
        $stmt_retiradas->bindParam(':data', $data);
        $stmt_retiradas->execute();
        $total_retiradas = $stmt_retiradas->fetch(PDO::FETCH_ASSOC)['total_retiradas'] ?? 0;

        return $saldo_inicial + $total_vendas - $total_retiradas;
    } catch (PDOException $e) {
        error_log("Erro ao obter saldo final: " . $e->getMessage());
        return 0.00;
    }
}

// Função para obter retiradas com total (FALTANTE)
function obterRetiradas($connection, $data_inicial, $data_final)
{
    try {
        $sql = "SELECT 
                    r.data_retirada,
                    r.valor,
                    r.motivo,
                    u.nome as usuario_nome
                FROM retiradas r
                INNER JOIN usuarios u ON r.usuario_id = u.id
                WHERE DATE(r.data_retirada) BETWEEN :data_inicial AND :data_final
                ORDER BY r.data_retirada DESC";

        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':data_inicial', $data_inicial);
        $stmt->bindParam(':data_final', $data_final);
        $stmt->execute();
        $retiradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular total
        $total_retiradas = 0;
        foreach ($retiradas as $retirada) {
            $total_retiradas += $retirada['valor'];
        }

        return [
            'detalhes' => $retiradas,
            'total' => $total_retiradas
        ];
    } catch (PDOException $e) {
        error_log("Erro ao obter retiradas: " . $e->getMessage());
        return [
            'detalhes' => [],
            'total' => 0
        ];
    }
}

// Função para obter vendas do período (FALTANTE)
function obterVendasPeriodo($connection, $data_inicial, $data_final)
{
    try {
        $data_final_ajustada = $data_final . ' 23:59:59';

        $sql = "SELECT 
                    v.data_venda,
                    v.valor_total,
                    fp.nome as forma_pagamento,
                    u.nome as usuario_nome,
                    v.descricao
                FROM vendas v
                INNER JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                INNER JOIN usuarios u ON v.usuario_id = u.id
                WHERE v.data_venda BETWEEN :data_inicial AND :data_final_ajustada
                AND v.status = 'concluida'
                ORDER BY v.data_venda DESC";

        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':data_inicial', $data_inicial);
        $stmt->bindParam(':data_final_ajustada', $data_final_ajustada);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter vendas do período: " . $e->getMessage());
        return [];
    }
}

// Função para obter resumo de pagamentos (FALTANTE)
function obterResumoPagamentos($connection, $data_inicial, $data_final)
{
    try {
        $data_final_ajustada = $data_final . ' 23:59:59';

        $sql = "SELECT 
                    fp.nome,
                    COUNT(v.id) as quantidade,
                    COALESCE(SUM(v.valor_total), 0) as total
                FROM formas_pagamento fp
                LEFT JOIN vendas v ON fp.id = v.forma_pagamento_id 
                    AND v.data_venda BETWEEN :data_inicial AND :data_final_ajustada
                    AND v.status = 'concluida'
                WHERE fp.ativo = 1
                GROUP BY fp.id, fp.nome
                ORDER BY total DESC";

        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':data_inicial', $data_inicial);
        $stmt->bindParam(':data_final_ajustada', $data_final_ajustada);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter resumo de pagamentos: " . $e->getMessage());
        return [];
    }
}

// Função para obter histórico de caixas (FALTANTE)
function obterHistoricoCaixas($connection, $limite = 10)
{
    try {
        $query = "SELECT c.*, 
                         u_abertura.nome as usuario_abertura,
                         u_fechamento.nome as usuario_fechamento
                  FROM caixa c
                  LEFT JOIN usuarios u_abertura ON c.usuario_id = u_abertura.id
                  LEFT JOIN usuarios u_fechamento ON c.usuario_fechamento = u_fechamento.id
                  ORDER BY c.data_abertura DESC 
                  LIMIT :limite";
        $stmt = $connection->prepare($query);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter histórico de caixas: " . $e->getMessage());
        return [];
    }
}

// Função para obter estatísticas do sistema (FALTANTE)
function obterEstatisticasSistema($connection)
{
    try {
        $estatisticas = [];

        // Total de vendas
        $query_vendas = "SELECT COUNT(*) as total FROM vendas WHERE status = 'concluida'";
        $stmt_vendas = $connection->prepare($query_vendas);
        $stmt_vendas->execute();
        $estatisticas['total_vendas'] = $stmt_vendas->fetchColumn();

        // Valor total vendido
        $query_valor = "SELECT COALESCE(SUM(valor_total), 0) as total FROM vendas WHERE status = 'concluida'";
        $stmt_valor = $connection->prepare($query_valor);
        $stmt_valor->execute();
        $estatisticas['valor_total_vendido'] = $stmt_valor->fetchColumn();

        // Contas a receber pendentes
        $query_contas = "SELECT COUNT(*) as total, COALESCE(SUM(valor_total), 0) as valor 
                         FROM vendas 
                         WHERE (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                         AND (status = '' OR status IS NULL)";
        $stmt_contas = $connection->prepare($query_contas);
        $stmt_contas->execute();
        $result_contas = $stmt_contas->fetch(PDO::FETCH_ASSOC);
        $estatisticas['contas_pendentes'] = $result_contas['total'] ?? 0;
        $estatisticas['valor_contas_pendentes'] = $result_contas['valor'] ?? 0;

        // Total de retiradas
        $query_retiradas = "SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor FROM retiradas";
        $stmt_retiradas = $connection->prepare($query_retiradas);
        $stmt_retiradas->execute();
        $result_retiradas = $stmt_retiradas->fetch(PDO::FETCH_ASSOC);
        $estatisticas['total_retiradas'] = $result_retiradas['total'] ?? 0;
        $estatisticas['valor_total_retiradas'] = $result_retiradas['valor'] ?? 0;

        return $estatisticas;
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas do sistema: " . $e->getMessage());
        return [];
    }
}

// Função para buscar vendas com filtros (FALTANTE)
function buscarVendasComFiltros($connection, $filtros = [])
{
    try {
        $where_conditions = ["1=1"];
        $params = [];

        // Filtro por data
        if (!empty($filtros['data_inicial']) && !empty($filtros['data_final'])) {
            $where_conditions[] = "v.data_venda BETWEEN ? AND ?";
            $params[] = $filtros['data_inicial'];
            $params[] = $filtros['data_final'] . ' 23:59:59';
        }

        // Filtro por forma de pagamento
        if (!empty($filtros['forma_pagamento_id'])) {
            $where_conditions[] = "v.forma_pagamento_id = ?";
            $params[] = $filtros['forma_pagamento_id'];
        }

        // Filtro por usuário
        if (!empty($filtros['usuario_id'])) {
            $where_conditions[] = "v.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }

        // Filtro por status
        if (!empty($filtros['status'])) {
            if ($filtros['status'] == 'concluida') {
                $where_conditions[] = "v.status = 'concluida'";
            } elseif ($filtros['status'] == 'a_receber') {
                $where_conditions[] = "(v.status = '' OR v.status IS NULL)";
            }
        }

        $where_sql = implode(' AND ', $where_conditions);

        $query = "SELECT v.*, fp.nome as forma_pagamento, u.nome as usuario_nome
                  FROM vendas v
                  INNER JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                  INNER JOIN usuarios u ON v.usuario_id = u.id
                  WHERE $where_sql
                  ORDER BY v.data_venda DESC";

        $stmt = $connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar vendas com filtros: " . $e->getMessage());
        return [];
    }
}

// Função para exportar dados para CSV (FALTANTE)
function exportarVendasCSV($connection, $data_inicial, $data_final)
{
    try {
        $vendas = obterVendasPeriodo($connection, $data_inicial, $data_final);

        // Criar arquivo CSV
        $filename = "vendas_" . date('Y-m-d_His') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Cabeçalho
        fputcsv($output, [
            'Data',
            'Valor',
            'Forma Pagamento',
            'Operador',
            'Descrição'
        ], ';');

        // Dados
        foreach ($vendas as $venda) {
            fputcsv($output, [
                date('d/m/Y H:i', strtotime($venda['data_venda'])),
                number_format($venda['valor_total'], 2, ',', '.'),
                $venda['forma_pagamento'],
                $venda['usuario_nome'],
                $venda['descricao']
            ], ';');
        }

        fclose($output);
        exit;
    } catch (PDOException $e) {
        error_log("Erro ao exportar CSV: " . $e->getMessage());
        return false;
    }
}

// Função para receber pagamento com forma de pagamento específica
function receberPagamentoContaComFormaPagamento($connection, $venda_id, $usuario_id, $forma_pagamento_id)
{
    try {
        // Verificar se a venda existe e está pendente
        $query_verificar = "SELECT * FROM vendas 
                           WHERE id = ? 
                           AND (forma_pagamento_id = 5 OR forma_pagamento_id = 7) 
                           AND (status = '' OR status IS NULL OR status = 'a_receber')";
        $stmt_verificar = $connection->prepare($query_verificar);
        $stmt_verificar->execute([$venda_id]);
        $venda = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

        if (!$venda) {
            error_log("Venda não encontrada ou já foi recebida: " . $venda_id);
            return false;
        }

        // Verificar se há caixa aberto
        $caixa_aberto = obterCaixaAberto($connection);
        if (!$caixa_aberto) {
            error_log("Não há caixa aberto para registrar o recebimento");
            return false;
        }

        // Iniciar transação
        $connection->beginTransaction();

        // 1. Atualizar a venda original para status 'concluida' e marcar como não visível
        $query_update_venda = "UPDATE vendas SET 
                              status = 'concluida', 
                              data_recebimento = NOW(),
                              visivel_contas_receber = 0 
                            WHERE id = ?";

        $stmt_update_venda = $connection->prepare($query_update_venda);
        $stmt_update_venda->execute([$venda_id]);

        if ($stmt_update_venda->rowCount() === 0) {
            $connection->rollBack();
            error_log("Nenhuma linha afetada ao atualizar venda #" . $venda_id);
            return false;
        }

        // 2. Criar uma NOVA venda no caixa atual com a forma de pagamento recebida
        $query_nova_venda = "INSERT INTO vendas (caixa_id, usuario_id, data_venda, valor_total, forma_pagamento_id, descricao, status) 
                           VALUES (?, ?, NOW(), ?, ?, ?, 'concluida')";

        $descricao_nova = "RECEBIMENTO - Conta #" . $venda_id . " - " . ($venda['descricao'] ?? '');

        $stmt_nova_venda = $connection->prepare($query_nova_venda);
        $stmt_nova_venda->execute([
            $caixa_aberto['id'],
            $usuario_id,
            $venda['valor_total'],
            $forma_pagamento_id,
            $descricao_nova
        ]);

        $nova_venda_id = $connection->lastInsertId();

        // Confirmar transação
        $connection->commit();

        error_log("Pagamento recebido com sucesso:");
        error_log("Venda original #" . $venda_id . " marcada como recebida");
        error_log("Nova venda #" . $nova_venda_id . " criada no caixa atual");
        error_log("Valor: " . $venda['valor_total']);
        error_log("Forma de pagamento: " . $forma_pagamento_id);

        return true;
    } catch (PDOException $e) {
        $connection->rollBack();
        error_log("Erro ao receber pagamento da conta #" . $venda_id . ": " . $e->getMessage());
        return false;
    }
}

// ADICIONE ESTAS FUNÇÕES AO SEU functions.php EXISTENTE

// ============================================================================
// FUNÇÕES PARA GERENCIAMENTO DE CLIENTES E CRÉDITO
// ============================================================================

/**
 * Cadastrar novo cliente
 */
/**
 * Cadastrar novo cliente (VERSÃO CORRIGIDA SIMPLES)
 */
function cadastrarCliente($connection, $dados) {
    try {
        // Validações básicas
        if (empty($dados['nome'])) {
            throw new Exception("Nome do cliente é obrigatório");
        }
        
        if (strlen($dados['nome']) < 3) {
            throw new Exception("O nome deve ter pelo menos 3 caracteres");
        }
        
        // Formatar CPF
        $cpf = null;
        if (!empty($dados['cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', $dados['cpf']);
            
            // Validar CPF
            if (!validarCPF($cpf)) {
                throw new Exception("CPF inválido");
            }
            
            // Verificar se CPF já existe
            $stmt = $connection->prepare("SELECT id FROM clientes WHERE cpf = ?");
            $stmt->execute([$cpf]);
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("CPF já cadastrado no sistema");
            }
        }
        
        // Converter valor do crédito (tratar formato brasileiro)
        $valor_credito = 0;
        if (!empty($dados['valor_credito'])) {
            $valor_str = (string)$dados['valor_credito'];
            $valor_str = str_replace(['R$', '.', ' '], '', $valor_str); // Remove R$ e pontos
            $valor_str = str_replace(',', '.', $valor_str); // Substitui vírgula por ponto
            $valor_credito = floatval($valor_str);
        }
        
        // Verificar usuário logado
        $usuario_id = $_SESSION['usuario_id'] ?? 1; // Fallback para admin se não houver sessão
        
        // Preparar SQL com valores diretos (não usar bindParam com referência)
        $sql = "INSERT INTO clientes (
            nome, 
            cpf, 
            email, 
            telefone, 
            valor_credito, 
            data_cadastro, 
            ativo
        ) VALUES (
            :nome, 
            :cpf, 
            :email, 
            :telefone, 
            :valor_credito, 
            NOW(), 
            1
        )";
        
        $stmt = $connection->prepare($sql);
        
        // Usar bindValue em vez de bindParam (resolve o problema de referência)
        $stmt->bindValue(':nome', $dados['nome'], PDO::PARAM_STR);
        
        if ($cpf) {
            $stmt->bindValue(':cpf', $cpf, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':cpf', null, PDO::PARAM_NULL);
        }
        
        if (!empty($dados['email'])) {
            $stmt->bindValue(':email', trim($dados['email']), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':email', null, PDO::PARAM_NULL);
        }
        
        if (!empty($dados['telefone'])) {
            $stmt->bindValue(':telefone', trim($dados['telefone']), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':telefone', null, PDO::PARAM_NULL);
        }
        
        $stmt->bindValue(':valor_credito', $valor_credito, PDO::PARAM_STR);
        
        // Executar
        if ($stmt->execute()) {
            $clienteId = $connection->lastInsertId();
            
            // Registrar no histórico se houver crédito inicial
            if ($valor_credito > 0) {
                registrarHistoricoCredito(
                    $connection, 
                    $clienteId, 
                    $usuario_id,
                    0, 
                    $valor_credito, 
                    "Crédito inicial"
                );
            }
            
            return $clienteId;
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Erro SQL: " . $errorInfo[2]);
        }
        
    } catch (PDOException $e) {
        // Capturar erros específicos do PDO
        $errorMessage = "Erro de banco de dados: " . $e->getMessage();
        
        // Verificar se é erro de duplicação de CPF
        if ($e->errorInfo[0] == '23000' && strpos($e->getMessage(), 'cpf') !== false) {
            $errorMessage = "CPF já cadastrado no sistema";
        }
        
        throw new Exception($errorMessage);
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Função auxiliar para registrar histórico de crédito
 */
function registrarHistoricoCredito($connection, $clienteId, $usuarioId, $valorAnterior, $valorNovo, $observacao = '') {
    try {
        $sql = "INSERT INTO historico_credito (
            cliente_id, 
            usuario_id, 
            data_alteracao, 
            valor_anterior, 
            valor_novo, 
            observacao
        ) VALUES (
            :cliente_id, 
            :usuario_id, 
            NOW(), 
            :valor_anterior, 
            :valor_novo, 
            :observacao
        )";
        
        $stmt = $connection->prepare($sql);
        $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':valor_anterior', $valorAnterior, PDO::PARAM_STR);
        $stmt->bindValue(':valor_novo', $valorNovo, PDO::PARAM_STR);
        $stmt->bindValue(':observacao', $observacao, PDO::PARAM_STR);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        // Silenciar erro do histórico, mas logar
        error_log("Erro ao registrar histórico de crédito: " . $e->getMessage());
        return false;
    }
}
/**
 * Excluir cliente (exclusão lógica - marca como inativo)
 */
function excluirCliente($connection, $clienteId, $usuarioId, $motivo = '') {
    try {
        // Verificar se o cliente existe e não está inativo
        $sql = "SELECT nome, ativo FROM clientes WHERE id = :id";
        $stmt = $connection->prepare($sql);
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            throw new Exception("Cliente não encontrado");
        }
        
        if ($cliente['ativo'] == 0) {
            throw new Exception("Cliente já está inativo");
        }
        
        // Verificar se o cliente tem crédito pendente
        if (floatval($cliente['valor_credito']) > 0) {
            throw new Exception("Não é possível excluir cliente com crédito disponível. Zere o crédito primeiro.");
        }
        
        // Verificar se o cliente tem vendas pendentes
        $sql_vendas = "SELECT COUNT(*) as total FROM vendas 
                      WHERE cliente_id = :cliente_id 
                      AND (status = '' OR status IS NULL)";
        $stmt_vendas = $connection->prepare($sql_vendas);
        $stmt_vendas->execute([':cliente_id' => $clienteId]);
        $vendas_pendentes = $stmt_vendas->fetch(PDO::FETCH_ASSOC);
        
        if ($vendas_pendentes && $vendas_pendentes['total'] > 0) {
            throw new Exception("Cliente tem vendas pendentes. Finalize as vendas primeiro.");
        }
        
        // Iniciar transação
        $connection->beginTransaction();
        
        // Registrar histórico antes de excluir (opcional)
        $sql_historico = "INSERT INTO historico_exclusoes 
                         (cliente_id, usuario_id, nome_cliente, motivo, data_exclusao) 
                         VALUES (:cliente_id, :usuario_id, :nome_cliente, :motivo, NOW())";
        $stmt_historico = $connection->prepare($sql_historico);
        $stmt_historico->execute([
            ':cliente_id' => $clienteId,
            ':usuario_id' => $usuarioId,
            ':nome_cliente' => $cliente['nome'],
            ':motivo' => $motivo ?: 'Exclusão solicitada'
        ]);
        
        // Marcar como inativo (exclusão lógica)
        $sql_update = "UPDATE clientes SET 
                      ativo = 0, 
                      data_inativacao = NOW(),
                      usuario_inativacao = :usuario_id
                      WHERE id = :id";
        
        $stmt_update = $connection->prepare($sql_update);
        $stmt_update->execute([
            ':usuario_id' => $usuarioId,
            ':id' => $clienteId
        ]);
        
        $connection->commit();
        return true;
        
    } catch (PDOException $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        error_log("Erro ao excluir cliente #$clienteId: " . $e->getMessage());
        throw new Exception("Erro de banco de dados: " . $e->getMessage());
    } catch (Exception $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $e;
    }
}

/**
 * Buscar cliente por ID (com informações de inativação)
 */
function buscarClienteCompleto($connection, $id) {
    try {
        $sql = "SELECT c.*, 
                       u_inativacao.nome as usuario_inativacao_nome
                FROM clientes c
                LEFT JOIN usuarios u_inativacao ON c.usuario_inativacao = u_inativacao.id
                WHERE c.id = :id";
        $stmt = $connection->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Erro ao buscar cliente completo: " . $e->getMessage());
        return false;
    }
}
/**
 * Listar clientes com crédito disponível
 */
function listarClientesComCredito($connection) {
    try {
        $sql = "SELECT * FROM clientes 
                WHERE valor_credito > 0 
                AND ativo = 1 
                ORDER BY nome ASC";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Erro ao listar clientes com crédito: " . $e->getMessage());
        return [];
    }
}

/**
 * Atualizar crédito do cliente com histórico
 */
function atualizarCreditoCliente($connection, $clienteId, $novoValor, $observacao = '', $tipo_operacao = 'ajuste') {
    try {
        $connection->beginTransaction();
        
        // Buscar valor atual
        $sqlAtual = "SELECT valor_credito, nome FROM clientes WHERE id = :id";
        $stmt = $connection->prepare($sqlAtual);
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            throw new Exception("Cliente não encontrado");
        }
        
        // Atualizar crédito
        $sqlUpdate = "UPDATE clientes 
                     SET valor_credito = :valor 
                     WHERE id = :id";
        
        $stmt = $connection->prepare($sqlUpdate);
        $stmt->execute([
            ':valor' => $novoValor,
            ':id' => $clienteId
        ]);
        
        // Registrar histórico
        $sqlHist = "INSERT INTO historico_credito 
                   (cliente_id, usuario_id, valor_anterior, valor_novo, tipo_operacao, observacao) 
                   VALUES (:cliente_id, :usuario_id, :valor_anterior, :valor_novo, :tipo_operacao, :observacao)";
        
        $stmt = $connection->prepare($sqlHist);
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':usuario_id' => $_SESSION['usuario_id'],
            ':valor_anterior' => $cliente['valor_credito'],
            ':valor_novo' => $novoValor,
            ':tipo_operacao' => $tipo_operacao,
            ':observacao' => $observacao
        ]);
        
        $connection->commit();
        return true;
        
    } catch(PDOException $e) {
        $connection->rollBack();
        error_log("Erro ao atualizar crédito: " . $e->getMessage());
        return false;
    }
}

/**
 * Buscar cliente por ID
 */
function buscarClientePorId($connection, $id) {
    try {
        $sql = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $connection->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Erro ao buscar cliente: " . $e->getMessage());
        return false;
    }
}

/**
 * Listar todos os clientes
 */
function listarTodosClientes($connection, $ativo = true) {
    try {
        $sql = "SELECT id, nome, cpf, telefone, valor_credito FROM clientes WHERE 1=1";
        
        if ($ativo) {
            $sql .= " AND ativo = 1";
        }
        
        $sql .= " ORDER BY nome";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Erro ao listar clientes: " . $e->getMessage());
        return [];
    }
}

/**
 * Obter histórico de crédito do cliente
 */
function obterHistoricoCredito($connection, $clienteId, $limite = 10) {
    try {
        $sql = "SELECT h.*, u.nome as usuario_nome 
                FROM historico_credito h
                JOIN usuarios u ON h.usuario_id = u.id
                WHERE h.cliente_id = :cliente_id
                ORDER BY h.data_alteracao DESC
                LIMIT :limite";
        
        $stmt = $connection->prepare($sql);
        $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Erro ao obter histórico de crédito: " . $e->getMessage());
        return [];
    }
}


/**
 * Validar CPF
 */
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica se os dígitos calculados conferem com os informados
    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

// ============================================================================
// FUNÇÕES PARA DETALHES DE VENDAS/COMPRAS
// ============================================================================

/**
 * Obter detalhes completos de uma venda específica
 */
function obterDetalhesVenda($connection, $venda_id) {
    try {
        $sql = "SELECT 
                    v.id,
                    v.caixa_id,
                    v.usuario_id,
                    v.data_venda,
                    v.valor_total,
                    v.forma_pagamento_id,
                    v.descricao,
                    v.status,
                    v.visivel_contas_receber,
                    v.data_recebimento,
                    c.status as caixa_status,
                    c.data_abertura,
                    c.data_fechamento,
                    u.nome as usuario_nome,
                    fp.nome as forma_pagamento_nome
                FROM vendas v
                JOIN caixa c ON v.caixa_id = c.id
                JOIN usuarios u ON v.usuario_id = u.id
                JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                WHERE v.id = ?";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([$venda_id]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: verificar o que está sendo retornado
        error_log("Detalhes da venda #$venda_id encontrados: " . ($resultado ? 'Sim' : 'Não'));
        if ($resultado) {
            error_log("Dados da venda: " . print_r($resultado, true));
        }
        
        return $resultado;
        
    } catch(PDOException $e) {
        error_log("Erro ao buscar detalhes da venda #$venda_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Verificar se uma venda existe
 */
function verificarVendaExiste($connection, $venda_id) {
    try {
        $sql = "SELECT COUNT(*) as total FROM vendas WHERE id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->execute([$venda_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($resultado && $resultado['total'] > 0);
        
    } catch(PDOException $e) {
        error_log("Erro ao verificar venda #$venda_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Excluir venda (apenas se caixa estiver aberto)
 */
function excluirVenda($connection, $venda_id) {
    try {
        // Verificar se a venda existe
        $sql_check = "SELECT caixa_id FROM vendas WHERE id = ?";
        $stmt_check = $connection->prepare($sql_check);
        $stmt_check->execute([$venda_id]);
        $venda = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$venda) {
            throw new Exception("Venda não encontrada");
        }
        
        // Verificar se o caixa está aberto
        $caixa_aberto = obterCaixaAberto($connection);
        if (!$caixa_aberto) {
            throw new Exception("Não é possível excluir venda: caixa fechado");
        }
        
        // Verificar se a venda pertence ao caixa aberto
        if ($venda['caixa_id'] != $caixa_aberto['id']) {
            throw new Exception("Não é possível excluir venda de outro caixa");
        }
        
        // Iniciar transação
        $connection->beginTransaction();
        
        // Excluir a venda
        $sql_delete = "DELETE FROM vendas WHERE id = ?";
        $stmt_delete = $connection->prepare($sql_delete);
        $stmt_delete->execute([$venda_id]);
        
        // Verificar se foi excluído
        if ($stmt_delete->rowCount() === 0) {
            $connection->rollBack();
            throw new Exception("Erro ao excluir venda");
        }
        
        $connection->commit();
        return true;
        
    } catch(PDOException $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        error_log("Erro ao excluir venda #$venda_id: " . $e->getMessage());
        throw new Exception("Erro ao excluir venda: " . $e->getMessage());
    } catch(Exception $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        error_log("Erro ao excluir venda #$venda_id: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

/**
 * Listar vendas com filtros avançados
 */
function listarVendasComFiltros($connection, $filtros = []) {
    try {
        $sql = "SELECT v.*, 
                       c.data_abertura,
                       c.data_fechamento,
                       u.nome as usuario_nome,
                       fp.nome as forma_pagamento_nome,
                       cl.nome as cliente_nome
                FROM vendas v
                JOIN caixa c ON v.caixa_id = c.id
                JOIN usuarios u ON v.usuario_id = u.id
                JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                LEFT JOIN clientes cl ON v.cliente_id = cl.id
                WHERE 1=1";
        
        $params = [];
        
        // Filtro por data inicial
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND DATE(v.data_venda) >= :data_inicio";
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        
        // Filtro por data final
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND DATE(v.data_venda) <= :data_fim";
            $params[':data_fim'] = $filtros['data_fim'];
        }
        
        // Filtro por cliente
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND v.cliente_id = :cliente_id";
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        
        // Filtro por forma de pagamento
        if (!empty($filtros['forma_pagamento_id'])) {
            $sql .= " AND v.forma_pagamento_id = :forma_pagamento_id";
            $params[':forma_pagamento_id'] = $filtros['forma_pagamento_id'];
        }
        
        // Filtro por status
        if (!empty($filtros['status'])) {
            if ($filtros['status'] === 'concluida') {
                $sql .= " AND v.status = 'concluida'";
            } elseif ($filtros['status'] === 'pendente') {
                $sql .= " AND (v.status = '' OR v.status IS NULL)";
            } elseif ($filtros['status'] === 'todas') {
                // Mostrar todas
            }
        } else {
            // Padrão: mostrar apenas concluídas
            $sql .= " AND v.status = 'concluida'";
        }
        
        // Filtro por valor mínimo
        if (!empty($filtros['valor_min'])) {
            $sql .= " AND v.valor_total >= :valor_min";
            $params[':valor_min'] = $filtros['valor_min'];
        }
        
        // Filtro por valor máximo
        if (!empty($filtros['valor_max'])) {
            $sql .= " AND v.valor_total <= :valor_max";
            $params[':valor_max'] = $filtros['valor_max'];
        }
        
        $sql .= " ORDER BY v.data_venda DESC";
        
        // Limite opcional
        if (!empty($filtros['limite'])) {
            $sql .= " LIMIT :limite";
            $params[':limite'] = (int)$filtros['limite'];
        }
        
        $stmt = $connection->prepare($sql);
        
        // Bind dos parâmetros
        foreach ($params as $key => $value) {
            if ($key === ':limite') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao listar vendas com filtros: " . $e->getMessage());
        return [];
    }
}

/**
 * Obter estatísticas de vendas por período
 */
function obterEstatisticasVendas($connection, $data_inicio, $data_fim) {
    try {
        $data_fim_ajustada = $data_fim . ' 23:59:59';
        
        $sql = "SELECT 
                    COUNT(*) as total_vendas,
                    SUM(v.valor_total) as valor_total,
                    AVG(v.valor_total) as media_valor,
                    MIN(v.valor_total) as menor_venda,
                    MAX(v.valor_total) as maior_venda,
                    COUNT(DISTINCT v.usuario_id) as usuarios_ativos,
                    COUNT(DISTINCT v.cliente_id) as clientes_atendidos
                FROM vendas v
                WHERE v.data_venda BETWEEN :data_inicio AND :data_fim_ajustada
                AND v.status = 'concluida'";
        
        $stmt = $connection->prepare($sql);
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim_ajustada', $data_fim_ajustada);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao obter estatísticas de vendas: " . $e->getMessage());
        return [];
    }
}

/**
 * Registrar venda com cliente e crédito (se aplicável)
 */
function registrarVendaComCliente($connection, $caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $cliente_id = null, $usar_credito = false) {
    try {
        $status = 'concluida';
        
        // Verificar se é "A Receber"
        if ($forma_pagamento_id == 5 || $forma_pagamento_id == 7) {
            $status = ''; // Status vazio para vendas a receber
        }
        
        // Se usar crédito do cliente
        if ($usar_credito && $cliente_id) {
            $cliente = buscarClientePorId($connection, $cliente_id);
            if ($cliente && $cliente['valor_credito'] >= $valor_total) {
                // Deduzir do crédito
                $novo_credito = $cliente['valor_credito'] - $valor_total;
                atualizarCreditoCliente(
                    $connection, 
                    $cliente_id, 
                    $novo_credito, 
                    "Compra realizada no valor de R$ " . number_format($valor_total, 2, ',', '.'),
                    'compra'
                );
                $forma_pagamento_id = 8; // ID para pagamento com crédito (precisa criar)
            } else {
                throw new Exception("Crédito insuficiente ou cliente não encontrado");
            }
        }
        
        // Registrar a venda
        $sql = "INSERT INTO vendas (caixa_id, usuario_id, data_venda, valor_total, forma_pagamento_id, descricao, status, cliente_id) 
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";

        $stmt = $connection->prepare($sql);
        $stmt->execute([$caixa_id, $usuario_id, $valor_total, $forma_pagamento_id, $descricao, $status, $cliente_id]);

        $venda_id = $connection->lastInsertId();
        
        // Se foi pago com crédito, registrar como entrada no caixa
        if ($usar_credito && $cliente_id) {
            // Aqui você pode adicionar lógica para registrar no caixa
            // como se fosse uma venda normal, já que o crédito é como dinheiro
        }
        
        return $venda_id;
        
    } catch(PDOException $e) {
        error_log("Erro ao registrar venda com cliente: " . $e->getMessage());
        return false;
    }
}

/**
 * Buscar vendas por cliente
 */
function buscarVendasPorCliente($connection, $cliente_id) {
    try {
        $sql = "SELECT v.*, 
                       fp.nome as forma_pagamento_nome,
                       u.nome as usuario_nome,
                       c.data_abertura
                FROM vendas v
                JOIN formas_pagamento fp ON v.forma_pagamento_id = fp.id
                JOIN usuarios u ON v.usuario_id = u.id
                JOIN caixa ca ON v.caixa_id = ca.id
                WHERE v.cliente_id = :cliente_id
                ORDER BY v.data_venda DESC";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([':cliente_id' => $cliente_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao buscar vendas por cliente: " . $e->getMessage());
        return [];
    }
}

/**
 * Obter resumo financeiro por cliente
 */
function obterResumoCliente($connection, $cliente_id) {
    try {
        $sql = "SELECT 
                    c.nome,
                    c.valor_credito,
                    COUNT(v.id) as total_compras,
                    SUM(CASE WHEN v.status = 'concluida' THEN v.valor_total ELSE 0 END) as total_gasto,
                    SUM(CASE WHEN v.status = '' OR v.status IS NULL THEN v.valor_total ELSE 0 END) as total_pendente,
                    MAX(v.data_venda) as ultima_compra,
                    MIN(v.data_venda) as primeira_compra
                FROM clientes c
                LEFT JOIN vendas v ON c.id = v.cliente_id
                WHERE c.id = :cliente_id
                GROUP BY c.id, c.nome, c.valor_credito";
        
        $stmt = $connection->prepare($sql);
        $stmt->execute([':cliente_id' => $cliente_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Erro ao obter resumo do cliente: " . $e->getMessage());
        return [];
    }
}
