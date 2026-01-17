<?php
// includes/functions/functions_clientes.php

/**
 * Funções para gerenciamento de clientes e crédito
 */

// Cadastrar novo cliente (VERSÃO CORRIGIDA SIMPLES)
function cadastrarCliente($connection, $dados)
{
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

        // Converter valor do crédito
        $valor_credito = 0;
        if (!empty($dados['valor_credito'])) {
            $valor_str = (string)$dados['valor_credito'];
            $valor_str = str_replace(['R$', '.', ' '], '', $valor_str);
            $valor_str = str_replace(',', '.', $valor_str);
            $valor_credito = floatval($valor_str);
        }

        // Verificar usuário logado
        $usuario_id = $_SESSION['usuario_id'] ?? 1;

        // Preparar SQL
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

        // Usar bindValue
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
        $errorMessage = "Erro de banco de dados: " . $e->getMessage();

        if ($e->errorInfo[0] == '23000' && strpos($e->getMessage(), 'cpf') !== false) {
            $errorMessage = "CPF já cadastrado no sistema";
        }

        throw new Exception($errorMessage);
    } catch (Exception $e) {
        throw $e;
    }
}

// Função auxiliar para registrar histórico de crédito
function registrarHistoricoCredito($connection, $clienteId, $usuarioId, $valorAnterior, $valorNovo, $observacao = '')
{
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
        error_log("Erro ao registrar histórico de crédito: " . $e->getMessage());
        return false;
    }
}

// Excluir cliente (exclusão lógica - marca como inativo)
function excluirCliente($connection, $clienteId, $usuarioId, $motivo = '')
{
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

        // Registrar histórico antes de excluir
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

// Buscar cliente por ID (com informações de inativação)
function buscarClienteCompleto($connection, $id)
{
    try {
        $sql = "SELECT c.*, 
                       u_inativacao.nome as usuario_inativacao_nome
                FROM clientes c
                LEFT JOIN usuarios u_inativacao ON c.usuario_inativacao = u_inativacao.id
                WHERE c.id = :id";
        $stmt = $connection->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar cliente completo: " . $e->getMessage());
        return false;
    }
}

// Listar clientes com crédito disponível
function listarClientesComCredito($connection)
{
    try {
        $sql = "SELECT * FROM clientes 
                WHERE valor_credito > 0 
                AND ativo = 1 
                ORDER BY nome ASC";

        $stmt = $connection->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao listar clientes com crédito: " . $e->getMessage());
        return [];
    }
}

// Atualizar crédito do cliente com histórico
function atualizarCreditoCliente($connection, $clienteId, $novoValor, $observacao = '', $tipo_operacao = 'ajuste')
{
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

        $valorAnterior = floatval($cliente['valor_credito']);

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
                   (cliente_id, usuario_id, valor_anterior, valor_novo, tipo_operacao, observacao, data_alteracao) 
                   VALUES (:cliente_id, :usuario_id, :valor_anterior, :valor_novo, :tipo_operacao, :observacao, NOW())";

        $stmt = $connection->prepare($sqlHist);
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':usuario_id' => $_SESSION['usuario_id'],
            ':valor_anterior' => $valorAnterior,
            ':valor_novo' => $novoValor,
            ':tipo_operacao' => $tipo_operacao,
            ':observacao' => $observacao
        ]);

        $connection->commit();
        return true;
    } catch (PDOException $e) {
        $connection->rollBack();
        error_log("Erro ao atualizar crédito: " . $e->getMessage());
        return false;
    }
}

// Buscar cliente por ID
function buscarClientePorId($connection, $id)
{
    try {
        $sql = "SELECT * FROM clientes WHERE id = :id";
        $stmt = $connection->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar cliente: " . $e->getMessage());
        return false;
    }
}

// Listar todos os clientes
function listarTodosClientes($connection, $ativo = true)
{
    try {
        $sql = "SELECT id, nome, cpf, telefone, valor_credito FROM clientes WHERE 1=1";

        if ($ativo) {
            $sql .= " AND ativo = 1";
        }

        $sql .= " ORDER BY nome";

        $stmt = $connection->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao listar clientes: " . $e->getMessage());
        return [];
    }
}

