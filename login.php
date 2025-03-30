<?php
// login.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// If already logged in, redirect to appropriate page
if (isLoggedIn()) {
    switch ($_SESSION['tipo_usuario']) {
        case 'administrador':
            redirectTo('admin/dashboard.php');
            break;
        case 'operador':
            redirectTo('admin/pedidos.php');
            break;
        default:
            redirectTo('public/cardapio.php');
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email']);
    $senha = $_POST['senha'];

    try {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("
            SELECT id_usuario, nome, senha, tipo_usuario 
            FROM Usuario 
            WHERE email = :email AND status = 'ativo'
        ");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && passwordVerify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['nome_usuario'] = $usuario['nome'];
            $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

            // Redireciona com base no tipo de usuário
            switch ($usuario['tipo_usuario']) {
                case 'administrador':
                    redirectTo('admin/dashboard.php');
                    break;
                case 'operador':
                    redirectTo('admin/pedidos.php');
                    break;
                default:
                    redirectTo('public/cardapio.php');
            }
        } else {
            flashMessage('Email ou senha inválidos', 'error');
        }
    } catch (Exception $e) {
        flashMessage('Erro no login: ' . $e->getMessage(), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Cardápio Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card shadow">
                <div class="card-header text-center bg-primary text-white">
                    <h3>Cardápio Digital</h3>
                </div>
                <div class="card-body">
                    <?php displayFlashMessage(); ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Digite seu email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       placeholder="Digite sua senha" required>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center mt-3">
                    <p>
                        <a href="cadastro.php">Cadastre-se</a> | 
                        <a href="esqueci-senha.php">Esqueci minha senha</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</body>
</html>