/**
 * Carrinho Dinâmico para Cardápio Digital
 * Permite adicionar produtos ao carrinho sem recarregar a página
 */

// Objeto para gerenciar o carrinho
const DynamicCart = {
    // Armazena os itens do carrinho
    items: [],
    
    // Contador de itens total
    count: 0,
    
    // Inicializa o carrinho
    init: function() {
        // Carregar carrinho da sessão, se existir
        this.loadCart();
        
        // Atualizar contador visual
        this.updateCartIcon();
        
        // Associar evento aos botões de adicionar
        this.setupAddToCartButtons();
        
        // Configurar o modal do carrinho
        this.setupCartModal();
        
        console.log('DynamicCart initialized');
    },
    
    // Carrega dados do carrinho se existirem
    loadCart: function() {
        // Verifica se há dados do carrinho no localStorage
        const savedCart = localStorage.getItem('dynamicCart');
        if (savedCart) {
            try {
                const cartData = JSON.parse(savedCart);
                this.items = cartData.items || [];
                
                // Recalcular o contador
                this.count = this.items.reduce((total, item) => total + item.quantidade, 0);
            } catch (e) {
                console.error('Error loading cart data', e);
                this.items = [];
                this.count = 0;
            }
        }
    },
    
    // Salva o carrinho atual no localStorage
    saveCart: function() {
        try {
            localStorage.setItem('dynamicCart', JSON.stringify({
                items: this.items,
                updated: new Date().toISOString()
            }));
        } catch (e) {
            console.error('Error saving cart data', e);
        }
    },
    
    // Atualiza o ícone do carrinho com o número correto de itens
    updateCartIcon: function() {
        const cartBadge = document.getElementById('cart-badge');
        if (cartBadge) {
            if (this.count > 0) {
                cartBadge.textContent = this.count;
                cartBadge.classList.remove('d-none');
            } else {
                cartBadge.classList.add('d-none');
            }
        }
        
        // Atualizar também o badge no modal
        const modalBadge = document.getElementById('cart-modal-badge');
        if (modalBadge) {
            modalBadge.textContent = this.count;
        }
        
        // Atualizar total de itens no modal
        const itemCountElement = document.getElementById('cart-item-count');
        if (itemCountElement) {
            itemCountElement.textContent = this.count;
        }
    },
    
    // Configurar os botões de adicionar ao carrinho
    setupAddToCartButtons: function() {
        const addButtons = document.querySelectorAll('.add-to-cart-btn');
        
        addButtons.forEach(button => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                
                // Obter dados do produto
                const productId = button.getAttribute('data-product-id');
                const productName = button.getAttribute('data-product-name');
                const productPrice = parseFloat(button.getAttribute('data-product-price'));
                const productImage = button.getAttribute('data-product-image') || '';
                
                // Obter quantidade do input correspondente
                const quantityInput = button.closest('.product-card').querySelector('.quantity-input');
                const quantity = parseInt(quantityInput ? quantityInput.value : 1);
                
                if (isNaN(quantity) || quantity < 1) {
                    alert('Por favor, informe uma quantidade válida.');
                    return;
                }
                
                // Adicionar ao carrinho
                this.addItem(productId, productName, productPrice, quantity, productImage);
                
                // Mostrar feedback
                this.showAddedToCartMessage(productName);
                
                // Resetar input de quantidade para 1
                if (quantityInput) {
                    quantityInput.value = 1;
                }
            });
        });
    },
    
    // Adiciona um item ao carrinho
    addItem: function(id, name, price, quantity, image) {
        // Verificar se o produto já está no carrinho
        const existingItemIndex = this.items.findIndex(item => item.id_produto === id);
        
        if (existingItemIndex !== -1) {
            // Atualizar quantidade
            this.items[existingItemIndex].quantidade += quantity;
        } else {
            // Adicionar novo item
            this.items.push({
                id_produto: id,
                nome: name,
                preco: price,
                quantidade: quantity,
                imagem_url: image
            });
        }
        
        // Atualizar contador
        this.count += quantity;
        
        // Atualizar interface
        this.updateCartIcon();
        
        // Atualizar lista de itens no modal
        this.updateCartItemsList();
        
        // Salvar carrinho
        this.saveCart();
        
        console.log('Item added to cart', id, name, price, quantity);
    },
    
    // Remover um item do carrinho
    removeItem: function(id) {
        const index = this.items.findIndex(item => item.id_produto === id);
        
        if (index !== -1) {
            // Atualizar contador
            this.count -= this.items[index].quantidade;
            
            // Remover item
            this.items.splice(index, 1);
            
            // Atualizar interface
            this.updateCartIcon();
            this.updateCartItemsList();
            
            // Salvar carrinho
            this.saveCart();
        }
    },
    
    // Atualiza a quantidade de um item
    updateItemQuantity: function(id, newQuantity) {
        const item = this.items.find(item => item.id_produto === id);
        
        if (item) {
            // Calcular diferença para atualizar contador
            const diff = newQuantity - item.quantidade;
            
            // Atualizar quantidade
            item.quantidade = newQuantity;
            this.count += diff;
            
            // Atualizar interface
            this.updateCartIcon();
            this.updateCartItemsList();
            
            // Salvar carrinho
            this.saveCart();
        }
    },
    
    // Calcular total do carrinho
    calculateTotal: function() {
        return this.items.reduce((total, item) => total + (item.preco * item.quantidade), 0);
    },
    
    // Configurar o modal do carrinho
    setupCartModal: function() {
        // Inicializar lista de itens no modal
        this.updateCartItemsList();
        
        // Configurar botão de limpar carrinho
        const clearCartBtn = document.getElementById('clear-cart-btn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', () => {
                if (confirm('Tem certeza que deseja limpar o carrinho?')) {
                    this.clearCart();
                }
            });
        }
        
        // Configurar evento para fechar modal com tecla Escape
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const modal = document.getElementById('cartModal');
                if (modal && modal.classList.contains('show')) {
                    bootstrap.Modal.getInstance(modal).hide();
                }
            }
        });
    },
    
    // Atualiza a lista de itens no modal
    updateCartItemsList: function() {
        const cartList = document.getElementById('cart-items-list');
        const cartTotal = document.getElementById('cart-total');
        const emptyCartMessage = document.getElementById('empty-cart-message');
        const cartActionButtons = document.getElementById('cart-action-buttons');
        
        if (!cartList) return;
        
        // Limpar lista atual
        cartList.innerHTML = '';
        
        if (this.items.length === 0) {
            // Mostrar mensagem de carrinho vazio
            if (emptyCartMessage) emptyCartMessage.classList.remove('d-none');
            if (cartActionButtons) cartActionButtons.classList.add('d-none');
            if (cartTotal) cartTotal.textContent = formatCurrency(0);
            return;
        }
        
        // Esconder mensagem de carrinho vazio e mostrar botões
        if (emptyCartMessage) emptyCartMessage.classList.add('d-none');
        if (cartActionButtons) cartActionButtons.classList.remove('d-none');
        
        // Adicionar cada item à lista
        this.items.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'cart-item d-flex align-items-center justify-content-between border-bottom py-2';
            
            const itemContent = `
                <div class="d-flex align-items-center">
                    <div class="me-2">
                        <button class="btn btn-sm btn-outline-danger remove-cart-item" data-id="${item.id_produto}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div>
                        <h6 class="mb-0">${item.nome}</h6>
                        <div class="d-flex align-items-center">
                            <small class="text-muted me-2">${formatCurrency(item.preco)} × </small>
                            <div class="input-group input-group-sm" style="width: 80px;">
                                <button class="btn btn-outline-secondary decrement-qty" type="button" data-id="${item.id_produto}">-</button>
                                <input type="number" class="form-control text-center cart-qty-input" value="${item.quantidade}" min="1" max="10" data-id="${item.id_produto}">
                                <button class="btn btn-outline-secondary increment-qty" type="button" data-id="${item.id_produto}">+</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="fw-bold">${formatCurrency(item.preco * item.quantidade)}</span>
                </div>
            `;
            
            itemElement.innerHTML = itemContent;
            cartList.appendChild(itemElement);
        });
        
        // Atualizar total
        if (cartTotal) {
            cartTotal.textContent = formatCurrency(this.calculateTotal());
        }
        
        // Configurar eventos para os botões de remover
        const removeButtons = cartList.querySelectorAll('.remove-cart-item');
        removeButtons.forEach(button => {
            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-id');
                this.removeItem(productId);
            });
        });
        
        // Configurar eventos para os inputs de quantidade
        const qtyInputs = cartList.querySelectorAll('.cart-qty-input');
        qtyInputs.forEach(input => {
            input.addEventListener('change', () => {
                const productId = input.getAttribute('data-id');
                let newQuantity = parseInt(input.value);
                
                // Validar quantidade
                if (isNaN(newQuantity) || newQuantity < 1) {
                    newQuantity = 1;
                    input.value = 1;
                } else if (newQuantity > 10) {
                    newQuantity = 10;
                    input.value = 10;
                }
                
                this.updateItemQuantity(productId, newQuantity);
            });
        });
        
        // Configurar eventos para os botões de incremento/decremento
        const decrementButtons = cartList.querySelectorAll('.decrement-qty');
        decrementButtons.forEach(button => {
            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-id');
                const input = button.parentElement.querySelector('.cart-qty-input');
                let newQuantity = parseInt(input.value) - 1;
                
                if (newQuantity < 1) newQuantity = 1;
                
                input.value = newQuantity;
                this.updateItemQuantity(productId, newQuantity);
            });
        });
        
        const incrementButtons = cartList.querySelectorAll('.increment-qty');
        incrementButtons.forEach(button => {
            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-id');
                const input = button.parentElement.querySelector('.cart-qty-input');
                let newQuantity = parseInt(input.value) + 1;
                
                if (newQuantity > 10) newQuantity = 10;
                
                input.value = newQuantity;
                this.updateItemQuantity(productId, newQuantity);
            });
        });
    },
    
    // Limpar o carrinho por completo
    clearCart: function() {
        this.items = [];
        this.count = 0;
        
        // Atualizar interface
        this.updateCartIcon();
        this.updateCartItemsList();
        
        // Salvar carrinho
        this.saveCart();
        
        console.log('Cart cleared');
    },
    
    // Exibir mensagem de produto adicionado
    showAddedToCartMessage: function(productName) {
        // Criar toast dinâmico
        const toastContainer = document.querySelector('.toast-container');
        
        if (!toastContainer) {
            // Criar container se não existir
            const newContainer = document.createElement('div');
            newContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(newContainer);
        }
        
        const container = document.querySelector('.toast-container');
        
        const toastId = 'toast-' + new Date().getTime();
        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center text-white bg-success';
        toastEl.id = toastId;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>${productName}</strong> foi adicionado ao carrinho!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>
        `;
        
        container.appendChild(toastEl);
        
        // Inicializar e mostrar toast
        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 3000
        });
        
        toast.show();
        
        // Remover da DOM após esconder
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    },
    
    // Sincronizar o carrinho com o servidor
    syncCart: function() {
        if (this.items.length === 0) {
            alert('Seu carrinho está vazio.');
            return;
        }
        
        // Criar formulário para envio
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'sincronizar-carrinho.php';
        form.style.display = 'none';
        
        // Adicionar token CSRF se necessário
        if (typeof csrfToken !== 'undefined') {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }
        
        // Adicionar itens do carrinho como JSON
        const itemsInput = document.createElement('input');
        itemsInput.type = 'hidden';
        itemsInput.name = 'cart_items';
        itemsInput.value = JSON.stringify(this.items);
        form.appendChild(itemsInput);
        
        // Adicionar formulário à página e enviar
        document.body.appendChild(form);
        form.submit();
    }
};

// Formatar valores monetários
function formatCurrency(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',');
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    DynamicCart.init();
});
/**
 * Função para limpar completamente o carrinho após finalização do pedido
 * Esta deve ser chamada após a conclusão bem-sucedida de um pedido
 */
function resetCartAfterOrder() {
    // Verificar se o objeto DynamicCart existe
    if (typeof DynamicCart !== 'undefined') {
        // Limpar o carrinho
        DynamicCart.clearCart();
    }
    
    // Limpar diretamente do localStorage como backup
    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('dynamicCart');
    }
    
    console.log('Carrinho limpo com sucesso após finalização do pedido!');
}

// Adicionar evento para detectar sucesso na finalização do pedido
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há uma mensagem de sucesso de pedido na página
    const flashMessages = document.querySelectorAll('.alert-success');
    let orderCompleted = false;
    
    // Verificar se alguma mensagem indica finalização de pedido
    flashMessages.forEach(message => {
        if (message.textContent.includes('Pedido realizado com sucesso')) {
            orderCompleted = true;
        }
    });
    
    // Se o pedido foi concluído, limpar o carrinho
    if (orderCompleted) {
        resetCartAfterOrder();
    }
});