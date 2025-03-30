<?php
// admin/produtos.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
checkUserPermission('administrador');

$database = new Database();
$conn = $database->getConnection();

// Carregar categorias para o formulário
try {
    $stmt = $conn->query("SELECT id_categoria, nome FROM Categoria ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao carregar categorias: ' . $e->getMessage(), 'error');
    $categorias = [];
}

// Processar exclusão de produto
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id_produto = (int)$_GET['excluir'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM Produto WHERE id_produto = :id");
        $stmt->bindParam(':id', $id_produto);
        $stmt->execute();
        
        flashMessage('Produto excluído com sucesso', 'success');
    } catch (Exception $e) {
        flashMessage('Erro ao excluir produto: ' . $e->getMessage(), 'error');
    }
    
    redirectTo('produtos.php');
}

// Processar adição/edição de produto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_produto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);
    $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
    $imagem_url = sanitizeInput($_POST['imagem_url']);
    
    // Validações básicas
    $errors = [];
    if (empty($nome)) $errors[] = "Nome é obrigatório";
    if ($preco === false || $preco <= 0) $errors[] = "Preço deve ser um número positivo";
    if ($id_categoria === false) $errors[] = "Categoria válida é obrigatória";
    
    if (empty($errors)) {
        try {
            // Se tem ID, atualiza, senão insere
            if ($id_produto) {
                $stmt = $conn->prepare("
                    UPDATE Produto SET 
                        nome = :nome, 
                        descricao = :descricao, 
                        preco = :preco, 
                        id_categoria = :id_categoria, 
                        imagem_url = :imagem_url
                    WHERE id_produto = :id_produto
                ");
                $stmt->bindParam(':id_produto', $id_produto);
                $mensagem = 'Produto atualizado com sucesso';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO Produto (
                        nome, descricao, preco, id_categoria, imagem_url
                    ) VALUES (
                        :nome, :descricao, :preco, :id_categoria, :imagem_url
                    )
                ");
                $mensagem = 'Produto adicionado com sucesso';
            }
            
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':preco', $preco);
            $stmt->bindParam(':id_categoria', $id_categoria);
            $stmt->bindParam(':imagem_url', $imagem_url);
            
            $stmt->execute();
            flashMessage($mensagem, 'success');
            redirectTo('produtos.php');
        } catch (Exception $e) {
            flashMessage('Erro ao salvar produto: ' . $e->getMessage(), 'error');
        }
    }
}

// Carregar dados para edição
$produto = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_produto = (int)$_GET['editar'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM Produto WHERE id_produto = :id");
        $stmt->bindParam(':id', $id_produto);
        $stmt->execute();
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            flashMessage('Produto não encontrado', 'error');
            redirectTo('produtos.php');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao carregar produto: ' . $e->getMessage(), 'error');
    }
}

// Listar todos os produtos
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.nome AS categoria_nome
        FROM Produto p
        JOIN Categoria c ON p.id_categoria = c.id_categoria
        ORDER BY p.nome
    ");
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao listar produtos: ' . $e->getMessage(), 'error');
    $produtos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Produtos - Administração</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pedidos.php">
                                <i class="bi bi-list-check"></i> Pedidos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="produtos.php">
                                <i class="bi bi-box"></i> Produtos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categorias.php">
                                <i class="bi bi-tag"></i> Categorias
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="usuarios.php">
                                <i class="bi bi-people"></i> Usuários
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gerenciar Produtos</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#produtoModal">
                        <i class="bi bi-plus-lg"></i> Novo Produto
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

                <!-- Produtos Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Lista de Produtos</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Imagem</th>
                                        <th>Nome</th>
                                        <th>Categoria</th>
                                        <th>Preço</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $produto_item): ?>
                                        <tr>
                                            <td><?php echo $produto_item['id_produto']; ?></td>
                                            <td>
                                                <?php if (!empty($produto_item['imagem_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($produto_item['imagem_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($produto_item['nome']); ?>" 
                                                         class="img-thumbnail" style="max-width: 50px;">
                                                <?php else: ?>
                                                    <span class="text-muted">Sem imagem</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($produto_item['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($produto_item['categoria_nome']); ?></td>
                                            <td><?php echo formatCurrency($produto_item['preco']); ?></td>
                                            <td>
                                                <a href="produtos.php?editar=<?php echo $produto_item['id_produto']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="produtos.php?excluir=<?php echo $produto_item['id_produto']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Tem certeza que deseja excluir este produto?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($produtos)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhum produto cadastrado</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Produto -->
    <div class="modal fade" id="produtoModal" tabindex="-1" aria-labelledby="produtoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="produtoModalLabel">
                        <?php echo $produto ? 'Editar Produto' : 'Novo Produto'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="produtos.php">
                        <?php if ($produto): ?>
                            <input type="hidden" name="id_produto" value="<?php echo $produto['id_produto']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Produto</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?php echo $produto ? htmlspecialchars($produto['nome']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php 
                                echo $produto ? htmlspecialchars($produto['descricao']) : ''; 
                            ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="preco" class="form-label">Preço</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="preco" name="preco" 
                                           step="0.01" min="0.01"
                                           value="<?php echo $produto ? htmlspecialchars($produto['preco']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="id_categoria" class="form-label">Categoria</label>
                                <select class="form-select" id="id_categoria" name="id_categoria" required>
                                    <option value="">Selecione uma categoria</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id_categoria']; ?>"
                                            <?php echo $produto && $produto['id_categoria'] == $categoria['id_categoria'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($categoria['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="imagem_url" class="form-label">URL da Imagem</label>
                            <input type="text" class="form-control" id="imagem_url" name="imagem_url" 
                                   value="<?php echo $produto ? htmlspecialchars($produto['imagem_url']) : ''; ?>">
                            <div class="form-text">Deixe em branco para não exibir imagem</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($produto): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('produtoModal')).show();
        });
    </script>
    <?php endif; ?>
</body>
</html>