// Obter histórico de crédito do cliente
function obterHistoricoCredito($connection, $clienteId, $limite = 10)
{
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
    } catch (PDOException $e) {
        error_log("Erro ao obter histórico de crédito: " . $e->getMessage());
        return [];
    }
}

// Obter resumo financeiro por cliente
function obterResumoCliente($connection, $cliente_id)
{
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
    } catch (PDOException $e) {
        error_log("Erro ao obter resumo do cliente: " . $e->getMessage());
        return [];
    }
}

/**
 * Verificar se um cliente pode ser excluído
 * @param PDO $connection Conexão PDO
 * @param int $clienteId ID do cliente
 * @return array Resultado da verificação
 */
function verificarPossibilidadeExclusao($connection, $clienteId)
{
    try {
        $resultado = [
            'pode_excluir' => true,
            'motivos' => [],
            'detalhes' => [],
            'cliente' => null
        ];

        // Buscar informações do cliente
        $cliente = buscarClientePorId($connection, $clienteId);
        
        if (!$cliente) {
            $resultado['pode_excluir'] = false;
            $resultado['motivos'][] = "Cliente não encontrado.";
            return $resultado;
        }
        
        $resultado['cliente'] = $cliente;
        $resultado['detalhes']['nome'] = $cliente['nome'];
        $resultado['detalhes']['valor_credito'] = $cliente['valor_credito'];

        // Verificar se cliente está ativo
        if ($cliente['ativo'] == 0) {
            $resultado['pode_excluir'] = false;
            $resultado['motivos'][] = "Cliente já está inativo.";
        }

        // Verificar saldo de crédito
        $saldo = floatval($cliente['valor_credito']);
        if ($saldo > 0) {
            $resultado['pode_excluir'] = false;
            $resultado['motivos'][] = "Cliente possui saldo de crédito: R$ " . 
                                      number_format($saldo, 2, ',', '.');
            $resultado['detalhes']['credito_restante'] = $saldo;
        }

        // Verificar contas a receber pendentes
        $sql_pendentes = "SELECT COUNT(*) as total, SUM(valor_total) as total_valor 
                         FROM vendas 
                         WHERE cliente_id = :cliente_id 
                         AND forma_pagamento_id = 5  -- ID para 'A Receber'
                         AND data_recebimento IS NULL
                         AND status = 'concluida'";
        
        $stmt = $connection->prepare($sql_pendentes);
        $stmt->execute([':cliente_id' => $clienteId]);
        $pendentes = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pendentes && $pendentes['total'] > 0) {
            $resultado['pode_excluir'] = false;
            $resultado['motivos'][] = "Cliente possui " . $pendentes['total'] . 
                                     " conta(s) a receber pendente(s): R$ " . 
                                     number_format($pendentes['total_valor'], 2, ',', '.');
            $resultado['detalhes']['contas_pendentes'] = $pendentes;
        }

        // Verificar se há histórico de vendas (apenas para informação)
        $sql_historico = "SELECT COUNT(*) as total_vendas FROM vendas WHERE cliente_id = :cliente_id";
        $stmt = $connection->prepare($sql_historico);
        $stmt->execute([':cliente_id' => $clienteId]);
        $historico = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($historico && $historico['total_vendas'] > 0) {
            $resultado['detalhes']['total_vendas'] = $historico['total_vendas'];
            $resultado['info'] = "Cliente possui histórico de " . $historico['total_vendas'] . " venda(s).";
        }

        return $resultado;

    } catch (PDOException $e) {
        error_log("Erro ao verificar exclusão do cliente: " . $e->getMessage());
        return [
            'pode_excluir' => false,
            'motivos' => ["Erro ao verificar dados: " . $e->getMessage()],
            'detalhes' => [],
            'cliente' => null
        ];
    }
}

/**
 * Excluir cliente com verificação de segurança
 * @param PDO $connection Conexão PDO
 * @param int $clienteId ID do cliente
 * @param int $usuarioId ID do usuário que está excluindo
 * @param string $motivo Motivo da exclusão
 * @return array Resultado da operação
 */
