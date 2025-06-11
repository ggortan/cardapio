<?php
// menu.php - Página pública para visualizar o cardápio sem login
require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Buscar categorias com produtos
    $stmt = $conn->prepare("
        SELECT c.id_categoria, c.nome AS categoria_nome, 
               p.id_produto, p.nome, p.descricao, p.preco, p.imagem_url
        FROM Categoria c
        LEFT JOIN Produto p ON c.id_categoria = p.id_categoria
        WHERE p.id_produto IS NOT NULL
        ORDER BY c.nome, p.nome
    ");
    $stmt->execute();
    
    // Agrupar produtos por categoria
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $categorias = [];
    
    foreach ($resultados as $row) {
        $id_categoria = $row['id_categoria'];
        $categoria_nome = $row['categoria_nome'];
        
        if (!isset($categorias[$id_categoria])) {
            $categorias[$id_categoria] = [
                'nome' => $categoria_nome,
                'produtos' => []
            ];
        }
        
        $categorias[$id_categoria]['produtos'][] = [
            'id_produto' => $row['id_produto'],
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'preco' => $row['preco'],
            'imagem_url' => $row['imagem_url']
        ];
    }
} catch (Exception $e) {
    $error_message = 'Erro ao carregar cardápio: ' . $e->getMessage();
    $categorias = [];
}

// Contador de itens no cardápio
$total_produtos = 0;
foreach ($categorias as $categoria) {
    $total_produtos += count($categoria['produtos']);
}

// Banner promocional (opcional)
$promocoes = [
    [
        'titulo' => 'Promoção de Terça',
        'descricao' => 'Hamburgueres com 15% de desconto',
        'imagem' => 'assets/images/promo1.jpg'
    ],
    [
        'titulo' => 'Happy Hour',
        'descricao' => 'De Segunda a Sexta das 17h às 19h',
        'imagem' => 'assets/images/promo2.jpg'
    ],
    [
        'titulo' => 'Combo Família',
        'descricao' => 'Peça para 4 pessoas e ganhe uma sobremesa',
        'imagem' => 'assets/images/promo3.jpg'
    ]
];
// Selecionar uma promoção aleatória
$promo_index = array_rand($promocoes);
$promocao = $promocoes[$promo_index];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio - Conheça nossos produtos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .category-heading {
            position: sticky;
            top: 70px;
            background-color: #f8f9fa;
            padding: 10px 15px;
            margin: 0 -15px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 10;
        }
        
        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 180px;
            object-fit: cover;
            border-top-left-radius: calc(0.25rem - 1px);
            border-top-right-radius: calc(0.25rem - 1px);
        }
        
        .banner {
            background-size: cover;
            background-position: center;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
            position: relative;
        }
        
        .banner-content {
            background-color: rgba(0,0,0,0.6);
            color: white;
            padding: 30px;
        }
        
        .category-nav {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 1020;
            padding: 10px 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .footer-cta {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .category-heading {
                top: 56px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-book"></i> GastroHub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="menu.php">Cardápio</a>
                    </li>
                    <!-- Adicione mais links de navegação conforme necessário -->
                </ul>
                <div>
                    <a href="login.php" class="btn btn-outline-primary me-2">Entrar</a>
                    <a href="cadastro.php" class="btn btn-primary">Cadastrar</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Banner Promocional -->
    <div class="container mt-4">
        <div class="banner" style="background-image: url('<?php echo $promocao['imagem']; ?>');">
            <div class="banner-content">
                <h2><?php echo htmlspecialchars($promocao['titulo']); ?></h2>
                <p class="lead"><?php echo htmlspecialchars($promocao['descricao']); ?></p>
                <a href="cadastro.php" class="btn btn-primary">Cadastre-se para pedir</a>
            </div>
        </div>
    </div>

    <!-- Navegação das Categorias -->
    <div class="category-nav">
        <div class="container">
            <div class="d-flex flex-nowrap overflow-auto">
                <?php foreach ($categorias as $id_categoria => $categoria): ?>
                    <a href="#categoria-<?php echo $id_categoria; ?>" class="btn btn-sm btn-outline-secondary me-2">
                        <?php echo htmlspecialchars($categoria['nome']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="container mt-4">
        <h1>Nosso Cardápio</h1>
        <p class="lead">Conheça nossos produtos e se cadastre para realizar seu pedido.</p>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($categorias)): ?>
            <div class="alert alert-info">
                Estamos atualizando nosso cardápio. Por favor, volte em breve!
            </div>
        <?php else: ?>
            <!-- Contador de produtos -->
            <div class="alert alert-light border">
                <i class="bi bi-info-circle"></i> Temos <?php echo $total_produtos; ?> produtos disponíveis em nosso cardápio!
            </div>
            
            <?php foreach ($categorias as $id_categoria => $categoria): ?>
                <div id="categoria-<?php echo $id_categoria; ?>" class="mb-5">
                    <h2 class="category-heading"><?php echo htmlspecialchars($categoria['nome']); ?></h2>
                    
                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <?php foreach ($categoria['produtos'] as $produto): ?>
                            <div class="col">
                                <div class="card product-card">
                                    <?php if (!empty($produto['imagem_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" 
                                             class="product-image" 
                                             alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($produto['nome']); ?></h5>
                                        <?php if (!empty($produto['descricao'])): ?>
                                            <p class="card-text"><?php echo htmlspecialchars($produto['descricao']); ?></p>
                                        <?php endif; ?>
                                        <p class="card-text fw-bold text-success fs-5">
                                            <?php echo formatCurrency($produto['preco']); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="login.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-cart-plus"></i> Entrar para pedir
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- CTA para cadastro -->
            <div class="footer-cta">
                <h3>Gostou do que viu?</h3>
                <p class="mb-4">Cadastre-se para fazer seu pedido e aproveitar todas as nossas opções!</p>
                <div class="d-grid gap-2 d-md-block">
                    <a href="login.php" class="btn btn-outline-primary me-md-2">Entrar</a>
                    <a href="cadastro.php" class="btn btn-primary">Cadastre-se agora</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Seção de Informações -->
    <div class="container mb-5">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock fs-1 text-primary mb-3"></i>
                        <h5 class="card-title">Horário de Funcionamento</h5>
                        <p class="card-text">
                            Segunda a Sexta: 11h - 22h<br>
                            Sábados e Domingos: 11h - 23h
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-geo-alt fs-1 text-primary mb-3"></i>
                        <h5 class="card-title">Nossa Localização</h5>
                        <p class="card-text">
                            Av. Principal, 1234<br>
                            Centro - Sua Cidade/UF
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-telephone fs-1 text-primary mb-3"></i>
                        <h5 class="card-title">Contato</h5>
                        <p class="card-text">
                            (00) 00000-0000<br>
                            contato@cardapiodigital.com
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>GastroHub</h5>
                    <p class="small">Sistema de gerenciamento de pedidos online para restaurantes e estabelecimentos de alimentação.</p>
                </div>
                <div class="col-md-3">
                    <h5>Links Úteis</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50">Sobre Nós</a></li>
                        <li><a href="#" class="text-white-50">Termos de Serviço</a></li>
                        <li><a href="#" class="text-white-50">Política de Privacidade</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contato</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-telephone"></i> (00) 00000-0000</li>
                        <li><i class="bi bi-envelope"></i> contato@cardapiodigital.com</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <p class="small mb-0">&copy; <?php echo date('Y'); ?> GastroHub. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small mb-0">Desenvolvido por <?php echo htmlspecialchars('Gabriel Gortan'); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
