<?php
// includes/footer.php
?>
    </main>

    <footer class="bg-dark text-white py-4 mt-auto">
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
                        <li><i class="bi bi-envelope"></i> contato@email.com</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <p class="small mb-0">&copy; <?php echo date('Y'); ?> GastroHub. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small mb-0">Desenvolvido por <?php echo htmlspecialchars('Seu Nome'); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ativar todas as tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Auto-fecha as mensagens flash após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            var alertList = document.querySelectorAll('.alert');
            alertList.forEach(function(alert) {
                // Excluir alertas que precisam de interação do usuário (como confirmações)
                if (!alert.classList.contains('alert-confirm')) {
                    setTimeout(function() {
                        var bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>
