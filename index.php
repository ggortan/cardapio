<?php
// landing-page.php - Página inicial pública com ênfase em conversão
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirecionar usuários já logados
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

// Buscar alguns produtos em destaque
try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT p.id_produto, p.nome, p.descricao, p.preco, p.imagem_url, c.nome AS categoria 
        FROM Produto p
        JOIN Categoria c ON p.id_categoria = c.id_categoria
        ORDER BY RAND() 
        LIMIT 6
    ");
    $stmt->execute();
    $produtos_destaque = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $produtos_destaque = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio Digital - Pedidos online fácil e rápido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background-color: #f8f9fa;
            padding: 80px 0;
            margin-bottom: 40px;
        }
        
        .hero-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .feature-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #198754; /* Bootstrap success color */
        }
        
        .cta-section {
            background-color: #198754;
            color: white;
            padding: 60px 0;
            margin: 40px 0;
        }
        
        .product-card {
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            height: 160px;
            object-fit: cover;
        }
        
        .testimonial-card {
            border-left: 4px solid #198754;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-book"></i> Cardápio Digital
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="menu.php">Cardápio</a>
                    </li>
                    <!-- Adicione mais links conforme necessário -->
                </ul>
                <div>
                    <a href="login.php" class="btn btn-outline-primary me-2">Entrar</a>
                    <a href="cadastro.php" class="btn btn-primary">Cadastrar</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-3">Peça o melhor da culinária sem sair de casa</h1>
                    <p class="lead mb-4">Navegue pelo nosso cardápio, faça seu pedido online e receba no conforto da sua casa.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <a href="menu.php" class="btn btn-primary btn-lg px-4 me-md-2">Ver Cardápio</a>
                        <a href="cadastro.php" class="btn btn-outline-primary btn-lg px-4">Cadastrar</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/hero-image.jpg" alt="Cardápio Digital" class="hero-image d-none d-lg-block">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container mb-5">
        <h2 class="text-center mb-5">Por que escolher nosso serviço?</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-clock feature-icon"></i>
                    <h4>Rápido e Prático</h4>
                    <p>Faça seu pedido em poucos cliques e receba sua comida rapidamente.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-shield-check feature-icon"></i>
                    <h4>Seguro</h4>
                    <p>Todos os seus dados são protegidos e suas transações são seguras.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-emoji-smile feature-icon"></i>
                    <h4>Satisfação Garantida</h4>
                    <p>Qualidade e sabor incomparáveis em cada pedido.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Highlight -->
    <?php if (!empty($produtos_destaque)): ?>
    <section class="container mb-5">
        <h2 class="text-center mb-4">Destaques do Cardápio</h2>
        <p class="text-center mb-5">Confira algumas das nossas opções mais populares</p>
        
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php foreach ($produtos_destaque as $produto): ?>
                <div class="col">
                    <div class="card h-100 product-card">
                        <?php if (!empty($produto['imagem_url'])): ?>
                            <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" 
                                 class="card-img-top product-image" 
                                 alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($produto['categoria']); ?></span>
                            <h5 class="card-title"><?php echo htmlspecialchars($produto['nome']); ?></h5>
                            <?php if (!empty($produto['descricao'])): ?>
                                <p class="card-text"><?php echo htmlspecialchars(substr($produto['descricao'], 0, 80)) . (strlen($produto['descricao']) > 80 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            <p class="card-text fw-bold text-success"><?php echo formatCurrency($produto['preco']); ?></p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="menu.php" class="btn btn-outline-primary w-100">Ver no Cardápio</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="menu.php" class="btn btn-primary">Ver Cardápio Completo</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="display-5 fw-bold mb-3">Pronto para fazer seu pedido?</h2>
            <p class="lead mb-4">Cadastre-se agora e aproveite todas as vantagens!</p>
            <a href="cadastro.php" class="btn btn-light btn-lg px-5">Cadastrar Agora</a>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="container mb-5">
        <h2 class="text-center mb-4">O que nossos clientes dizem</h2>
        
        <div class="row">
            <div class="col-md-4">
                <div class="testimonial-card">
                    <p class="fst-italic">"Serviço excelente e comida deliciosa! O pedido chegou antes do tempo previsto."</p>
                    <p class="fw-bold mb-0">Maria Silva</p>
                    <small class="text-muted">Cliente desde 2023</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <p class="fst-italic">"O cardápio digital é super fácil de usar. Faço meus pedidos em questão de minutos!"</p>
                    <p class="fw-bold mb-0">João Oliveira</p>
                    <small class="text-muted">Cliente desde 2022</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <p class="fst-italic">"Adoro poder acompanhar o status do meu pedido em tempo real. Muito prático!"</p>
                    <p class="fw-bold mb-0">Ana Santos</p>
                    <small class="text-muted">Cliente desde 2023</small>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="container mb-5">
        <h2 class="text-center mb-4">Como Funciona</h2>
        
        <div class="row">
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <h3 class="mb-0">1</h3>
                </div>
                <h5>Cadastre-se</h5>
                <p>Crie sua conta em poucos minutos</p>
            </div>
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <h3 class="mb-0">2</h3>
                </div>
                <h5>Escolha</h5>
                <p>Navegue pelo cardápio e escolha seus produtos</p>
            </div>
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <h3 class="mb-0">3</h3>
                </div>
                <h5>Peça</h5>
                <p>Confirme seu pedido e escolha a forma de pagamento</p>
            </div>
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <h3 class="mb-0">4</h3>
                </div>
                <h5>Receba</h5>
                <p>Seu pedido será entregue ou ficará disponível para retirada</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Cardápio Digital</h5>
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
                    <p class="small mb-0">&copy; <?php echo date('Y'); ?> Cardápio Digital. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small mb-0">Desenvolvido por <?php echo htmlspecialchars('Seu Nome'); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>