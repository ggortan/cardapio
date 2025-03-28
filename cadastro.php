<?php
// cadastro.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $nome = sanitizeInput($_POST['nome']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    $confirma_senha = $_POST['confirma_senha'];

    // Validação básica
    $errors = [];
    if (empty($nome)) $errors[] = "Nome é obrigatório";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email inválido";
    if (strlen($senha) < 8) $errors[] = "Senha deve ter pelo menos 8 caracteres";
    if ($senha !== $confirma_senha) $errors[] = "Senhas não coincidem";

    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Verificar se email já existe
            $stmt = $conn->prepare("SELECT COUNT(*) FROM Usuario WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Este email já está cadastrado";
            } else {
                // Preparar inserção
                $senhaHash = passwordHash($senha);
                $stmt = $conn->prepare("
                    INSERT INTO Usuario (nome, email, senha, tipo_usuario) 
                    VALUES (:nome, :email, :senha, 'consumidor')
                ");

                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':senha', $senhaHash);

                if ($stmt->execute()) {
                    flashMessage('Cadastro realizado com sucesso!', 'success');
                    redirectTo('login.php');
                } else {
                    $errors[] = "Erro ao cadastrar usuário";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Erro no cadastro: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - Cardápio Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Cadastro de Usuário</div>
                    <div class="card-body">
                        <?php 
                        // Exibir erros de validação
                        if (!empty($errors)) {
                            echo '<div class="alert alert-danger">';
                            foreach ($errors as $error) {
                                echo "<p>$error</p>";
                            }
                            echo '</div>';
                        }
                        ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" 
                                       value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>" 
                                       required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                                       required>
                            </div>
                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="senha" name="senha" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirma_senha" class="form-label">Confirme a Senha</label>
                                <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Cadastrar</button>
                            <a href="login.php" class="btn btn-link">Já tenho cadastro</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>