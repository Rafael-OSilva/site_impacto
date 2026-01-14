<?php
session_start();

// Se já estiver logado, redirecionar para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    // Simulação de autenticação (substituir por consulta ao banco)
    if ($usuario === 'admin' && $senha === '1234') {
        $_SESSION['usuario_id'] = 1;
        $_SESSION['usuario_nome'] = 'Administrador';
        header('Location: index.php');
        exit;
    } else {
        $erro = "Usuário ou senha inválidos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impacto Sistema - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ======= Reset ======= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            height: 100vh;
            background: url('./imagens/login.png') center/cover no-repeat;
            position: relative;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* ======= Escurecer o fundo ======= */
        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 0;
        }

        /* ======= Cabeçalho ======= */
        header {
            padding: 60px;
            position: absolute;
            top: 5%;
            text-align: center;
            z-index: 1;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 600;
        }

        header .impacto {
            background: #457eff;
            color: #fff;
            padding: 5px 15px;
            border-radius: 12px;
            margin-right: 5px;
        }

        header .sistema {
            color: #f9f9f9;
            font-weight: 400;
        }

        header .subtitulo {
            font-size: 0.9rem;
            margin-top: 8px;
            letter-spacing: 1px;
            color: #dcdcdc;
        }

        /* ======= Login Box ======= */
        .login-container {
            position: relative;
            z-index: 1;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 280px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .user-icon {
            font-size: 80px;
            margin-bottom: 20px;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .input {
            width: 100%;
            padding: 12px 15px;
            border: none;
            border-radius: 20px;
            margin-bottom: 15px;
            outline: none;
            background: rgba(255, 255, 255, 0.4);
            color: #000;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input::placeholder {
            color: #555;
        }

        .input:focus {
            background: rgba(255, 255, 255, 0.6);
            transform: scale(1.02);
        }

        .login-btn {
            background: none;
            border: none;
            font-size: 2rem;
            color: #457eff;
            cursor: pointer;
            transition: 0.3s;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
        }

        .login-btn:hover {
            transform: scale(1.1);
            color: #5c8dff;
            background: rgba(255, 255, 255, 0.3);
        }

        /* ======= Mensagem de erro ======= */
        .alert {
            padding: 10px 15px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.9rem;
            width: 100%;
            backdrop-filter: blur(5px);
        }

        /* ======= Responsivo ======= */
        @media (max-width: 500px) {
            header {
                padding: 40px 20px;
            }
            
            header h1 {
                font-size: 2rem;
            }

            .login-box {
                width: 85%;
                max-width: 280px;
            }
        }

        @media (max-height: 600px) {
            header {
                position: relative;
                top: 0;
                padding: 20px;
            }
            
            body {
                justify-content: flex-start;
                padding-top: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="overlay"></div>

    <header>
        <h1>
            <span class="impacto">IMPACTO</span>
            <span class="sistema">Sistema</span>
        </h1>
        <p class="subtitulo">SEU FINANCEIRO AINDA MAIS SEGURO</p>
    </header>

    <main class="login-container">
        <div class="login-box">
            <div class="user-icon">
                <i class="fas fa-user" style="font-size: 2.5rem;"></i>
            </div>
            
            <?php if (isset($erro)): ?>
                <div class="alert">
                    <?= $erro ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <input type="text" name="usuario" placeholder="Usuário" class="input" required autofocus>
                <input type="password" name="senha" placeholder="Senha" class="input" required>
                <button type="submit" class="login-btn">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </main>

    <script>
        // Adicionar foco automático no primeiro campo
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Efeito de hover nos inputs
        const inputs = document.querySelectorAll('.input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>