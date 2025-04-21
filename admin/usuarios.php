<?php
// admin/usuarios.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
checkUserPermission('administrador');

$database = new Database();
$conn = $database->getConnection();

// Processar ativação/desativação de usuário
if (isset($_GET['alternar_status']) && is_numeric($_GET['alternar_status'])) {
    $id_usuario = (int)$_GET['alternar_status'];
    
    try {
        // Primeiro, obter status atual
        $stmt = $conn->prepare("SELECT status FROM Usuario WHERE id_usuario = :id");
        $stmt->bindParam(':id', $id_usuario);
        $stmt->execute();
        $status_atual = $stmt->fetchColumn();
        
        // Se não encontrou ou é o próprio usuário logado, não permitir
        if (!$status_atual || $id_usuario == $_SESSION['usuario_id']) {
            flashMessage('Não é possível alterar este usuário', 'error');
        } else {
            // Alternar status
            $novo_status = ($status_atual == 'ativo') ? 'inativo' : 'ativo';
            
            $stmt = $conn->prepare("UPDATE Usuario SET status = :status WHERE id_usuario = :id");
            $stmt->bindParam(':status', $novo_status);
            $stmt->bindParam(':id', $id_usuario);
            $stmt->execute();
            
            flashMessage('Status do usuário alterado com sucesso', 'success');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao alterar status: ' . $e->getMessage(), 'error');
    }
    
    redirectTo('usuarios.php');
}

// Processar alteração de tipo de usuário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alterar_tipo'])) {
    $id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
    $tipo_usuario = filter_input(INPUT_POST, 'tipo_usuario', FILTER_SANITIZE_STRING);
    
    if ($id_usuario && in_array($tipo_usuario, ['consumidor', 'operador', 'administrador'])) {
        try {
            // Não permitir alterar próprio tipo
            if ($id_usuario == $_SESSION['usuario_id']) {
                flashMessage('Não é possível alterar seu próprio tipo de usuário', 'error');
            } else {
                $stmt = $conn->prepare("UPDATE Usuario SET tipo_usuario = :tipo WHERE id_usuario = :id");
                $stmt->bindParam(':tipo', $tipo_usuario);
                $stmt->bindParam(':id', $id_usuario);
                $stmt->execute();
                
                flashMessage('Tipo de usuário alterado com sucesso', 'success');
            }
        } catch (Exception $e) {
            flashMessage('Erro ao alterar tipo: ' . $e->getMessage(), 'error');
        }
    }
    
    redirectTo('usuarios.php');
}

// Listar todos os usuários
try {
    $stmt = $conn->prepare("
        SELECT u.*, 
            (SELECT COUNT(*) FROM Pedido WHERE id_usuario = u.id_usuario) AS total_pedidos
        FROM Usuario u
        ORDER BY u.nome
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao listar usuários: ' . $e->getMessage(), 'error');
    $usuarios = [];
}

require_once '../includes/header.php';
?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gerenciar Usuários</h1>
                </div>

                <?php displayFlashMessage(); ?>

                <!-- Usuários Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Lista de Usuários</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Data Cadastro</th>
                                        <th>Pedidos</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?php echo $usuario['id_usuario']; ?></td>
                                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                                    <select name="tipo_usuario" class="form-select form-select-sm" 
                                                            onchange="this.form.submit()"
                                                            <?php echo $usuario['id_usuario'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                                                        <option value="consumidor" <?php echo $usuario['tipo_usuario'] == 'consumidor' ? 'selected' : ''; ?>>Consumidor</option>
                                                        <option value="operador" <?php echo $usuario['tipo_usuario'] == 'operador' ? 'selected' : ''; ?>>Operador</option>
                                                        <option value="administrador" <?php echo $usuario['tipo_usuario'] == 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                                                    </select>
                                                    <input type="hidden" name="alterar_tipo" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $usuario['status'] == 'ativo' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ucfirst($usuario['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($usuario['data_criacao'])); ?></td>
                                            <td><?php echo $usuario['total_pedidos']; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="usuarios.php?alternar_status=<?php echo $usuario['id_usuario']; ?>" 
                                                    class="btn btn-sm <?php echo $usuario['status'] == 'ativo' ? 'btn-warning' : 'btn-success'; ?>"
                                                    <?php echo $usuario['id_usuario'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>>
                                                        <?php echo $usuario['status'] == 'ativo' ? '<i class="bi bi-x-circle"></i> Desativar' : '<i class="bi bi-check-circle"></i> Ativar'; ?>
                                                    </a>
                                                    
                                                    <!-- Adicione este link para redefinir senha -->
                                                    <a href="redefinir-senha.php?id=<?php echo $usuario['id_usuario']; ?>" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    <?php echo $usuario['id_usuario'] == $_SESSION['usuario_id'] ? 'disabled' : ''; ?>
                                                    title="Redefinir senha">
                                                        <i class="bi bi-key"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($usuarios)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Nenhum usuário encontrado</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php require_once '../includes/footer.php'; ?>