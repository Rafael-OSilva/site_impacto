-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 17/01/2026 às 18:13
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `caixa_impacto`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa`
--

CREATE TABLE `caixa` (
  `id` int(11) NOT NULL,
  `data_abertura` datetime NOT NULL,
  `data_fechamento` datetime DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `usuario_fechamento` int(11) DEFAULT NULL,
  `valor_inicial` decimal(10,2) NOT NULL,
  `valor_final` decimal(10,2) DEFAULT NULL,
  `diferenca` decimal(10,2) DEFAULT NULL,
  `status` enum('aberto','fechado') DEFAULT 'aberto',
  `observacao` text DEFAULT NULL,
  `observacoes_fechamento` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `caixa`
--

INSERT INTO `caixa` (`id`, `data_abertura`, `data_fechamento`, `usuario_id`, `usuario_fechamento`, `valor_inicial`, `valor_final`, `diferenca`, `status`, `observacao`, `observacoes_fechamento`) VALUES
(1, '2025-09-18 13:06:56', '2025-09-18 15:38:17', 1, 1, 200.00, 200.00, 0.00, 'fechado', '', ''),
(2, '2025-09-18 16:38:48', '2025-09-18 16:39:48', 1, 1, 100.00, 126.00, 0.00, 'fechado', '', ''),
(3, '2025-09-18 19:43:10', '2025-09-18 19:46:06', 1, 1, 100.00, 150.00, 0.00, 'fechado', '', ''),
(4, '2025-09-22 10:50:00', '2025-09-22 16:57:51', 1, 1, 100.00, 130.00, 0.00, 'fechado', '', ''),
(5, '2025-09-23 13:35:59', '2025-09-23 16:46:10', 1, 1, 130.00, 122.60, 0.00, 'fechado', '', ''),
(6, '2025-10-04 11:43:44', '2025-10-06 16:59:37', 1, 1, 10.00, 55.30, 0.00, 'fechado', '', ''),
(7, '2025-10-06 17:01:23', '2025-10-06 17:07:02', 1, 1, 55.30, 55.30, 0.00, 'fechado', '', ''),
(8, '2025-10-08 12:08:04', '2025-10-08 16:42:46', 1, 1, 150.00, 160.00, 0.00, 'fechado', '', ''),
(9, '2025-10-13 16:13:18', '2025-10-13 16:14:00', 1, 1, 150.00, 165.00, 0.00, 'fechado', '', ''),
(10, '2025-11-14 15:07:01', '2025-11-17 09:33:40', 1, 1, 23.00, 23.00, 0.00, 'fechado', '', ''),
(11, '2025-11-21 14:37:16', NULL, 1, NULL, 125.65, NULL, NULL, 'aberto', '', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `valor_credito` decimal(10,2) DEFAULT 0.00,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `cpf`, `email`, `telefone`, `valor_credito`, `data_cadastro`, `ativo`) VALUES
(5, 'teste', '0', 'rafinha101419.silva@gmail.com', '(61) 98560-4142', 10.00, '2026-01-16 19:24:55', 1),
(6, 'rafael', '05030496114', NULL, NULL, 15.00, '2026-01-16 20:40:33', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `formas_pagamento`
--

CREATE TABLE `formas_pagamento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `formas_pagamento`
--

