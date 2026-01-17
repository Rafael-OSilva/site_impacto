<?php
// includes/functions/functions_contas_receber.php

/**
 * Funções para gerenciamento de contas a receber
 */

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