<?php
// admin/categorias.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
checkUserPermission('administrador');

$database = new Database();
$conn = $database->getConnection();

// Processar exclusão de categoria
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id_categoria = (int)$_GET['excluir'];
    
    try {
        // Verificar se existem produtos associados
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Produto WHERE id_categoria = :id");
        $stmt->bindParam(':id', $id_categoria);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            flashMessage('Não é possível excluir a categoria pois existem produtos associados', 'error');
        } else {
            $stmt = $conn->prepare("DELETE FROM Categoria WHERE id_categoria = :id");
            $stmt->bindParam(':id', $id_categoria);
            $stmt->execute();
            
            flashMessage('Categoria excluída com sucesso', 'success');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao excluir categoria: ' . $e->getMessage(), 'error');
    }
    
    redirectTo('categorias.php');
}

// Processar adição/edição de categoria
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
    $nome = sanitizeInput($_POST['nome']);
    
    // Validações básicas
    $errors = [];
    if (empty($nome)) $errors[] = "Nome é obrigatório";
    
    if (empty($errors)) {
        try {
            // Verificar se já existe uma categoria com o mesmo nome
            $stmt = $conn->prepare("SELECT COUNT(*) FROM Categoria WHERE nome = :nome AND id_categoria != :id");
            $stmt->bindParam(':nome', $nome);
            $stmt->bindValue(':id', $id_categoria ?: 0);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                flashMessage('Já existe uma categoria com este nome', 'error');
            } else {
                // Se tem ID, atualiza, senão insere
                if ($id_categoria) {
                    $stmt = $conn->prepare("UPDATE Categoria SET nome = :nome WHERE id_categoria = :id_categoria");
                    $stmt->bindParam(':id_categoria', $id_categoria);
                    $mensagem = 'Categoria atualizada com sucesso';
                } else {
                    $stmt = $conn->prepare("INSERT INTO Categoria (nome) VALUES (:nome)");
                    $mensagem = 'Categoria adicionada com sucesso';
                }
                
                $stmt->bindParam(':nome', $nome);
                $stmt->execute();
                flashMessage($mensagem, 'success');
                redirectTo('categorias.php');
            }
        } catch (Exception $e) {
            flashMessage('Erro ao salvar categoria: ' . $e->getMessage(), 'error');
        }
    }
}

// Carregar dados para edição
$categoria = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_categoria = (int)$_GET['editar'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM Categoria WHERE id_categoria = :id");
        $stmt->bindParam(':id', $id_categoria);
        $stmt->execute();
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categoria) {
            flashMessage('Categoria não encontrada', 'error');
            redirectTo('categorias.php');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao carregar categoria: ' . $e->getMessage(), 'error');
    }
}

// Listar todas as categorias
try {
    $stmt = $conn->prepare("
        SELECT c.*, 
            (SELECT COUNT(*) FROM Produto p WHERE p.id_categoria = c.id_categoria) AS total_produtos
        FROM Categoria c
        ORDER BY c.nome
    ");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao listar categorias: ' . $e->getMessage(), 'error');
    $categorias = [];
}

require_once '../includes/header.php';
?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gerenciar Categorias</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoriaModal">
                        <i class="bi bi-plus-lg"></i> Nova Categoria
                    </button>
                </div>

                <?php displayFlashMessage(); ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Categorias Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Lista de Categorias</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Total de Produtos</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $categoria_item): ?>
                                        <tr>
                                            <td><?php echo $categoria_item['id_categoria']; ?></td>
                                            <td><?php echo htmlspecialchars($categoria_item['nome']); ?></td>
                                            <td><?php echo $categoria_item['total_produtos']; ?></td>
                                            <td>
                                                <a href="categorias.php?editar=<?php echo $categoria_item['id_categoria']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="categorias.php?excluir=<?php echo $categoria_item['id_categoria']; ?>" 
                                                   class="btn btn-sm btn-danger <?php echo $categoria_item['total_produtos'] > 0 ? 'disabled' : ''; ?>"
                                                   onclick="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($categorias)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Nenhuma categoria cadastrada</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

    <!-- Modal de Categoria -->
    <div class="modal fade" id="categoriaModal" tabindex="-1" aria-labelledby="categoriaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoriaModalLabel">
                        <?php echo $categoria ? 'Editar Categoria' : 'Nova Categoria'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="categorias.php">
                        <?php if ($categoria): ?>
                            <input type="hidden" name="id_categoria" value="<?php echo $categoria['id_categoria']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome da Categoria</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?php echo $categoria ? htmlspecialchars($categoria['nome']) : ''; ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php if ($categoria): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('categoriaModal')).show();
        });
    </script>
    <?php endif; ?>
    <?php require_once '../includes/footer.php'; ?>