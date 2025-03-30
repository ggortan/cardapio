<?php
// admin/redefinir-senha.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
checkUserPermission('administrador');

$database = new Database();
$conn = $database->getConnection();

// Verificar se um ID de usuário foi passado
$id_usuario = isset($_GET['id']) ? filter_input(INPUT_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$id_usuario) {
    flashMessage('ID de usuário inválido', 'error');
    redirectTo('usuarios.php');
}

// Carregar informações do usuário
try {
    $stmt = $conn->prepare("SELECT id_usuario, nome, email, tipo_usuario, status FROM Usuario WHERE id_usuario = :id");
    $stmt->bindParam(':id', $id_usuario);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        flashMessage('Usuário não encontrado', 'error');
        redirectTo('usuarios.php');
    }
} catch (Exception $e) {
    flashMessage('Erro ao carregar dados do usuário: ' . $e->getMessage(), 'error');
    redirectTo('usuarios.php');
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nova_senha = $_POST['nova_senha'];
    $confirma_senha = $_POST['confirma_senha'];
    
    // Validações básicas
    $errors = [];
    if (strlen($nova_senha) < 8) $errors[] = "Senha deve ter pelo menos 8 caracteres";
    if ($nova_senha !== $confirma_senha) $errors[] = "Senhas não coincidem";
    
    if (empty($errors)) {
        try {
            // Atualizar senha
            $senha_hash = passwordHash($nova_senha);
            
            $stmt = $conn->prepare("UPDATE Usuario SET senha = :senha WHERE id_usuario = :id");
            $stmt->bindParam(':senha', $senha_hash);
            $stmt->bindParam(':id', $id_usuario);
            $stmt->execute();
            
            // Registrar ação em log (opcional)
            $admin_id = $_SESSION['usuario_id'];
            $stmt = $conn->prepare("
                INSERT INTO Log (tipo_acao, descricao, id_usuario, id_admin, data_acao)
                VALUES ('redefinicao_senha', 'Senha redefinida pelo administrador', :id_usuario, :id_admin, NOW())
            ");
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':id_admin', $admin_id);
            $stmt->execute();
            
            flashMessage('Senha atualizada com sucesso para o usuário ' . $usuario['nome'], 'success');
            redirectTo('usuarios.php');
        } catch (Exception $e) {
            flashMessage('Erro ao atualizar senha: ' . $e->getMessage(), 'error');
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Redefinir Senha do Usuário</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h5>Detalhes do Usuário</h5>
                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($usuario['nome']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                        <p>
                            <strong>Tipo:</strong> <?php echo ucfirst($usuario['tipo_usuario']); ?>
                            <span class="badge <?php echo $usuario['status'] == 'ativo' ? 'bg-success' : 'bg-danger'; ?> ms-2">
                                <?php echo ucfirst($usuario['status']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <form method="POST">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Atenção:</strong> Esta ação irá redefinir a senha do usuário. 
                            Esta operação não pode ser desfeita.
                        </div>
                        
                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="8">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">A senha deve ter pelo menos 8 caracteres.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirma_senha" class="form-label">Confirme a Nova Senha</label>
                            <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Redefinir Senha</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('nova_senha');
    
    togglePassword.addEventListener('click', function() {
        // Toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // Toggle the icon
        this.querySelector('i').classList.toggle('bi-eye');
        this.querySelector('i').classList.toggle('bi-eye-slash');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>