INSERT INTO `formas_pagamento` (`id`, `nome`, `descricao`, `ativo`) VALUES
(1, 'Dinheiro', 'Pagamento em espécie', 1),
(2, 'Cartão Débito', 'Pagamento com cartão de débito', 1),
(3, 'Cartão Crédito', 'Pagamento com cartão de crédito', 1),
(4, 'PIX', 'Pagamento via PIX', 1),
(5, 'A Receber', 'Vendas com pagamento futuro (final do mês)', 1),
(7, 'A Receber', NULL, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_credito`
--

CREATE TABLE `historico_credito` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_alteracao` timestamp NOT NULL DEFAULT current_timestamp(),
  `valor_anterior` decimal(10,2) DEFAULT NULL,
  `valor_novo` decimal(10,2) DEFAULT NULL,
  `tipo_operacao` varchar(20) DEFAULT NULL,
  `observacao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `historico_credito`
--

INSERT INTO `historico_credito` (`id`, `cliente_id`, `usuario_id`, `data_alteracao`, `valor_anterior`, `valor_novo`, `tipo_operacao`, `observacao`) VALUES
(1, 5, 1, '2026-01-16 19:24:55', 0.00, 20.00, NULL, 'Crédito inicial'),
(2, 5, 1, '2026-01-16 19:25:12', 20.00, 25.00, 'ajuste', ''),
(3, 5, 1, '2026-01-16 19:26:39', 25.00, 5.00, 'ajuste', ''),
(4, 5, 1, '2026-01-16 19:27:23', 5.00, 10.00, 'ajuste', ''),
(5, 6, 1, '2026-01-16 20:40:33', 0.00, 15.00, NULL, 'Crédito inicial');

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_exclusoes`
--

CREATE TABLE `historico_exclusoes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nome_cliente` varchar(100) NOT NULL,
  `motivo` text DEFAULT NULL,
  `data_exclusao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_venda`
--

CREATE TABLE `itens_venda` (
  `id` int(11) NOT NULL,
  `venda_id` int(11) NOT NULL,
  `produto_nome` varchar(100) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `retiradas`
--

CREATE TABLE `retiradas` (
  `id` int(11) NOT NULL,
  `caixa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_retirada` datetime NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `retiradas`
--

INSERT INTO `retiradas` (`id`, `caixa_id`, `usuario_id`, `data_retirada`, `valor`, `motivo`, `observacao`) VALUES
(1, 1, 1, '2025-09-18 15:37:35', 12.30, 'Lanche', NULL),
(2, 5, 1, '2025-09-23 16:44:45', 50.00, 'lanche', NULL),
(4, 6, 1, '2025-10-06 16:47:34', 15.00, 'compras', NULL),
(6, 8, 1, '2025-10-08 12:27:19', 15.00, 'compras', NULL),
(7, 9, 1, '2025-10-13 16:13:48', 15.00, 'compras', NULL),
(8, 11, 1, '2026-01-17 09:30:25', 5.65, 'escritorio', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel_acesso` enum('admin','operador') DEFAULT 'operador',
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `usuario`, `senha`, `nivel_acesso`, `ativo`, `data_criacao`) VALUES
(1, 'Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2025-09-17 17:47:42'),
(2, 'Operador', 'operador', '1234', 'operador', 1, '2025-09-17 17:47:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `caixa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `data_venda` datetime NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `forma_pagamento_id` int(11) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('concluida','cancelada') DEFAULT 'concluida',
  `visivel_contas_receber` tinyint(1) DEFAULT 1,
  `data_recebimento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendas`
--

INSERT INTO `vendas` (`id`, `caixa_id`, `usuario_id`, `cliente_id`, `data_venda`, `valor_total`, `forma_pagamento_id`, `descricao`, `status`, `visivel_contas_receber`, `data_recebimento`) VALUES
(1, 1, 1, NULL, '2025-09-18 15:34:46', 50.90, 3, '', 'concluida', 0, NULL),
(2, 1, 1, NULL, '2025-09-18 15:34:57', 12.30, 1, '', 'concluida', 0, NULL),
(3, 1, 1, NULL, '2025-09-18 15:36:54', 16.70, 4, '', 'concluida', 0, NULL),
(4, 2, 1, NULL, '2025-09-18 16:39:12', 26.00, 1, '', 'concluida', 0, NULL),
(5, 3, 1, NULL, '2025-09-18 19:44:05', 50.00, 1, '', 'concluida', 0, NULL),
(6, 3, 1, NULL, '2025-09-18 19:44:29', 50.00, 3, '', 'concluida', 0, NULL),
(7, 3, 1, NULL, '2025-09-18 19:44:35', 60.00, 2, '', 'concluida', 0, NULL),
(8, 4, 1, NULL, '2025-09-22 14:17:29', 2.50, 3, '', 'concluida', 0, NULL),
(9, 4, 1, NULL, '2025-09-22 14:17:57', 12.00, 5, '', 'concluida', 0, '2025-09-23 15:58:53'),
(10, 4, 1, NULL, '2025-09-22 14:34:10', 12.00, 5, '', 'concluida', 0, '2025-09-23 15:58:51'),
(12, 4, 1, NULL, '2025-09-22 15:30:55', 25.00, 5, 'Cliente: teste', 'concluida', 0, '2025-09-23 15:58:49'),
(13, 4, 1, NULL, '2025-09-22 15:31:31', 20.00, 1, '', 'concluida', 0, NULL),
(14, 4, 1, NULL, '2025-09-22 15:36:57', 2.00, 5, 'Cliente: a', 'concluida', 0, '2025-09-23 15:58:47'),
(15, 4, 1, NULL, '2025-09-22 15:37:04', 2.00, 7, 'Cliente: b', 'concluida', 0, '2025-09-23 15:58:45'),
(16, 4, 1, NULL, '2025-09-22 15:40:27', 200.00, 5, 'Cliente: a', 'concluida', 0, '2025-09-23 15:58:43'),
(17, 4, 1, NULL, '2025-09-22 16:22:37', 200.00, 5, 'Cliente: aa', 'concluida', 0, '2025-09-23 15:58:41'),
(18, 4, 1, NULL, '2025-09-22 16:33:20', 200.00, 5, 'Cliente: asd', 'concluida', 0, '2025-09-23 15:58:17'),
(19, 4, 1, NULL, '2025-09-22 16:35:43', 10.00, 1, '', 'concluida', 0, NULL),
(20, 4, 1, NULL, '2025-09-22 16:44:30', 10.00, 5, 'Cliente: teste | jklkk,', 'concluida', 0, '2025-09-23 15:58:38'),
(21, 4, 1, NULL, '2025-09-22 16:54:55', 200.00, 5, 'Cliente: teste', 'concluida', 0, '2025-09-23 15:58:34'),
(22, 4, 1, NULL, '2025-09-22 16:56:55', 200.00, 5, 'Cliente: aa', 'concluida', 0, '2025-09-23 15:58:26'),
(23, 5, 1, NULL, '2025-09-23 13:36:13', 10.00, 5, 'Cliente: teste 1', 'concluida', 0, '2025-09-23 15:58:09'),
(24, 5, 1, NULL, '2025-09-23 14:28:53', 12.60, 1, 'Copias', 'concluida', 0, NULL),
(25, 5, 1, NULL, '2025-09-23 15:41:19', 13.00, 5, 'Cliente: teste c', 'concluida', 0, '2025-09-23 15:57:54'),
(26, 5, 1, NULL, '2025-09-23 16:02:08', 12.50, 5, 'Cliente: teste r', 'concluida', 0, '2025-09-23 16:31:10'),
(27, 5, 1, NULL, '2025-09-23 16:40:04', 10.00, 3, '', 'concluida', 1, NULL),
(28, 5, 1, NULL, '2025-09-23 16:40:09', 20.00, 2, '', 'concluida', 1, NULL),
(29, 5, 1, NULL, '2025-09-23 16:40:13', 30.00, 1, '', 'concluida', 1, NULL),
(30, 5, 1, NULL, '2025-09-23 16:40:17', 40.00, 4, '', 'concluida', 1, NULL),
(31, 5, 1, NULL, '2025-09-23 16:43:48', 2.10, 5, 'Cliente: teste o', 'concluida', 0, '2025-09-23 16:45:43'),
(32, 5, 1, NULL, '2025-09-23 16:44:14', 6.00, 5, 'Cliente: teste o', 'concluida', 0, '2025-09-23 16:45:41'),
(33, 6, 1, NULL, '2025-10-04 11:43:57', 50.00, 5, 'Cliente: teste', 'concluida', 0, '2025-10-04 12:51:36'),
(34, 6, 1, NULL, '2025-10-04 12:51:25', 10.00, 5, 'Cliente: te', 'concluida', 0, '2025-10-04 12:51:34'),
(35, 6, 1, NULL, '2025-10-06 10:15:07', 10.30, 1, '', 'concluida', 1, NULL),
(36, 6, 1, NULL, '2025-10-06 10:15:16', 80.00, 3, '', 'concluida', 1, NULL),
(37, 6, 1, NULL, '2025-10-06 10:15:20', 65.00, 2, '', 'concluida', 1, NULL),
(38, 6, 1, NULL, '2025-10-06 10:15:24', 25.00, 4, '', 'concluida', 1, NULL),
(39, 6, 1, NULL, '2025-10-06 10:15:27', 50.00, 1, '', 'concluida', 1, NULL),
(40, 6, 1, NULL, '2025-10-06 16:57:11', 10.00, 5, 'Cliente: teste', 'concluida', 0, '2025-10-06 17:06:39'),
(41, 8, 1, NULL, '2025-10-08 12:08:35', 15.00, 3, '', 'concluida', 1, NULL),
(42, 8, 1, NULL, '2025-10-08 12:08:41', 20.00, 2, '', 'concluida', 1, NULL),
(43, 8, 1, NULL, '2025-10-08 12:08:48', 25.00, 1, '', 'concluida', 1, NULL),
(44, 8, 1, NULL, '2025-10-08 12:08:53', 30.00, 4, '', 'concluida', 1, NULL),
(45, 8, 1, NULL, '2025-10-08 12:09:04', 50.00, 5, 'Cliente: teste', 'concluida', 0, '2025-10-08 12:09:09'),
(46, 8, 1, NULL, '2025-10-08 12:22:08', 50.00, 5, 'Cliente: teste2.0', 'concluida', 0, '2025-10-08 12:46:02'),
(47, 8, 1, NULL, '2025-10-08 12:46:02', 50.00, 4, 'RECEBIMENTO - Conta #46 - Cliente: teste2.0', 'concluida', 1, NULL),
(48, 8, 1, NULL, '2025-10-08 12:47:18', 20.00, 5, 'Cliente: iris', 'concluida', 0, '2025-10-08 12:48:20'),
(49, 8, 1, NULL, '2025-10-08 12:47:40', 15.00, 5, 'Cliente: igor', 'concluida', 0, '2025-10-08 12:48:17'),
(50, 8, 1, NULL, '2025-10-08 12:48:17', 15.00, 4, 'RECEBIMENTO - Conta #49 - Cliente: igor', 'concluida', 1, NULL),
(51, 8, 1, NULL, '2025-10-08 12:48:20', 20.00, 4, 'RECEBIMENTO - Conta #48 - Cliente: iris', 'concluida', 1, NULL),
(52, 9, 1, NULL, '2025-10-13 16:13:23', 10.00, 3, '', 'concluida', 1, NULL),
(53, 9, 1, NULL, '2025-10-13 16:13:27', 20.00, 2, '', 'concluida', 1, NULL),
(54, 9, 1, NULL, '2025-10-13 16:13:30', 30.00, 1, '', 'concluida', 1, NULL),
(55, 9, 1, NULL, '2025-10-13 16:13:33', 40.00, 4, '', 'concluida', 1, NULL),
(56, 10, 1, NULL, '2025-11-14 15:07:13', 50.00, 5, 'Cliente: teste', 'concluida', 0, '2026-01-05 08:41:22'),
(57, 11, 1, NULL, '2025-11-21 14:37:28', 10.50, 5, 'Cliente: teste 5', 'concluida', 0, '2026-01-05 08:41:19'),
(58, 11, 1, NULL, '2026-01-05 08:41:06', 25.50, 5, 'Cliente: asd', 'concluida', 0, '2026-01-05 08:41:15'),
(59, 11, 1, NULL, '2026-01-05 08:41:15', 25.50, 3, 'RECEBIMENTO - Conta #58 - Cliente: asd', 'concluida', 1, NULL),
(60, 11, 1, NULL, '2026-01-05 08:41:19', 10.50, 4, 'RECEBIMENTO - Conta #57 - Cliente: teste 5', 'concluida', 1, NULL),
(61, 11, 1, NULL, '2026-01-05 08:41:22', 50.00, 4, 'RECEBIMENTO - Conta #56 - Cliente: teste', 'concluida', 1, NULL),
(62, 11, 1, NULL, '2026-01-14 11:16:45', 56.22, 5, 'Cliente: teste', 'concluida', 0, '2026-01-14 11:16:55'),
(64, 11, 1, NULL, '2026-01-17 09:29:32', 50.00, 5, 'Cliente: marcos', 'concluida', 0, '2026-01-17 09:29:52'),
(65, 11, 1, NULL, '2026-01-17 09:29:52', 50.00, 4, 'RECEBIMENTO - Conta #64 - Cliente: marcos', 'concluida', 1, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `caixa`
--
ALTER TABLE `caixa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `usuario_fechamento` (`usuario_fechamento`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf` (`cpf`);

--
-- Índices de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `historico_credito`
--
ALTER TABLE `historico_credito`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `historico_exclusoes`
--
ALTER TABLE `historico_exclusoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `itens_venda`
--
ALTER TABLE `itens_venda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venda_id` (`venda_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `retiradas`
--
ALTER TABLE `retiradas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caixa_id` (`caixa_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caixa_id` (`caixa_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `forma_pagamento_id` (`forma_pagamento_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `caixa`
--
ALTER TABLE `caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `historico_credito`
--
ALTER TABLE `historico_credito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `historico_exclusoes`
--
ALTER TABLE `historico_exclusoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `itens_venda`
--
ALTER TABLE `itens_venda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `retiradas`
--
ALTER TABLE `retiradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `caixa`
--
ALTER TABLE `caixa`
  ADD CONSTRAINT `caixa_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `caixa_ibfk_2` FOREIGN KEY (`usuario_fechamento`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `historico_credito`
--
ALTER TABLE `historico_credito`
  ADD CONSTRAINT `historico_credito_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `historico_credito_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `historico_exclusoes`
--
ALTER TABLE `historico_exclusoes`
  ADD CONSTRAINT `historico_exclusoes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `historico_exclusoes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `itens_venda`
--
ALTER TABLE `itens_venda`
  ADD CONSTRAINT `itens_venda_ibfk_1` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `retiradas`
--
ALTER TABLE `retiradas`
  ADD CONSTRAINT `retiradas_ibfk_1` FOREIGN KEY (`caixa_id`) REFERENCES `caixa` (`id`),
  ADD CONSTRAINT `retiradas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `vendas_ibfk_1` FOREIGN KEY (`caixa_id`) REFERENCES `caixa` (`id`),
  ADD CONSTRAINT `vendas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `vendas_ibfk_3` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`),
  ADD CONSTRAINT `vendas_ibfk_4` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
