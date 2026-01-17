<?php
// includes/functions/functions_caixa.php

/**
 * Funções relacionadas ao gerenciamento de caixa
 */

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

        // Se não encontrou, busca do caixa aberto atual
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

// Função para obter histórico de caixas
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