function excluirClienteCompleto($connection, $clienteId, $usuarioId, $motivo = '')
{
    try {
        // Verificar se pode excluir
        $verificacao = verificarPossibilidadeExclusao($connection, $clienteId);
        
        if (!$verificacao['pode_excluir']) {
            return [
                'success' => false,
                'message' => implode(' ', $verificacao['motivos'])
            ];
        }

        // Iniciar transação
        $connection->beginTransaction();

        // Registrar histórico antes de excluir
        $sql_historico = "INSERT INTO historico_exclusoes 
                         (cliente_id, usuario_id, nome_cliente, motivo, data_exclusao) 
                         VALUES (:cliente_id, :usuario_id, :nome_cliente, :motivo, NOW())";
        
        $stmt_historico = $connection->prepare($sql_historico);
        $stmt_historico->execute([
            ':cliente_id' => $clienteId,
            ':usuario_id' => $usuarioId,
            ':nome_cliente' => $verificacao['cliente']['nome'],
            ':motivo' => $motivo ?: 'Exclusão solicitada pelo usuário'
        ]);

        // Fazer exclusão lógica (marcar como inativo)
        $sql_excluir = "UPDATE clientes SET 
                       ativo = 0,
                       data_inativacao = NOW(),
                       usuario_inativacao = :usuario_id 
                       WHERE id = :id";
        
        $stmt_excluir = $connection->prepare($sql_excluir);
        $stmt_excluir->execute([
            ':usuario_id' => $usuarioId,
            ':id' => $clienteId
        ]);

        // Desvincular cliente de vendas existentes
        $sql_desvincular = "UPDATE vendas SET cliente_id = NULL WHERE cliente_id = :cliente_id";
        $stmt_desvincular = $connection->prepare($sql_desvincular);
        $stmt_desvincular->execute([':cliente_id' => $clienteId]);

        // Confirmar transação
        $connection->commit();

        return [
            'success' => true,
            'message' => "Cliente '{$verificacao['cliente']['nome']}' excluído com sucesso!",
            'cliente_nome' => $verificacao['cliente']['nome']
        ];

    } catch (PDOException $e) {
        // Reverter transação em caso de erro
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        
        error_log("Erro ao excluir cliente #$clienteId: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => "Erro de banco de dados: " . $e->getMessage()
        ];
    } catch (Exception $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Listar clientes excluídos
 * @param PDO $connection Conexão PDO
 * @param int $limite Limite de registros
 * @return array Lista de clientes excluídos
 */
function listarClientesExcluidos($connection, $limite = 50)
{
    try {
        $sql = "SELECT c.id, c.nome, c.cpf, c.data_cadastro, 
                       he.data_exclusao, he.motivo,
                       u.nome as usuario_exclusao
                FROM clientes c
                JOIN historico_exclusoes he ON c.id = he.cliente_id
                JOIN usuarios u ON he.usuario_id = u.id
                WHERE c.ativo = 0
                ORDER BY he.data_exclusao DESC
                LIMIT :limite";
        
        $stmt = $connection->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao listar clientes excluídos: " . $e->getMessage());
        return [];
    }
}

/**
 * Restaurar cliente excluído
 * @param PDO $connection Conexão PDO
 * @param int $clienteId ID do cliente
 * @param int $usuarioId ID do usuário que está restaurando
 * @return bool Sucesso da operação
 */
function restaurarCliente($connection, $clienteId, $usuarioId)
{
    try {
        // Verificar se cliente existe e está inativo
        $sql = "SELECT id, nome FROM clientes WHERE id = :id AND ativo = 0";
        $stmt = $connection->prepare($sql);
        $stmt->execute([':id' => $clienteId]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Cliente não encontrado ou já está ativo.");
        }

        // Restaurar cliente
        $sql_restaurar = "UPDATE clientes SET 
                         ativo = 1,
                         data_inativacao = NULL,
                         usuario_inativacao = NULL
                         WHERE id = :id";
        
        $stmt = $connection->prepare($sql_restaurar);
        return $stmt->execute([':id' => $clienteId]);
        
    } catch (PDOException $e) {
        error_log("Erro ao restaurar cliente #$clienteId: " . $e->getMessage());
        return false;
    }
}