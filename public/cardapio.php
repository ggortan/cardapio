<?php
// public/cardapio.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para acessar o cardápio', 'error');
    redirectTo('../login.php');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Buscar categorias com produtos
    $stmt = $conn->prepare("
        SELECT c.id_categoria, c.nome AS categoria_nome, 
               p.id_produto, p.nome, p.descricao, p.preco, p.imagem_url
        FROM Categoria c
        LEFT JOIN Produto p ON c.id_categoria = p.id_categoria
        ORDER BY c.nome, p.nome
    ");
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_GROUP);
} catch (Exception $e) {
    flashMessage('Erro ao carregar cardápio: ' . $e->getMessage(), 'error');
    $produtos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cardápio Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card {
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">Cardápio Digital</a>
            <div class="navbar-nav ms-auto">
                <a href="carrinho.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-cart"></i> Carrinho
                </a>
                <a href="../logout.php" class="btn btn-outline-danger">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Nosso Cardápio</h1>
        <?php displayFlashMessage(); ?>

        <?php foreach ($produtos as $categoria => $itens): ?>
            <div class="mb-4">
                <h2><?php echo htmlspecialchars($categoria); ?></h2>
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php foreach ($itens as $produto): ?>
                        <div class="col">
                            <div class="card h-100 product-card">
                                <?php if ($produto['imagem_url']): ?>
                                    <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($produto['nome']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></p>
                                    <p class="card-text fw-bold text-success">
                                        <?php echo formatCurrency($produto['preco']); ?>
                                    </p>
                                    <form action="adicionar-carrinho.php" method="POST">
                                        <input type="hidden" name="id_produto" value="<?php echo $produto['id_produto']; ?>">
                                        <div class="input-group">
                                            <input type="number" name="quantidade" 
                                                   class="form-control" value="1" min="1" max="10">
                                            <button type="submit" class="btn btn-primary">
                                                Adicionar ao Carrinho
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</body>
</html>