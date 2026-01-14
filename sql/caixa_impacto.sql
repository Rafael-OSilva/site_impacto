-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 18/09/2025 às 18:22
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
(1, '2025-09-18 13:06:56', NULL, 1, NULL, 200.00, NULL, NULL, 'aberto', '', NULL);

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
(5, 'Transferência', 'Transferência bancária', 1);

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
(2, 'Operador', 'operador', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operador', 1, '2025-09-17 17:47:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `caixa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_venda` datetime NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `forma_pagamento_id` int(11) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('concluida','cancelada') DEFAULT 'concluida'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Índices de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `forma_pagamento_id` (`forma_pagamento_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `caixa`
--
ALTER TABLE `caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `retiradas`
--
ALTER TABLE `retiradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `vendas_ibfk_3` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
