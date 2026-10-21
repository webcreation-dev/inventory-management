/**
 * POS Products Display Manager
 * Handles product display, search, and category filtering from IndexedDB
 */

class POSProductsDisplay {
    constructor() {
        this.currentCategoryId = null;
        this.currentSearchTerm = '';
        this.allProducts = [];
        this.displayedProducts = [];
        this.productsPerPage = 500;
        this.isLoading = false;
        this.baseUrl = $('#base_url').val() || window.location.origin;
        this.companySessionData = this.getCompanySessionData();
        this.isMedicineMode = this.checkMedicineMode();
        this.isListLayoutMode = this.checkListLayoutMode(); // Medicine or Grocery: list view + keyboard nav
        this.currentGenericName = null; // Track current generic name for alternatives
        this.originalCategoryListHTML = null; // Store original category list HTML
        this.activeItemIndex = -1; // Track currently active item index for keyboard navigation
        this.isKeyboardNavigationActive = false; // Track if keyboard navigation is active
    }

    /**
     * Get company session data (same source as sales.js: #company_data)
     */
    getCompanySessionData() {
        let company_session_data = {};
        try {
            const company_data = $('#company_data').val();
            if (company_data) {
                company_session_data = JSON.parse(company_data);
            }
        } catch (e) {
            console.error('Error parsing company info:', e);
        }
        return company_session_data;
    }

    /**
     * Check if medicine mode is enabled (Alternative Medicine categories)
     */
    checkMedicineMode() {
        try {
            return this.companySessionData.grocery_experience === 'Medicine';
        } catch (e) {
            return false;
        }
    }

    /**
     * Check if list layout mode is enabled (Medicine or Grocery: list view + keyboard nav)
     * Grocery uses list layout like Medicine but keeps regular categories
     */
    checkListLayoutMode() {
        try {
            const exp = this.companySessionData.grocery_experience;
            return exp === 'Medicine' || exp === 'Grocery';
        } catch (e) {
            return false;
        }
    }

    /**
     * Initialize products display
     */
    async init() {
        // Wait for IndexedDB to be ready
        if (!posIndexedDB.isInitialized) {
            await posIndexedDB.init();
        }

        // Store original category list HTML
        this.originalCategoryListHTML = $('#pos-category-list').html();

        // If medicine mode, initialize medicine-specific UI
        if (this.isMedicineMode) {
            this.initializeMedicineMode();
        }

        // Setup event listeners first
        this.setupEventListeners();
        
        // Setup keyboard navigation for list layout mode (Medicine + Grocery)
        if (this.isListLayoutMode) {
            this.setupKeyboardNavigation();
        }

        // Wait a bit for IndexedDB sync to complete (if in progress)
        // Check if sync is in progress and wait for it
        if (posIndexedDB.syncInProgress) {
            // Wait for sync to complete (check every 500ms)
            await this.waitForSyncComplete();
        }

        // Load first chunk of products
        await this.loadFirstChunk();
    }

    /**
     * Initialize medicine mode UI
     */
    initializeMedicineMode() {
        // Update category header - hide icon and change text
        const $categoryHeader = $('.pos-category-header');
        $categoryHeader.html('Alternative Medicine').data('category-id', 'all');
        
        // Clear category list and show note
        const $categoryList = $('#pos-category-list');
        $categoryList.html('<div class="pos-category-item text-muted" style="text-align: center; padding: 20px; font-style: italic;">Alternative Medicine will be displayed here</div>');
    }

    /**
     * Wait for IndexedDB sync to complete or at least first batch
     */
    async waitForSyncComplete(maxWait = 30000) {
        const startTime = Date.now();
        while (posIndexedDB.syncInProgress && (Date.now() - startTime) < maxWait) {
            // Check if at least some products are available
            try {
                const count = await posIndexedDB.getProductCount();
                if (count > 0) {
                    // At least one batch is synced, we can proceed
                    break;
                }
            } catch (e) {
                // Ignore errors during check
            }
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }

    /**
     * Load first chunk of products (500 items) from IndexedDB
     */
    async loadFirstChunk() {
        try {
            this.showLoading();
            
            // Check if IndexedDB has products
            const productCount = await posIndexedDB.getProductCount();
            
            if (productCount > 0) {
                // Get all products and take first chunk
                const allProducts = await posIndexedDB.getAllProducts();
                // Get first 500 products (or all if less than 500)
                this.displayedProducts = allProducts.slice(0, this.productsPerPage);
                this.renderProducts(this.displayedProducts);
            } else {
                // If IndexedDB is empty, fetch first batch from server
                console.log('IndexedDB is empty, fetching first batch from server...');
                await this.fetchFirstBatchFromServer();
            }
        } catch (error) {
            console.error('Error loading first chunk:', error);
            this.showError('Failed to load products. Please try syncing products.');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Fetch first batch from server if IndexedDB is empty
     */
    async fetchFirstBatchFromServer() {
        try {
            const response = await posIndexedDB.fetchProductsBatch(1);
            if (response.status === 'success' && response.data && response.data.length > 0) {
                // Store in IndexedDB
                await posIndexedDB.storeProducts(response.data);
                // Display first chunk
                this.displayedProducts = response.data.slice(0, this.productsPerPage);
                this.renderProducts(this.displayedProducts);
            } else {
                this.showEmptyState('No products available');
            }
        } catch (error) {
            console.error('Error fetching first batch:', error);
            this.showError('Failed to fetch products from server');
        }
    }

    /**
     * Setup event listeners for search and category
     */
    setupEventListeners() {
        // Search input handler with debounce
        let searchTimeout;
        $('#pos-product-search').on('input', (e) => {
            clearTimeout(searchTimeout);
            const searchTerm = $(e.target).val().trim();
            
            searchTimeout = setTimeout(() => {
                this.handleSearch(searchTerm);
            }, 300); // 300ms debounce
        });

        // Category click handler
        $(document).on('click', '.pos-category-item, .pos-category-header', (e) => {
            e.preventDefault();
            let groceryExperience = $('#pos-layout-select').val();
            if(groceryExperience != 'Medicine'){
                const categoryId = $(e.currentTarget).data('category-id');
                this.handleCategoryClick(categoryId, $(e.currentTarget));
            }
        });

        // Product card click handler
        $(document).on('click', '.item-card, .item-card-list', (e) => {
            e.preventDefault();
            const $clickedItem = $(e.currentTarget);
            const productId = $clickedItem.data('product-id');
            const isActive = $clickedItem.hasClass('active');
            
            // If item is active (from keyboard navigation), open modal directly
            if (isActive && this.isMedicineMode) {
                // Set flag to indicate this is from active item click - bypass alternative check
                this.isKeyboardNavigationActive = true;
            } else {
                // Reset keyboard navigation when clicking non-active item
                this.activeItemIndex = -1;
                this.isKeyboardNavigationActive = false;
            }
            
            // Remove active class from all items
            $('.item-card, .item-card-list').removeClass('active');
            
            // Handle the click
            this.handleProductClick(productId);
        });

        // Item edit icon click handler
        $(document).on('click', '.item-edit-icon', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const productId = $(e.currentTarget).data('product-id');
            this.handleProductClick(productId, { forceModal: true });
        });
    }

    /**
     * Setup keyboard navigation for medicine mode
     */
    setupKeyboardNavigation() {
        const self = this;
        
        // Prevent default arrow key behavior when in list layout mode (Medicine + Grocery)
        $(document).on('keydown', function(e) {
            // Only handle if list layout mode is active and not typing in input fields
            if (!self.isListLayoutMode) return;
            
            const $target = $(e.target);
            const isInputField = $target.is('input, textarea, select') || $target.closest('input, textarea, select').length > 0;
            
            // If typing in input field, don't handle navigation
            if (isInputField) return;
            
            // Handle arrow keys
            if (e.key === 'ArrowDown' || e.keyCode === 40) {
                e.preventDefault();
                self.navigateItems('down');
            } else if (e.key === 'ArrowUp' || e.keyCode === 38) {
                e.preventDefault();
                self.navigateItems('up');
            } else if (e.key === 'Enter' || e.keyCode === 13) {
                // Only handle Enter if an item is active
                if (self.activeItemIndex >= 0 && !isInputField) {
                    e.preventDefault();
                    self.openActiveItemModal();
                }
            }
        });
    }

    /**
     * Navigate items with arrow keys
     */
    async navigateItems(direction) {
        // Get items based on current view
        let $items;
        if (this.isListLayoutMode) {
            // In list layout mode (Medicine/Grocery), check if we're in list view or grid view
            const $listItems = $('.pos-products-list .item-card-list');
            const $gridItems = $('.pos-products-grid .item-card');
            $items = $listItems.length > 0 ? $listItems : $gridItems;
        } else {
            $items = $('.pos-products-grid .item-card');
        }
        
        if ($items.length === 0) return;
        
        // Reset active index if out of bounds
        if (this.activeItemIndex >= $items.length) {
            this.activeItemIndex = $items.length - 1;
        }
        
        // Calculate new index
        if (direction === 'down') {
            // First press: select first item (index 0)
            if (this.activeItemIndex < 0) {
                this.activeItemIndex = 0;
            } else {
                this.activeItemIndex = (this.activeItemIndex + 1) % $items.length;
            }
        } else if (direction === 'up') {
            // If at first item, wrap to last
            if (this.activeItemIndex <= 0) {
                this.activeItemIndex = $items.length - 1;
            } else {
                this.activeItemIndex = this.activeItemIndex - 1;
            }
        }
        
        // Update active class
        $('.item-card, .item-card-list').removeClass('active');
        const $activeItem = $items.eq(this.activeItemIndex);
        $activeItem.addClass('active');
        
        // Scroll to active item
        this.scrollToActiveItem($activeItem);
        
        // If medicine mode only: show Alternative Medicine when navigating to Medicine_Product
        if (this.isMedicineMode) {
            const productId = $activeItem.data('product-id');
            if (productId) {
                await this.handleActiveItemChange(productId);
            }
        }
        
        this.isKeyboardNavigationActive = true;
    }

    /**
     * Scroll to active item
     */
    scrollToActiveItem($item) {
        if ($item.length === 0) return;
        
        // Find the container
        const $container = $item.closest('.pos-products-list, .pos-products-grid');
        if ($container.length === 0) return;
        
        const containerTop = $container.offset().top;
        const containerBottom = containerTop + $container.height();
        const itemTop = $item.offset().top;
        const itemBottom = itemTop + $item.outerHeight();
        
        // Scroll if item is outside visible area
        if (itemTop < containerTop) {
            $container.scrollTop($container.scrollTop() + (itemTop - containerTop) - 20);
        } else if (itemBottom > containerBottom) {
            $container.scrollTop($container.scrollTop() + (itemBottom - containerBottom) + 20);
        }
    }

    /**
     * Handle active item change - show alternatives if Medicine_Product
     */
    async handleActiveItemChange(productId) {
        try {
            // Ensure product is in IndexedDB
            await posIndexedDB.ensureProductInIndexedDB(productId);
            const product = await posIndexedDB.getProductById(productId);
            
            if (!product) return;
            
            // Check if product type is Medicine_Product and has generic_name
            if (product.type === 'Medicine_Product' && product.generic_name) {
                await this.showMedicineAlternatives(product.generic_name);
            } else {
                // If not Medicine_Product or no generic_name, clear alternatives
                this.currentGenericName = null;
                $('.pos-category-header').html('Alternative Medicine').data('category-id', 'all');
                $('#pos-category-list').html('<div class="pos-category-item text-muted" style="text-align: center; padding: 20px; font-style: italic;">Alternative Medicine will be displayed here</div>');
            }
        } catch (error) {
            console.error('Error handling active item change:', error);
        }
    }

    /**
     * Open modal for active item
     */
    async openActiveItemModal() {
        // Get items based on current view
        let $items;
        if (this.isListLayoutMode) {
            const $listItems = $('.pos-products-list .item-card-list');
            const $gridItems = $('.pos-products-grid .item-card');
            $items = $listItems.length > 0 ? $listItems : $gridItems;
        } else {
            $items = $('.pos-products-grid .item-card');
        }
        
        if (this.activeItemIndex < 0 || this.activeItemIndex >= $items.length) return;
        
        const $activeItem = $items.eq(this.activeItemIndex);
        const productId = $activeItem.data('product-id');
        
        if (productId) {
            // Set flag to indicate this is from keyboard navigation
            this.isKeyboardNavigationActive = true;
            
            try {
                // Ensure product is in IndexedDB
                await posIndexedDB.ensureProductInIndexedDB(productId);
                const product = await posIndexedDB.getProductById(productId);
                
                if (!product) {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification('Product not found. Please try again.');
                    }
                    return;
                }
                
                product.stock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;

                // Get company data from session
                const companyData = JSON.parse($('#company_data').val() || '{}');
                const directCart = companyData.direct_cart || 'No';
                const productType = product.type || '';

                // Variation_Product always requires modal to select variation
                const isDirectCartEligible = (productType === 'General_Product' || productType === 'Installment_Product');
                
                // If direct cart is enabled and product is eligible, add directly to cart
                if (isDirectCartEligible && directCart === 'Yes') {
                    this.addProductToCartDirectly(product);
                } else {
                    // Open modal for all other cases (including Variation_Product)
                    this.openProductInfoModal(product);
                }
            } catch (error) {
                console.error('Error opening active item modal:', error);
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Failed to load product information');
                }
            } finally {
                // Reset flag after opening modal
                this.isKeyboardNavigationActive = false;
            }
        }
    }

    /**
     * Handle search input
     */
    async handleSearch(searchTerm) {
        this.currentSearchTerm = searchTerm;
        
        // Reset keyboard navigation when searching
        this.activeItemIndex = -1;
        this.isKeyboardNavigationActive = false;
        $('.item-card, .item-card-list').removeClass('active');
        
        try {
            this.showLoading();
            
            // If search is empty
            if (!searchTerm) {
                // If category is selected, show products for that category
                if (this.currentCategoryId) {
                    const products = await posIndexedDB.getProductsByCategory(this.currentCategoryId);
                    this.displayedProducts = products;
                    this.renderProducts(this.displayedProducts);
                } else {
                    // No search and no category - show first chunk
                    await this.loadFirstChunk();
                }
                return;
            }

            const imeiHandled = await this.tryAddToCartFromImeiSerialKey(searchTerm);
            if (imeiHandled) {
                $('#pos-product-search').val('');
                this.currentSearchTerm = '';
                this.activeItemIndex = -1;
                this.isKeyboardNavigationActive = false;
                $('.item-card, .item-card-list').removeClass('active');
                if (this.currentCategoryId) {
                    const products = await posIndexedDB.getProductsByCategory(this.currentCategoryId);
                    this.displayedProducts = products;
                    this.renderProducts(this.displayedProducts);
                } else {
                    await this.loadFirstChunk();
                }
                return;
            }

            // Search products from IndexedDB
            let products = await posIndexedDB.searchProducts(searchTerm);
            
            // If category is also selected, filter by category
            // NOTE: compare as String to avoid type-mismatch (number vs string) coming from different server PHP/MySQL setups
            if (this.currentCategoryId) {
                const currentCatIdStr = String(this.currentCategoryId);
                products = products.filter(p => p.category_id != null && String(p.category_id) === currentCatIdStr);
            }

            this.displayedProducts = products;
            this.renderProducts(this.displayedProducts);
        } catch (error) {
            console.error('Error searching products:', error);
            this.showError('Failed to search products');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Handle category click
     */
    async handleCategoryClick(categoryId, $categoryElement) {
        // Reset keyboard navigation when category changes
        this.activeItemIndex = -1;
        this.isKeyboardNavigationActive = false;
        $('.item-card, .item-card-list').removeClass('active');
        
        // Update active state
        $('.pos-category-item, .pos-category-header').removeClass('active');
        $categoryElement.addClass('active');

        // Clear search input when category changes (optional - you can remove this if you want to keep search)
        // $('#pos-product-search').val('');
        // this.currentSearchTerm = '';

        try {
            this.showLoading();
            
            // If medicine mode and clicking on alternative category header, reset to show all medicines
            if (this.isMedicineMode && (categoryId === 'alternative' || categoryId === 'all')) {
                this.currentGenericName = null;
                // Reset category header (no icon in medicine mode)
                $('.pos-category-header').html('Alternative Medicine').data('category-id', 'all');
                // Reset category list to show note
                $('#pos-category-list').html('<div class="pos-category-item text-muted" style="text-align: center; padding: 20px; font-style: italic;">Alternative Medicine will be displayed here</div>');
                // Reload products
                await this.loadFirstChunk();
                return;
            }
            
            // In medicine mode, regular category clicks should not work (categories are hidden)
            if (this.isMedicineMode) {
                this.hideLoading();
                return;
            }
            
            if (categoryId === 'all' || !categoryId) {
                // Show all products (first chunk)
                this.currentCategoryId = null;
                // If there's a search term, keep it and show all products matching search
                if (this.currentSearchTerm) {
                    const products = await posIndexedDB.searchProducts(this.currentSearchTerm);
                    this.displayedProducts = products;
                    this.renderProducts(this.displayedProducts);
                } else {
                    await this.loadFirstChunk();
                }
            } else {
                // Set current category
                this.currentCategoryId = categoryId;
                
                let products = [];
                
                // Get products by category from IndexedDB
                products = await posIndexedDB.getProductsByCategory(categoryId);
                
                // If search term exists, filter search results by category
                if (this.currentSearchTerm) {
                    const searchResults = await posIndexedDB.searchProducts(this.currentSearchTerm);
                    // Compare as String to avoid type-mismatch (number vs string) from different server setups
                    const catIdStr = String(categoryId);
                    products = searchResults.filter(p => p.category_id != null && String(p.category_id) === catIdStr);
                }

                this.displayedProducts = products;
                this.renderProducts(this.displayedProducts);
            }
        } catch (error) {
            console.error('Error loading products by category:', error);
            this.showError('Failed to load products for this category');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Render products in the grid
     */
    renderProducts(products) {
        const $grid = $('#pos-products-grid');
        
        if (!products || products.length === 0) {
            this.showEmptyState('No products found');
            return;
        }

        // Clear existing products
        $grid.empty();

        // If list layout mode (Medicine + Grocery), change grid to list layout
        if (this.isListLayoutMode) {
            $grid.removeClass('pos-products-grid').addClass('pos-products-list');
        } else {
            $grid.removeClass('pos-products-list').addClass('pos-products-grid');
        }

        // Render each product
        products.forEach((product) => {
            const productCard = this.createProductCard(product);
            $grid.append(productCard);
        });
        
        if (this.isListLayoutMode) {
            $grid.css('display', 'block');
        } else {
            $grid.css('display', 'grid');
        }
        
        // Reset active item index when products are re-rendered
        this.activeItemIndex = -1;
        this.isKeyboardNavigationActive = false;
    }

    /**
     * Create product card HTML
     */
    createProductCard(product) {
        // Format price
        const price = this.formatPrice(product.sale_price || 0);
        
        // Get stock information (if available, otherwise show N/A)
        // Store stock as number for validation, handle null/undefined
        const stock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;
        const stockUnit = product.sale_unit_name || 'PCS';
        const stockDisplay = stock > 0 ? stock : 'N/A';

        // If list layout mode (Medicine + Grocery), show list view (no images)
        if (this.isListLayoutMode) {
            return $(`
                <div class="item-card item-card-list" data-product-id="${product.id}" data-product-stock="${stock}" data-product-type="${product.type || ''}" data-generic-name="${this.escapeHtml(product.generic_name || '')}">
                    <div class="item-card-content-list">
                        <div class="item-card-info">
                            <h5 class="item-card-title" title="${this.escapeHtml(product.name)}">
                                ${this.escapeHtml(product.name)}
                            </h5>
                            ${product.generic_name ? `<p class="item-card-generic-name text-muted">${this.escapeHtml(product.generic_name)}</p>` : ''}
                        </div>
                        <div class="item-card-details">
                            <div class="item-card-price">
                                <span class="price-label">Price:</span> 
                                <span class="price-value">${price}</span>
                            </div>
                            <div class="item-card-stock">
                                <span class="stock-label">Stock:</span> 
                                <span class="stock-value">${stockDisplay}</span>
                                ${stock > 0 ? `<span class="stock-unit">-${stockUnit}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        // Regular card view with image
        const imageUrl = product.image_url;
        return $(`
            <div class="item-card" data-product-id="${product.id}" data-product-stock="${stock}" data-product-type="${product.type || ''}">
                <div class="item-card-image">
                    <img src="${imageUrl}" alt="${this.escapeHtml(product.name)}" 
                         onerror="this.src='${this.baseUrl}/public/uploads/dummy_images/default-picture-pos.png'">
                </div>
                <div class="item-card-content">
                    <h5 class="item-card-title" title="${this.escapeHtml(product.name)}">
                        ${this.escapeHtml(product.name)}
                    </h5>
                    <h6>
                        <span class="price-label">Price:</span> 
                        <span class="price-value">${price}</span>
                    </h6>
                    <h6>
                        <span class="stock-label">Stock:</span> 
                        <span class="stock-value">${stockDisplay}</span>
                        ${stock > 0 ? `<span class="stock-unit">-${stockUnit}</span>` : ''}
                    </h6>
                </div>
            </div>
        `);
    }

    /**
     * Format price with currency
     */
    formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, (m) => map[m]);
    }

    /**
     * Show loading state
     */
    showLoading() {
        const $grid = $('#pos-products-grid');
        $grid.html(`
            <div class="pos-loading-state">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading products...</p>
            </div>
        `);
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        // Loading state is replaced by products, so no need to hide separately
    }

    /**
     * Show empty state
     */
    showEmptyState(message) {
        const $grid = $('#pos-products-grid');
        $grid.html(`
            <div class="pos-empty-state">
                <i class="ti tabler-package"></i>
                <h5 class="mb-2">${message}</h5>
            </div>
        `);
        $grid.css({
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'center',
            'height': '100%'
        });
    }

    /**
     * Show error state
     */
    showError(message) {
        const $grid = $('#pos-products-grid');
        $grid.html(`
            <div class="pos-error-state">
                <i class="ti tabler-alert-circle"></i>
                <h5 class="mb-2 text-danger">Error</h5>
                <p class="text-muted">${message}</p>
            </div>
        `);
    }

    /**
     * Update product card stock display after stock change
     * @param {number} productId - Product ID
     * @param {number} newStock - New stock value
     */
    async updateProductCardStock(productId, newStock) {
        try {
            // Get product from IndexedDB to get unit name
            const product = await posIndexedDB.getProductById(productId);
            const stockUnit = product ? (product.sale_unit_name || 'PCS') : 'PCS';
            const stockDisplay = newStock > 0 ? newStock : 'N/A';
            
            // Update product card
            const $productCard = $(`.item-card[data-product-id="${productId}"]`);
            if ($productCard.length) {
                // Update data attribute
                $productCard.attr('data-product-stock', newStock);
                
                // Update stock display
                const $stockValue = $productCard.find('.stock-value');
                const $stockUnit = $productCard.find('.stock-unit');
                
                if ($stockValue.length) {
                    $stockValue.text(stockDisplay);
                }
                
                if (newStock > 0) {
                    if ($stockUnit.length) {
                        $stockUnit.text(`-${stockUnit}`);
                    } else {
                        // Add stock unit if it doesn't exist
                        $stockValue.after(`<span class="stock-unit">-${stockUnit}</span>`);
                    }
                } else {
                    // Remove stock unit if stock is 0
                    $stockUnit.remove();
                }
            }
        } catch (error) {
            console.error('Error updating product card stock:', error);
        }
    }

    /**
     * Handle product card click
     * @param {number|string} productId
     * @param {{forceModal?: boolean}} [options] Pass { forceModal: true } to always open the
     *        product info modal, even when direct_cart is enabled (used by the edit icon).
     */
    async handleProductClick(productId, options = {}) {
        const forceModal = options && options.forceModal === true;
        try {
            // Ensure product is in IndexedDB (sync if needed)
            await posIndexedDB.ensureProductInIndexedDB(productId);
            // Get product from IndexedDB
            const product = await posIndexedDB.getProductById(productId);
            if (!product) {
                if (typeof showErrorNotification != 'undefined') {
                    showErrorNotification('Product not found. Please try again.');
                }
                return;
            }
            // Always get stock from IndexedDB (not from data attribute)
            product.stock = product.stock != undefined && product.stock != null ? parseFloat(product.stock) : 0;

            // If medicine mode and product has generic_name, show alternatives
            // But only if not triggered by keyboard navigation or active item click or edit icon
            if (this.isMedicineMode && product.generic_name && !this.isKeyboardNavigationActive && !forceModal) {
                await this.showMedicineAlternatives(product.generic_name);
                // Reset flag after showing alternatives
                this.isKeyboardNavigationActive = false;
                return;
            }

            // Get company data from session
            const companyData = JSON.parse($('#company_data').val() || '{}');
            const directCart = companyData.direct_cart || 'No';
            const productType = product.type || '';

            // Variation_Product always requires modal to select variation
            // Check if product type is General_Product or Installment_Product
            const isDirectCartEligible = (productType == 'General_Product' || productType == 'Installment_Product');
            
            // If direct cart is enabled and product is eligible, add directly to cart
            // (unless forceModal is set, e.g. from the edit icon which should always open the modal)
            if (!forceModal && isDirectCartEligible && directCart == 'Yes') {
                this.addProductToCartDirectly(product);
            } else {
                // Open modal for all other cases (including Variation_Product and forced modal)
                this.openProductInfoModal(product);
            }
            
            // Reset keyboard navigation flag after opening modal/cart
            this.isKeyboardNavigationActive = false;
        } catch (error) {
            console.error('Error handling product click:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to load product information');
            } else {
                alert('Failed to load product information');
            }
        }
    }

    /**
     * Show medicine alternatives in category list
     */
    async showMedicineAlternatives(genericName) {
        try {
            this.currentGenericName = genericName;
            
            // Get all products with same generic name
            const alternatives = await posIndexedDB.getProductsByGenericName(genericName);
            
            // Update category header to show "Alternative Medicine" (no icon)
            $('.pos-category-header').html('Alternative Medicine').data('category-id', 'alternative');
            
            // Clear and populate category list with alternatives
            const $categoryList = $('#pos-category-list');
            $categoryList.empty();
            
            if (alternatives.length === 0) {
                $categoryList.append('<div class="pos-category-item text-muted alternative-medicine">No alternatives found</div>');
                return;
            }
            
            // Add each alternative as a category item with alternative-medicine class
            alternatives.forEach((medicine) => {
                const $categoryItem = $('<div class="pos-category-item alternative-medicine" data-product-id="' + medicine.id + '">' + 
                    this.escapeHtml(medicine.name) + '</div>');
                $categoryList.append($categoryItem);
            });
            
            // Don't clear product grid - keep items visible while showing alternatives
            
            // Add click handler for alternative items
            $(document).off('click', '.pos-category-item[data-product-id]');
            $(document).on('click', '.pos-category-item[data-product-id]', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const clickedProductId = $(e.currentTarget).data('product-id');
                if (clickedProductId) {
                    // Open item modal for the clicked alternative (bypass alternative check)
                    try {
                        await posIndexedDB.ensureProductInIndexedDB(clickedProductId);
                        const product = await posIndexedDB.getProductById(clickedProductId);
                        if (!product) {
                            if (typeof showErrorNotification !== 'undefined') {
                                showErrorNotification('Product not found. Please try again.');
                            }
                            return;
                        }
                        product.stock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;

                        // Get company data from session
                        const companyData = JSON.parse($('#company_data').val() || '{}');
                        const directCart = companyData.direct_cart || 'No';
                        const productType = product.type || '';

                        // Variation_Product always requires modal to select variation
                        const isDirectCartEligible = (productType === 'General_Product' || productType === 'Installment_Product');
                        
                        // If direct cart is enabled and product is eligible, add directly to cart
                        if (isDirectCartEligible && directCart === 'Yes') {
                            this.addProductToCartDirectly(product);
                        } else {
                            // Open modal for all other cases (including Variation_Product)
                            this.openProductInfoModal(product);
                        }
                    } catch (error) {
                        console.error('Error handling alternative medicine click:', error);
                        if (typeof showErrorNotification !== 'undefined') {
                            showErrorNotification('Failed to load product information');
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error showing medicine alternatives:', error);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Failed to load alternatives');
            }
        }
    }

    /**
     * Add product directly to cart (without modal)
     */
    async addProductToCartDirectly(product) {


        const isComboProduct = product.type === 'Combo_Product';
        
        // Always get product from IndexedDB to ensure we have latest stock and tax information
        let productFromDB = null;
        let stock = 0;
        
        try {
            // Always fetch from IndexedDB to get latest stock and tax information
            productFromDB = await posIndexedDB.getProductById(product.id);
            if (productFromDB) {
                stock = productFromDB.stock !== undefined && productFromDB.stock !== null ? parseFloat(productFromDB.stock) : 0;
            } else {
                // If not in IndexedDB, try to sync it first
                await posIndexedDB.ensureProductInIndexedDB(product.id);
                productFromDB = await posIndexedDB.getProductById(product.id);
                if (productFromDB) {
                    stock = productFromDB.stock !== undefined && productFromDB.stock !== null ? parseFloat(productFromDB.stock) : 0;
                } else {
                    stock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;
                }
            }
        } catch (error) {
            console.error('Error getting product from IndexedDB:', error);
            stock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;
        }
        
        // Check stock based on allow_less_sale setting (skip for Combo_Product)
        if (!isComboProduct) {
            const companyData = JSON.parse($('#company_data').val() || '{}');
            const allowLessSale = companyData.allow_less_sale || 'No';
            
            if (allowLessSale === 'No' && stock <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('There is no stock available.');
                } else {
                    alert('There is no stock available.');
                }
                return;
            }
        }

        // Always use product from IndexedDB if available (it has latest stock and tax info)
        // Otherwise fall back to passed product
        const finalProduct = productFromDB || product;

        // Ensure tax_information is an array
        let taxInformation = [];
        if (finalProduct.tax_information) {
            if (Array.isArray(finalProduct.tax_information)) {
                taxInformation = finalProduct.tax_information;
            } else if (typeof finalProduct.tax_information === 'string') {
                // Try to parse if it's a JSON string
                try {
                    taxInformation = JSON.parse(finalProduct.tax_information);
                } catch (e) {
                    console.error('Error parsing tax_information:', e);
                    taxInformation = [];
                }
            }
        }

        // Check for promotion
        const promotion = finalProduct.promotion || null;
        let discount = 0;
        let discountType = 'fixed';
        let hasPromotionDiscount = false;
        
        // Handle promotion discount (Type 1)
        if (promotion && promotion.type === '1') {
            discount = promotion.discount || 0;
            discountType = promotion.discount_type || 'percentage';
            hasPromotionDiscount = true;
        }
        
        // Calculate total with promotion discount
        let unitPrice = finalProduct.sale_price || 0;
        let subtotal = unitPrice;
        let discountAmount = 0;
        if (hasPromotionDiscount) {
            if (discountType === 'percentage') {
                discountAmount = (subtotal * discount) / 100;
            } else {
                discountAmount = discount;
            }
        }
        const total = Math.max(0, subtotal - discountAmount);

        // Create cart item with default values (include new tax fields for GST)
        const cartItem = {
            product_id: finalProduct.id,
            product_name: finalProduct.name,
            product_code: finalProduct.code,
            product_type: finalProduct.type,
            quantity: 1,
            unit_price: unitPrice,
            discount: discount,
            discount_type: discountType,
            total: total,
            tax_information: taxInformation,
            applicable_tax_id: finalProduct.applicable_tax_id || null,
            tax_type: finalProduct.tax_type || 'Inclusive',
            item_seller_id: null, // Will be set when adding via modal for Service_Product
            promotion: promotion,
            has_promotion_discount: hasPromotionDiscount
        };

        // Add to cart (this will be handled by cart manager)
        if (typeof posCartManager !== 'undefined') {
            posCartManager.addItem(cartItem);
            
            // Handle Buy X Get Y promotion (Type 3)
            if (promotion && promotion.type === '3' && promotion.get_item_id) {
                await this.handleBuyXGetYPromotion(promotion, 1, cartItem);
            }
        } else {
            // Fallback: show notification
            if (typeof showSuccessNotification !== 'undefined') {
                showSuccessNotification(`${product.name} added to cart`);
            } else {
                alert(`${product.name} added to cart`);
            }
            console.log('Cart item:', cartItem);
        }
    }

    /**
     * When "IMEI/Serial" search is enabled: exact lookup in IndexedDB imei_serial_numbers,
     * resolve product (parent or variation), add line with selected_imei_serial.
     * @param {string} rawSearch
     * @returns {Promise<boolean>} true if lookup was handled (added or error shown); false = fall through
     */
    async tryAddToCartFromImeiSerialKey(rawSearch) {
        const term = String(rawSearch || '').trim();
        if (!term || typeof posIndexedDB === 'undefined' || !posIndexedDB.getImeiSerialRecord) {
            return false;
        }
        if (!$('#pos-search-by-imei-serial').length || !$('#pos-search-by-imei-serial').is(':checked')) {
            return false;
        }
        try {
            if (!posIndexedDB.isInitialized) {
                await posIndexedDB.init();
            }
            const record = await posIndexedDB.getImeiSerialRecord(term);
            if (!record || record.item_id == null || !record.imei_serial) {
                return false;
            }
            const serial = String(record.imei_serial).trim();
            const ctx = await posIndexedDB.findParentProductAndVariationForSellableId(record.item_id);
            if (!ctx || !ctx.parent) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Product not found for this IMEI/Serial. Re-sync products and try again.');
                } else {
                    alert('Product not found for this IMEI/Serial.');
                }
                return true;
            }
            const itemTypeFromRecord = record.item_type || '';
            if (itemTypeFromRecord !== 'IMEI_Product' && itemTypeFromRecord !== 'Serial_Product') {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Invalid IMEI/Serial record.');
                }
                return true;
            }
            const available = await posIndexedDB.getAvailableImeiSerialsForItem(record.item_id);
            const stillAvailable = available.some((s) => String(s).trim() === serial);
            if (!stillAvailable) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('This IMEI/Serial is not available.');
                } else {
                    alert('This IMEI/Serial is not available.');
                }
                return true;
            }

            const { parent, variation } = ctx;
            const finalProductId = parseInt(record.item_id, 10);
            let productName = '';
            let productCode = '';
            let unitPrice = 0;
            let promotion = null;

            if (variation) {
                const parentName = parent.name || '';
                const variationName = variation.name || '';
                productName =
                    parentName && variationName
                        ? `${parentName} - ${variationName}`
                        : variationName || parentName || '';
                productCode = variation.code || '';
                unitPrice = parseFloat(variation.sale_price) || 0;
                let varProm = variation.promotion;
                if (varProm && typeof varProm === 'string') {
                    try {
                        varProm = JSON.parse(varProm);
                    } catch (e) {
                        varProm = null;
                    }
                }
                promotion = varProm || parent.promotion || null;
            } else {
                productName = parent.name || '';
                productCode = parent.code || '';
                unitPrice = parseFloat(parent.sale_price) || 0;
                promotion = parent.promotion || null;
            }

            if (Number.isNaN(finalProductId)) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Invalid product reference for this IMEI/Serial.');
                }
                return true;
            }

            if (unitPrice <= 0) {
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Price must be greater than 0 for this item. Open the product modal to fix.');
                }
                return true;
            }

            let taxInformation = [];
            let applicableTaxId = null;
            let itemTaxType = 'Inclusive';
            try {
                const getItem = await posIndexedDB.getProductOrVariationById(finalProductId);
                if (getItem) {
                    if (getItem.tax_information) {
                        if (Array.isArray(getItem.tax_information)) {
                            taxInformation = getItem.tax_information;
                        } else if (typeof getItem.tax_information === 'string') {
                            try {
                                taxInformation = JSON.parse(getItem.tax_information) || [];
                            } catch (e) {
                                taxInformation = [];
                            }
                        }
                    }
                    applicableTaxId = getItem.applicable_tax_id || null;
                    itemTaxType = getItem.tax_type || 'Inclusive';
                }
            } catch (e) {
                console.warn('Tax load for IMEI quick add:', e);
            }

            let discount = 0;
            let discountType = 'fixed';
            let hasPromotionDiscount = false;
            if (promotion && String(promotion.type) === '1') {
                discount = parseFloat(promotion.discount) || 0;
                discountType = promotion.discount_type || 'percentage';
                if (discountType === 'flat') {
                    discountType = 'fixed';
                }
                hasPromotionDiscount = true;
            }
            const quantity = 1;
            const subtotal = unitPrice * quantity;
            let discountAmount = 0;
            if (hasPromotionDiscount) {
                if (discountType === 'percentage') {
                    discountAmount = (subtotal * discount) / 100;
                } else {
                    discountAmount = discount;
                }
            }
            const total = Math.max(0, subtotal - discountAmount);

            const cartItem = {
                product_id: finalProductId,
                product_name: productName,
                product_code: productCode || 'N/A',
                product_type: itemTypeFromRecord,
                quantity,
                unit_price: unitPrice,
                discount,
                discount_type: discountType,
                total,
                tax_information: taxInformation,
                applicable_tax_id: applicableTaxId,
                tax_type: itemTaxType,
                selected_imei_serial: [serial],
                selected_medicine_expiry: [],
                item_seller_id: null,
                combo_items: [],
                promotion: promotion || null,
                has_promotion_discount: hasPromotionDiscount,
            };

            if (typeof posCartManager === 'undefined') {
                return false;
            }

            const mergeIntoExistingImeiLine = async () => {
                const existingIndex = posCartManager.cartItems.findIndex((item) => {
                    if (String(item.product_id) !== String(finalProductId)) {
                        return false;
                    }
                    if (item.is_promotion_free_item === true) {
                        return false;
                    }
                    const pt = item.product_type || '';
                    const hasImeiSerials =
                        Array.isArray(item.selected_imei_serial) &&
                        item.selected_imei_serial.length > 0;
                    return (
                        pt === 'IMEI_Product' ||
                        pt === 'Serial_Product' ||
                        hasImeiSerials
                    );
                });
                if (existingIndex === -1) {
                    return false;
                }
                const existing = posCartManager.cartItems[existingIndex];
                const merged = Array.isArray(existing.selected_imei_serial)
                    ? existing.selected_imei_serial
                          .map((s) => String(s).trim())
                          .filter((s) => s !== '')
                    : [];
                if (merged.some((s) => s === serial)) {
                    if (typeof showWarningNotification !== 'undefined') {
                        showWarningNotification('This IMEI/Serial is already in the cart.');
                    } else if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification('This IMEI/Serial is already in the cart.');
                    }
                    return true;
                }
                merged.push(serial);
                existing.selected_imei_serial = merged;
                existing.quantity = merged.length;
                if (
                    existing.product_type !== 'IMEI_Product' &&
                    existing.product_type !== 'Serial_Product'
                ) {
                    existing.product_type = itemTypeFromRecord;
                }
                existing.total = posCartManager.calculateItemTotal(existing);
                if (existing.promotion && String(existing.promotion.type) === '3') {
                    try {
                        await posCartManager.updateBuyXGetYFreeItemQuantity(
                            existing.product_id,
                            existing.quantity
                        );
                    } catch (e) {
                        console.warn('Buy X Get Y update (IMEI merge):', e);
                    }
                }
                posCartManager.saveCartToStorage();
                posCartManager.renderCart();
                posCartManager.updateCartSummary();
                if (typeof showSuccessNotification !== 'undefined') {
                    showSuccessNotification(
                        `${existing.product_name || productName}: ${merged.join(', ')}`
                    );
                }
                return true;
            };

            const merged = await mergeIntoExistingImeiLine();
            if (merged) {
                return true;
            }

            posCartManager.addItem(cartItem, true);
            if (promotion && String(promotion.type) === '3' && promotion.get_item_id) {
                await this.handleBuyXGetYPromotion(promotion, quantity, cartItem);
            }
            if (typeof showSuccessNotification !== 'undefined') {
                showSuccessNotification(`${productName} added to cart`);
            }
            return true;
        } catch (err) {
            console.error('tryAddToCartFromImeiSerialKey:', err);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Could not add item from IMEI/Serial.');
            }
            return true;
        }
    }

    /**
     * Open product info modal
     */
    async openProductInfoModal(product) {
        const baseUrl = this.baseUrl;
        const imageUrl = product.photo 
            ? `${baseUrl}/public/uploads/items/${product.photo}`
            : `${baseUrl}/public/uploads/dummy_images/default-picture-pos.png`;

        // Set product information in modal
        $('#pos_item_info_product_id').val(product.id);
        $('#pos_item_info_product_type').val(product.type || '');
        $('#pos_item_info_product_name').text(product.name || '');
        $('#pos_item_info_product_code').text(product.code ? `Code: ${product.code}` : '');
        $('#pos_item_info_product_image').attr('src', imageUrl);
        
        // Set sale unit name
        const saleUnitName = product.sale_unit_name || 'PCS';
        $('#pos_item_info_sale_unit_display').text(saleUnitName);
        
        // Check if product is already in cart and populate fields
        // For Variation_Product, we'll check after variation is selected
        let existingCartQuantity = 1;
        let existingCartDiscount = 0;
        let existingCartDiscountType = 'fixed';
        let existingCartPrice = product.sale_price || 0;
        let existingCartPromotion = product.promotion || null; // Default to product promotion
        
        if (typeof posCartManager !== 'undefined' && product.type !== 'Variation_Product') {
            const existingItem = posCartManager.cartItems.find(item => item.product_id == product.id);
            if (existingItem) {
                existingCartQuantity = existingItem.quantity;
                existingCartDiscount = existingItem.discount || 0;
                existingCartDiscountType = existingItem.discount_type || 'fixed';
                existingCartPrice = existingItem.unit_price || product.sale_price || 0;
                // Preserve promotion data from cart item if it exists (important for editing)
                if (existingItem.promotion) {
                    existingCartPromotion = existingItem.promotion;
                }
            }
        }
        
        // Update product object with cart promotion data if available
        if (existingCartPromotion) {
            product.promotion = existingCartPromotion;
        }
        
        $('#pos_item_info_quantity').val(existingCartQuantity);
        $('#pos_item_info_discount').val(existingCartDiscount);
        $('#pos_item_info_discount_type').val(existingCartDiscountType);
        
        // Ensure Sale Price radio is selected by default
        $('#pos_item_info_price_type_sale').prop('checked', true);
        $('input[name="pos_item_info_price_type"]').not('#pos_item_info_price_type_sale').prop('checked', false);
        
        const isMedicineProduct = product.type === 'Medicine_Product' && product.expiry_date_maintain === 'Yes';
        
        // Always get stock from IndexedDB (not from data attribute)
        let productStock = 0;
        let productFromDB = null;
        try {
            productFromDB = await posIndexedDB.getProductById(product.id);
            if (productFromDB) {
                if (productFromDB.stock !== undefined && productFromDB.stock !== null) {
                    productStock = parseFloat(productFromDB.stock);
                    if (isNaN(productStock)) {
                        productStock = 0;
                    }
                } else {
                    productStock = 0;
                }
                console.log(`Product ${product.id} stock from IndexedDB:`, productStock);
            } else {
                console.warn(`Product ${product.id} not found in IndexedDB, using product object stock`);
                productStock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;
            }
        } catch (error) {
            console.error('Error getting stock from IndexedDB:', error);
            productStock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;
        }
        // Combo_Product: prefer combo_lines from IndexedDB (correct shape after product sync)
        if (product.type === 'Combo_Product' && productFromDB && Array.isArray(productFromDB.combo_items)) {
            product.combo_items = productFromDB.combo_items;
        }
        const stockDisplay = productStock > 0 ? `${productStock} ${product.sale_unit_name || 'PCS'}` : 'N/A';
        // Medicine_Product with expiry: total stock is summed from expiry batches (set in medicine block below)
        if (!isMedicineProduct) {
            $('#pos_item_info_stock').text(stockDisplay);
        }
        
        // Check for promotion
        await this.handlePromotionDisplay(product);
        
        // Check if IMEI/Serial product (needed for modal initialization)
        const isIMEISerialProduct = product.type === 'IMEI_Product' || product.type === 'Serial_Product';
        const isServiceProduct = product.type === 'Service_Product';
        const isComboProduct = product.type === 'Combo_Product';
        
        // Handle modal size - modal-xl for Combo_Product, modal-lg for others
        const $modalDialog = $('#modal_item_info_dialog');
        if (isComboProduct) {
            $modalDialog.removeClass('modal-lg').addClass('modal-xl');
        } else {
            $modalDialog.removeClass('modal-xl').addClass('modal-lg');
        }
        
        // Handle Combo_Product - show combo items table, hide price fields
        if (isComboProduct) {
            $('#pos_item_info_combo_wrap').show();
            $('#pos_item_info_price_type_wrap').hide();
            $('#pos_item_info_price_wrap').hide();
            $('#pos_item_info_price').prop('disabled', true);
            $('#pos_item_info_purchase_price').val(0);
            $('#pos_item_info_last_purchase_price').val(0);
            $('#pos_item_info_sale_price').val(0);
            $('#pos_item_info_whole_sale_price').val(0);
            
            // Disable quantity for combo products
            $('#pos_item_info_quantity').prop('disabled', true);
            $('#pos_item_info_quantity').val(1); // Combo products always have quantity 1
            
            // Check if combo product is already in cart and restore previous combo items
            let comboItemsToShow = product.combo_items || [];
            if (typeof posCartManager !== 'undefined') {
                const existingItem = posCartManager.cartItems.find(item => item.product_id == product.id);
                if (existingItem && existingItem.combo_items && existingItem.combo_items.length > 0) {
                    // Use combo items from cart (edit mode)
                    comboItemsToShow = existingItem.combo_items;
                    // Also restore the price
                    if (existingItem.unit_price) {
                        $('#pos_item_info_price').val(existingItem.unit_price);
                    }
                }
            }
            
            // Populate combo items table (normalize legacy IndexedDB/API shapes)
            this.populateComboItemsTable(this.normalizeComboItemsForModal(comboItemsToShow));
        } else {
            $('#pos_item_info_combo_wrap').hide();
            $('#pos_item_info_price_type_wrap').show();
            $('#pos_item_info_price_wrap').show();
            $('#pos_item_info_price').prop('disabled', false);
            
            // Handle quantity field - disable for IMEI/Serial and Medicine_Product (with expiry) products
            if (isIMEISerialProduct || isMedicineProduct) {
                $('#pos_item_info_quantity').prop('disabled', true);
                $('#pos_item_info_quantity').val(1); // Set to 1 as default
            } else {
                $('#pos_item_info_quantity').prop('disabled', false);
            }
        }
        
        // Handle employee selection - show for Service_Product only
        if (isServiceProduct) {
            $('#pos_item_info_employee_wrap').show();
            // Initialize select2 for employee dropdown
            if ($('#pos_item_info_employee_select').hasClass('select2-hidden-accessible')) {
                $('#pos_item_info_employee_select').select2('destroy');
            }
            $('#pos_item_info_employee_select').select2({
                placeholder: 'Select Employee',
                allowClear: false,
                width: '100%',
                dropdownParent: $('#modal_item_info')
            });
        } else {
            $('#pos_item_info_employee_wrap').hide();
        }
        
        // Handle stock display - hide for Service_Product
        if (isServiceProduct) {
            $('#pos_item_info_stock').closest('.stock-wrap').hide();
        } else {
            $('#pos_item_info_stock').closest('.stock-wrap').show();
        }
        
        // Handle Variation_Product
        if (product.type === 'Variation_Product') {

            $('#pos_item_info_variation_wrap').show();
            $('#pos_item_info_imei_wrap').hide();
            $('#pos_item_info_variation_select').empty().append('<option value="">Select Variation</option>');
            
            // Helper to append variation options to pos_item_info_variation_select
            const appendVariationOptions = (variationsList) => {
                if (!Array.isArray(variationsList)) return;
                variationsList.forEach(variation => {
                    const variationStock = variation.stock !== undefined && variation.stock !== null ? parseFloat(variation.stock) : 0;
                    const taxInfoJson = variation.tax_information ? JSON.stringify(variation.tax_information) : '[]';
                    const promotionJson = (variation.promotion ? JSON.stringify(variation.promotion) : '').replace(/'/g, '&#39;');
                    const option = $(`<option value="${variation.id}" 
                        data-sale-price="${variation.sale_price || 0}" 
                        data-purchase-price="${variation.purchase_price || 0}" 
                        data-last-three-purchase-avg="${variation.last_three_purchase_avg ?? variation.purchase_price ?? 0}" 
                        data-last-purchase-price="${variation.last_purchase_price ?? variation.purchase_price ?? 0}" 
                        data-whole-sale-price="${variation.whole_sale_price || 0}"
                        data-stock="${variationStock}"
                        data-photo="${variation.photo || ''}"
                        data-unit-type="${variation.unit_type || '1'}"
                        data-conversion-rate="${variation.conversion_rate || 1}"
                        data-sale-unit-name="${variation.sale_unit_name || 'PCS'}"
                        data-type="${variation.type || ''}"
                        data-tax-information='${taxInfoJson}'
                        data-promotion='${promotionJson}'>${variation.name} (${variation.code || ''}) - Stock: ${variationStock > 0 ? variationStock : 'N/A'}</option>`);
                    $('#pos_item_info_variation_select').append(option);
                });
            };

            // Load variations: try API first; when offline or fetch fails, use product.variations from IndexedDB
            let variationsLoaded = false;
            try {
                const response = await fetch(`${baseUrl}/pos/products/${product.id}/variations`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        appendVariationOptions(data.data);
                        variationsLoaded = true;
                    }
                }
            } catch (error) {
                console.warn('Variations API unavailable (e.g. offline), using IndexedDB:', error);
            }

            // When offline or API returned no data: populate from product.variations (from current product or IndexedDB)
            if (!variationsLoaded) {
                let variationsToUse = product.variations;
                if (!variationsToUse || !Array.isArray(variationsToUse) || variationsToUse.length === 0) {
                    try {
                        const productFromDB = typeof posIndexedDB !== 'undefined' ? await posIndexedDB.getProductById(product.id) : null;
                        if (productFromDB && productFromDB.variations && Array.isArray(productFromDB.variations) && productFromDB.variations.length > 0) {
                            variationsToUse = productFromDB.variations;
                        }
                    } catch (e) {
                        console.warn('Could not get product variations from IndexedDB:', e);
                    }
                }
                if (variationsToUse && variationsToUse.length > 0) {
                    appendVariationOptions(variationsToUse);
                }
            }

            // Reset prices (will be populated when variation is selected)
            this.updatePriceFields(0, 0, 0, 0);
        } else {
            $('#pos_item_info_variation_wrap').hide();
            // Set prices from product (last 3 avg + last purchase — not legacy purchase_price)
            const rawLastThree = Number(product.last_three_purchase_avg ?? product.purchase_price ?? 0) || 0;
            const rawLastPurchase = Number(product.last_purchase_price ?? product.purchase_price ?? 0) || 0;
            const salePrice = product.sale_price || 0;
            const wholeSalePrice = product.whole_sale_price || 0;

            const lastThreePurchase = this.convertPurchasePrice(
                rawLastThree,
                product.unit_type,
                product.conversion_rate
            );
            const lastPurchasePrice = this.convertPurchasePrice(
                rawLastPurchase,
                product.unit_type,
                product.conversion_rate
            );

            this.updatePriceFields(lastThreePurchase, lastPurchasePrice, salePrice, wholeSalePrice);
            
            // If product is already in cart with modified price, use that price
            if (existingCartPrice && existingCartPrice !== salePrice) {
                $('#pos_item_info_price').val(existingCartPrice);
            } else {
                // Ensure sale price is populated in price field initially
                $('#pos_item_info_price').val(salePrice);
            }
        }
        
            // Reset quantity field state
            $('#pos_item_info_quantity').prop('disabled', false);
            
            // Reset employee dropdown
            $('#pos_item_info_employee_wrap').hide();
            
            // Show stock display
            $('#pos_item_info_stock').closest('.stock-wrap').show();
            
            // Handle IMEI_Product and Serial_Product
        if (isIMEISerialProduct) {
            $('#pos_item_info_imei_wrap').show();
            $('#pos_item_info_medicine_wrap').hide();
            const labelText = product.type === 'IMEI_Product' ? 'Select IMEI' : 'Select Serial Number';
            $('#pos_item_info_imei_label').html(`${labelText} <span class="text-danger">*</span>`);
            
            // Disable quantity for IMEI/Serial products
            $('#pos_item_info_quantity').prop('disabled', true);
            $('#pos_item_info_quantity').val(1);
            
            // Destroy select2 if already initialized
            if ($('#pos_item_info_imei_select').hasClass('select2-hidden-accessible')) {
                $('#pos_item_info_imei_select').select2('destroy');
            }
            
            // Remove select2 class temporarily to prevent auto-initialization
            $('#pos_item_info_imei_select').removeClass('select2');
            
            // Clear and prepare select
            $('#pos_item_info_imei_select').empty();
            
            // Always get IMEI/serial numbers from IndexedDB
            // Prefer flat imei_serial_numbers store (no full product read / array scan)
            try {
                let imeiNumbers = [];
                if (typeof posIndexedDB.getAvailableImeiSerialsForItem === 'function') {
                    imeiNumbers = await posIndexedDB.getAvailableImeiSerialsForItem(product.id);
                }
                if (!imeiNumbers || imeiNumbers.length === 0) {
                    const productFromDB = await posIndexedDB.getProductById(product.id);
                    if (productFromDB && productFromDB.imei_number && Array.isArray(productFromDB.imei_number)) {
                        imeiNumbers = productFromDB.imei_number;
                    } else if (product.imei_number && Array.isArray(product.imei_number)) {
                        imeiNumbers = product.imei_number;
                    }
                }

                if (imeiNumbers && imeiNumbers.length > 0) {
                    imeiNumbers.forEach(imei => {
                        if (imei !== null && imei !== undefined && String(imei).trim() !== '') {
                            const imeiValue = String(imei).trim();
                            // Use text() method to safely set option text and value
                            const option = $('<option></option>')
                                .attr('value', imeiValue)
                                .text(imeiValue);
                            $('#pos_item_info_imei_select').append(option);
                        }
                    });
                } else {
                    // Show message if no IMEI/serial available
                    const noDataText = `No ${product.type === 'IMEI_Product' ? 'IMEI' : 'Serial Number'} available`;
                    $('#pos_item_info_imei_select').append(`<option value="" disabled>${noDataText}</option>`);
                }
            } catch (error) {
                console.error('Error getting IMEI from IndexedDB:', error);
                const noDataText = `Error loading ${product.type === 'IMEI_Product' ? 'IMEI' : 'Serial Number'}`;
                $('#pos_item_info_imei_select').append(`<option value="" disabled>${noDataText}</option>`);
            }
            
            // Note: Select2 will be initialized after modal is shown (see modal.show() section below)
        } else {
            $('#pos_item_info_imei_wrap').hide();
        }
        
        // Handle Medicine_Product with expiry_date_maintain = "Yes"
        if (isMedicineProduct) {
            $('#pos_item_info_medicine_wrap').show();
            $('#pos_item_info_imei_wrap').hide();
            
            // Disable quantity for Medicine products
            $('#pos_item_info_quantity').prop('disabled', true);
            $('#pos_item_info_quantity').val(1);
            
            // Clear and prepare select
            $('#pos_item_info_medicine_expiry_select').empty().append('<option value="">Select Expiry Date</option>');
            
            // Clear medicine table
            $('#pos_item_info_medicine_table_body').empty();
            $('#pos_item_info_medicine_table_wrap').hide();
            
            // Initialize selected medicine expiry dates array (will be populated from cart if editing)
            if (!this.selectedMedicineExpiryDates) {
                this.selectedMedicineExpiryDates = [];
            } else {
                this.selectedMedicineExpiryDates = []; // Clear previous selections
            }
            
            let totalMedicineExpiryStock = 0;
            
            // Get medicine data from IndexedDB
            try {
                const productFromDB = await posIndexedDB.getProductById(product.id);
                let medicineData = [];
                
                if (productFromDB && productFromDB.medicine && Array.isArray(productFromDB.medicine)) {
                    medicineData = productFromDB.medicine;
                    console.log('Medicine data from IndexedDB:', medicineData);
                } else if (product.medicine && Array.isArray(product.medicine)) {
                    // Fallback to product object if IndexedDB doesn't have it
                    medicineData = product.medicine;
                    console.log('Medicine data from product object:', medicineData);
                }
                
                // Populate expiry date dropdown
                if (medicineData && medicineData.length > 0) {
                    // Group by expiry date and calculate total stock
                    const expiryMap = new Map();
                    medicineData.forEach(med => {
                        const expiryDate = med.expiry_imei_serial || '';
                        const stockQty = parseFloat(med.stock_quantity) || 0;
                        
                        if (expiryDate && stockQty > 0) {
                            if (expiryMap.has(expiryDate)) {
                                expiryMap.set(expiryDate, expiryMap.get(expiryDate) + stockQty);
                            } else {
                                expiryMap.set(expiryDate, stockQty);
                            }
                        }
                    });
                    
                    totalMedicineExpiryStock = Array.from(expiryMap.values()).reduce((sum, q) => sum + q, 0);
                    
                    // Sort expiry dates (ascending)
                    const sortedExpiryDates = Array.from(expiryMap.entries()).sort((a, b) => {
                        return new Date(a[0]) - new Date(b[0]);
                    });
                    
                    // Add options to select
                    sortedExpiryDates.forEach(([expiryDate, stockQty]) => {
                        const formattedDate = this.formatDateForDisplay(expiryDate);
                        const option = $('<option></option>')
                            .attr('value', expiryDate)
                            .attr('data-stock', stockQty)
                            .text(`${formattedDate} (Stock: ${stockQty})`);
                        $('#pos_item_info_medicine_expiry_select').append(option);
                    });
                    
                    if (sortedExpiryDates.length === 0) {
                        $('#pos_item_info_medicine_expiry_select').append('<option value="" disabled>No expiry dates available</option>');
                    }
                } else {
                    $('#pos_item_info_medicine_expiry_select').append('<option value="" disabled>No expiry dates available</option>');
                }
                
                // Check if product is already in cart and restore previous selections
                if (typeof posCartManager !== 'undefined') {
                    const existingItem = posCartManager.cartItems.find(item => item.product_id == product.id);
                    if (existingItem && existingItem.selected_medicine_expiry && Array.isArray(existingItem.selected_medicine_expiry) && existingItem.selected_medicine_expiry.length > 0) {
                        // Restore selected expiry dates
                        existingItem.selected_medicine_expiry.forEach(medExpiry => {
                            this.addMedicineExpiryToTable(medExpiry.expiry_date, medExpiry.stock_quantity, medExpiry.quantity);
                        });
                    }
                }
            } catch (error) {
                console.error('Error getting medicine data from IndexedDB:', error);
                $('#pos_item_info_medicine_expiry_select').append('<option value="" disabled>Error loading expiry dates</option>');
            }
            
            const medicineStockUnit = product.sale_unit_name || 'PCS';
            const medicineStockDisplay = totalMedicineExpiryStock > 0
                ? `${totalMedicineExpiryStock} ${medicineStockUnit}`
                : 'N/A';
            $('#pos_item_info_stock').text(medicineStockDisplay);
        } else {
            $('#pos_item_info_medicine_wrap').hide();
        }
        
        // Handle Service_Product - show employee dropdown
        if (isServiceProduct) {
            $('#pos_item_info_employee_wrap').show();
            // Hide stock display for Service_Product
            $('#pos_item_info_stock').closest('.stock-wrap').hide();
            // Initialize select2 for employee dropdown
            if ($('#pos_item_info_employee_select').hasClass('select2-hidden-accessible')) {
                $('#pos_item_info_employee_select').select2('destroy');
            }
            $('#pos_item_info_employee_select').select2({
                placeholder: 'Select Employee',
                allowClear: false,
                width: '100%',
                dropdownParent: $('#modal_item_info')
            });
            
            // Check if product is already in cart and pre-select employee
            if (typeof posCartManager !== 'undefined') {
                const existingItem = posCartManager.cartItems.find(item => item.product_id == product.id);
                if (existingItem && existingItem.item_seller_id) {
                    $('#pos_item_info_employee_select').val(existingItem.item_seller_id).trigger('change');
                }
            }
        } else {
            $('#pos_item_info_employee_wrap').hide();
        }
        
        // Reset discount values only if there's no promotion discount
        // (Promotion discount was already set in handlePromotionDisplay)
        if (!product.promotion || product.promotion.type !== '1') {
            $('#pos_item_info_discount').val(0);
            $('#pos_item_info_discount_type').val('fixed');
        }
        
        // Ensure Sale Price radio is selected by default and price is populated
        $('#pos_item_info_price_type_sale').prop('checked', true);
        $('input[name="pos_item_info_price_type"]').not('#pos_item_info_price_type_sale').prop('checked', false);
        
        // Re-apply promotion discount if exists (in case it was reset above)
        if (product.promotion && product.promotion.type === '1') {
            const discountValue = parseFloat(product.promotion.discount) || 0;
            const discountType = product.promotion.discount_type || 'fixed';
            $('#pos_item_info_discount').val(discountValue);
            $('#pos_item_info_discount_type').val(discountType);
            $('#pos_item_info_discount').prop('disabled', true);
            $('#pos_item_info_discount_type').prop('disabled', true);
        }
        
        // Calculate initial total
        this.calculateItemTotal();

        // Show modal — use getOrCreateInstance (not `new Modal` every time). Repeated
        // `new bootstrap.Modal(sameEl)` stacks instances and can break internal `_config` / backdrop handling.
        const modalEl = document.getElementById('modal_item_info');
        if (!modalEl) {
            console.error('POS: #modal_item_info not found in the DOM.');
            return;
        }
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            console.error('POS: Bootstrap Modal is not available.');
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
            backdrop: 'static',
            keyboard: true,
        });

        // For IMEI/Serial products, ensure Select2 initializes after modal is shown
        if (isIMEISerialProduct) {
            $(modalEl).one('shown.bs.modal', function() {
                setTimeout(() => {
                    if ($('#pos_item_info_imei_select').length && !$('#pos_item_info_imei_select').hasClass('select2-hidden-accessible')) {
                        $('#pos_item_info_imei_select').select2({
                            placeholder: `Select ${product.type === 'IMEI_Product' ? 'IMEI' : 'Serial Number'}`,
                            allowClear: false,
                            multiple: true,
                            width: '100%',
                            dropdownParent: $('#modal_item_info')
                        });
                        
                        // Check if product is already in cart and pre-select IMEI/serial
                        if (typeof posCartManager !== 'undefined') {
                            const existingItem = posCartManager.cartItems.find(item => item.product_id == product.id);
                            if (existingItem && existingItem.selected_imei_serial && existingItem.selected_imei_serial.length > 0) {
                                $('#pos_item_info_imei_select').val(existingItem.selected_imei_serial).trigger('change');
                            }
                        }
                    }
                }, 50);
            });
        }
        
        // For Combo_Product, initialize tooltip after modal is shown
        if (isComboProduct) {
            $(modalEl).one('shown.bs.modal', function() {
                setTimeout(() => {
                    const tooltipIcon = document.querySelector('#pos_item_info_combo_select_all + i');
                    if (tooltipIcon && typeof bootstrap !== 'undefined') {
                        // Destroy existing tooltip if any
                        const existingTooltip = bootstrap.Tooltip.getInstance(tooltipIcon);
                        if (existingTooltip) {
                            existingTooltip.dispose();
                        }
                        // Initialize new tooltip
                        new bootstrap.Tooltip(tooltipIcon);
                    }
                }, 50);
            });
        }
        
        modal.show();
    }

    /**
     * Normalize combo component rows from API / IndexedDB (fixes legacy payloads where Eloquent `(array)` nested attributes).
     * @param {Array} comboItems
     * @returns {Array<Object>}
     */
    normalizeComboItemsForModal(comboItems) {
        if (!Array.isArray(comboItems)) {
            return [];
        }
        return comboItems.map((raw) => {
            if (!raw || typeof raw !== 'object') {
                return null;
            }
            const attrs = raw.attributes && typeof raw.attributes === 'object' ? raw.attributes : {};
            const merged = { ...attrs, ...raw };
            const itemId = merged.item_id != null && merged.item_id !== '' ? merged.item_id : null;
            const itemName = merged.item_name || merged.name || merged.product_name || '';
            const saleUnitName = merged.sale_unit_name || merged.unit_name || '';
            const qty = parseFloat(merged.quantity);
            const amtSrc = merged.amount != null && merged.amount !== '' ? merged.amount : merged.unit_price;
            const amount = parseFloat(amtSrc);
            let total = parseFloat(merged.total);
            if (Number.isNaN(total)) {
                total = (parseFloat(qty) || 0) * (parseFloat(amount) || 0);
            }
            return {
                item_id: itemId,
                item_name: itemName,
                sale_unit_name: saleUnitName,
                quantity: !Number.isNaN(qty) ? qty : 1,
                amount: !Number.isNaN(amount) ? amount : 0,
                unit_price: !Number.isNaN(amount) ? amount : 0,
                total,
                show_in_invoice: merged.show_in_invoice,
                employee_id: merged.employee_id || null,
            };
        }).filter((row) => row && row.item_id != null);
    }

    /**
     * Populate combo items table
     */
    populateComboItemsTable(comboItems) {
        const $tbody = $('#pos_item_info_combo_tbody');
        $tbody.empty();
        
        if (!comboItems || comboItems.length === 0) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">No combo items available</td></tr>');
            $('#pos_item_info_combo_total_price').text('0.00');
            return;
        }
        
        const self = this;
        comboItems.forEach((comboItem, index) => {
            // Handle both product.combo_items structure and cart combo_items structure
            const showInInvoice = comboItem.show_in_invoice !== undefined 
                ? (comboItem.show_in_invoice === true || comboItem.show_in_invoice === 'Yes' || comboItem.show_in_invoice === 1)
                : true;
            const quantity = parseFloat(comboItem.quantity) || 1;
            // Support both 'amount' and 'unit_price' fields
            const unitPrice = parseFloat(comboItem.amount || comboItem.unit_price) || 0;
            const total = parseFloat(comboItem.total) || (quantity * unitPrice);
            const itemId = comboItem.item_id != null ? comboItem.item_id : null;
            const employeeId = comboItem.employee_id || null;
            const saleUnitName = comboItem.sale_unit_name || comboItem.unit_name || '';
            
            const row = $(`
                <tr data-combo-item-index="${index}" data-combo-item-id="${itemId}">
                    <td>
                        <div class="form-check">
                            <input type="checkbox" class="combo-item-show-invoice form-check-input" 
                               data-index="${index}" ${showInInvoice ? 'checked' : ''}>
                        </div>
                    </td>
                    <td>${this.escapeHtml(comboItem.item_name || '')}</td>
                    <td>
                        <input type="text" class="form-control number-input form-control-sm combo-item-quantity" 
                               data-index="${index}" value="${quantity}" min="0.001" step="0.001">
                    </td>
                    <td>
                        <input type="text" class="form-control number-input form-control-sm combo-item-unit-price" 
                               data-index="${index}" value="${this.formatPrice(unitPrice)}" min="0" step="0.01">
                    </td>
                    <td class="combo-item-total" data-index="${index}">${this.formatPrice(total)}</td>
                    <td>
                        <select class="form-select form-select-sm combo-item-employee" data-index="${index}">
                            <option value="">Select Employee</option>
                        </select>
                    </td>
                </tr>
            `);
            
            // Populate employee dropdown from the main employee select
            const $employeeSelect = row.find('.combo-item-employee');
            $('#pos_item_info_employee_select option').each(function() {
                if ($(this).val()) {
                    $employeeSelect.append($(this).clone());
                }
            });
            
            // Restore employee selection if exists (from cart)
            if (employeeId) {
                $employeeSelect.val(employeeId).trigger('change');
            }
            
            $tbody.append(row);
        });
        
        // Calculate initial total
        this.calculateComboItemsTotal();
        
        // Setup event listeners for combo items
        this.setupComboItemsEventListeners();
    }

    /**
     * Setup event listeners for combo items table
     */
    setupComboItemsEventListeners() {
        const self = this;
        
        // Remove existing listeners to prevent duplicates
        $(document).off('input', '.combo-item-quantity');
        $(document).off('input', '.combo-item-unit-price');
        $(document).off('change', '#pos_item_info_combo_select_all');
        $(document).off('change', '.combo-item-show-invoice');
        
        // Quantity change
        $(document).on('input', '.combo-item-quantity', function() {
            const index = $(this).data('index');
            self.updateComboItemTotal(index);
        });
        
        // Unit price change
        $(document).on('input', '.combo-item-unit-price', function() {
            const index = $(this).data('index');
            self.updateComboItemTotal(index);
        });
        
        // Select all checkbox
        $(document).on('change', '#pos_item_info_combo_select_all', function() {
            const isChecked = $(this).prop('checked');
            $('.combo-item-show-invoice').prop('checked', isChecked);
        });
        
        // Individual checkbox change
        $(document).on('change', '.combo-item-show-invoice', function() {
            // Update select all checkbox state
            const totalCheckboxes = $('.combo-item-show-invoice').length;
            const checkedCheckboxes = $('.combo-item-show-invoice:checked').length;
            $('#pos_item_info_combo_select_all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
    }

    /**
     * Update combo item total for a specific row
     */
    updateComboItemTotal(index) {
        const $row = $(`tr[data-combo-item-index="${index}"]`);
        const quantity = parseFloat(String($row.find('.combo-item-quantity').val() || '').replace(/,/g, '')) || 0;
        const unitPrice = parseFloat(String($row.find('.combo-item-unit-price').val() || '').replace(/,/g, '')) || 0;
        const total = quantity * unitPrice;
        
        $row.find('.combo-item-total').text(this.formatPrice(total));
        
        // Update main price field and total
        this.calculateComboItemsTotal();
    }

    /**
     * Calculate total of all combo items and update price field
     */
    calculateComboItemsTotal() {
        let total = 0;
        $('.combo-item-total').each(function() {
            const totalText = $(this).text().replace(/,/g, '');
            total += parseFloat(totalText) || 0;
        });
        
        $('#pos_item_info_combo_total_price').text(this.formatPrice(total));
        $('#pos_item_info_price').val(total);
        $('#pos_item_info_total').val(this.formatPrice(total));
    }

    /**
     * Get combo items data from table
     */
    getComboItemsData() {
        const comboItems = [];
        $('#pos_item_info_combo_tbody tr').each(function() {
            const index = $(this).data('combo-item-index');
            if (index !== undefined) {
                const itemId = $(this).data('combo-item-id');
                const itemName = $(this).find('td:eq(1)').text().trim();
                const quantity = parseFloat(String($(this).find('.combo-item-quantity').val() || '').replace(/,/g, '')) || 0;
                const unitPrice = parseFloat(String($(this).find('.combo-item-unit-price').val() || '').replace(/,/g, '')) || 0;
                const showInInvoice = $(this).find('.combo-item-show-invoice').prop('checked');
                const employeeId = $(this).find('.combo-item-employee').val() || null;
                
                comboItems.push({
                    item_id: itemId,
                    item_name: itemName,
                    quantity: quantity,
                    unit_price: unitPrice,
                    amount: unitPrice, // Keep amount for consistency
                    total: quantity * unitPrice,
                    show_in_invoice: showInInvoice,
                    employee_id: employeeId
                });
            }
        });
        return comboItems;
    }

    /**
     * Convert stored purchase cost fields based on unit type and conversion rate
     * If unit_type is 2 (double unit), divide by conversion_rate (sale-unit price).
     * @param {number} amount - last_three_purchase_avg or last_purchase_price (base unit)
     * @param {string|number} unitType - Unit type (1 or '1' for single, 2 or '2' for double)
     * @param {number} conversionRate - Conversion rate
     * @returns {number} - Converted amount for the sale unit
     */
    convertPurchasePrice(amount, unitType, conversionRate) {
        if (!amount || amount <= 0) {
            return 0;
        }

        if (unitType === '2' || unitType === 2) {
            const rate = parseFloat(conversionRate) || 1;
            if (rate > 0) {
                return amount / rate;
            }
        }

        return amount;
    }

    /**
     * Update price fields (hidden values and display labels).
     * @param {number} lastThreeAvgPurchase - Last 3 purchase average (sale unit)
     * @param {number} lastPurchasePrice - Last single purchase price (sale unit)
     * @param {number} salePrice
     * @param {number} wholeSalePrice
     */
    updatePriceFields(lastThreeAvgPurchase, lastPurchasePrice, salePrice, wholeSalePrice) {
        $('#pos_item_info_purchase_price').val(lastThreeAvgPurchase || 0);
        $('#pos_item_info_last_purchase_price').val(lastPurchasePrice || 0);
        $('#pos_item_info_sale_price').val(salePrice || 0);
        $('#pos_item_info_whole_sale_price').val(wholeSalePrice || 0);

        $('#pos_item_info_purchase_price_display').text(this.formatPrice(lastThreeAvgPurchase || 0));
        $('#pos_item_info_last_purchase_price_display').text(this.formatPrice(lastPurchasePrice || 0));
        $('#pos_item_info_sale_price_display').text(this.formatPrice(salePrice || 0));
        $('#pos_item_info_whole_sale_price_display').text(this.formatPrice(wholeSalePrice || 0));

        const selectedPriceType = $('input[name="pos_item_info_price_type"]:checked').val() || 'sale';
        let priceToSet = 0;
        if (selectedPriceType === 'purchase') {
            priceToSet = lastThreeAvgPurchase || 0;
        } else if (selectedPriceType === 'last_purchase') {
            priceToSet = lastPurchasePrice || 0;
        } else if (selectedPriceType === 'whole_sale') {
            priceToSet = wholeSalePrice || 0;
        } else {
            priceToSet = salePrice || 0;
        }
        $('#pos_item_info_price').val(priceToSet);
    }

    /**
     * Handle promotion display and logic
     */
    async handlePromotionDisplay(product) {
        // Reset promotion display
        $('#pos_item_info_promotion_wrap').hide();
        $('#pos_item_info_discount').prop('disabled', false);
        
        // Check if product has promotion
        if (!product.promotion || !product.promotion.id) {
            return;
        }
        
        const promotion = product.promotion;
        
        // Show promotion info
        $('#pos_item_info_promotion_title').text(promotion.title || 'Promotion');
        $('#pos_item_info_promotion_wrap').show();
        
        // Store promotion data in hidden field for later use
        $('#pos_item_info_product_id').data('promotion', promotion);
        
        // Handle different promotion types
        if (promotion.type === '1') {
            // Type 1: Discount on specific item
            let promotionDetails = '';
            const discountValue = parseFloat(promotion.discount) || 0;
            let discountType = promotion.discount_type || 'fixed';
            if (discountType === 'flat') discountType = 'fixed';
            
            if (discountType === 'percentage') {
                promotionDetails = `${discountValue}% discount`;
            } else {
                promotionDetails = `${discountValue} ${this.companySessionData.currency_symbol || '$'} discount`;
            }
            $('#pos_item_info_promotion_details').text(promotionDetails);
            
            // Set discount value and discount type
            const $discountInput = $('#pos_item_info_discount');
            const $discountTypeSelect = $('#pos_item_info_discount_type');
            
            // Set discount value
            $discountInput.val(discountValue);
            
            // Set discount type - ensure the option exists and is selected
            $discountTypeSelect.val(discountType);
            
            // Trigger change event to ensure select dropdown updates
            $discountTypeSelect.trigger('change');
            
            // Disable both fields
            $discountInput.prop('disabled', true);
            $discountTypeSelect.prop('disabled', true);
            
            // Recalculate total with promotion discount
            this.calculateItemTotal();
        } else if (promotion.type === '2') {
            // Type 2: Coupon code discount (not item-specific, but show info)
            let promotionDetails = '';
            if (promotion.discount_type === 'percentage') {
                promotionDetails = `${promotion.discount}% discount with coupon code: ${promotion.coupon_code || 'N/A'}`;
            } else {
                promotionDetails = `${promotion.discount} ${this.companySessionData.currency_symbol || '$'} discount with coupon code: ${promotion.coupon_code || 'N/A'}`;
            }
            $('#pos_item_info_promotion_details').text(promotionDetails);
        } else if (promotion.type === '3') {
            // Type 3: Buy X Get Y (get_item no longer in API; use generic label)
            const buyQty = Number(promotion.buy_qty) || 0;
            const getQty = Number(promotion.get_qty) || 0;
            const promotionDetails = `Buy ${buyQty} Get ${getQty} Free Item`;
            $('#pos_item_info_promotion_details').text(promotionDetails);
        }
    }

    /**
     * Calculate item total in modal
     */
    calculateItemTotal() {
        const quantity = parseFloat($('#pos_item_info_quantity').val()) || 1;
        const price = parseFloat($('#pos_item_info_price').val()) || 0;
        const discount = parseFloat($('#pos_item_info_discount').val()) || 0;
        const discountType = $('#pos_item_info_discount_type').val();

        let subtotal = quantity * price;
        let discountAmount = 0;

        if (discountType === 'percentage') {
            discountAmount = (subtotal * discount) / 100;
        } else {
            discountAmount = discount;
        }

        const total = Math.max(0, subtotal - discountAmount);
        $('#pos_item_info_total').val(total.toFixed(2));
    }

    /**
     * Add item to cart from modal
     */
    async addItemToCartFromModal() {
        // Get productId and ensure it's a number (IndexedDB uses number as key)
        const productIdStr = $('#pos_item_info_product_id').val();
        const productId = productIdStr ? parseInt(productIdStr, 10) : null;
        const productType = $('#pos_item_info_product_type').val();
        let productName = $('#pos_item_info_product_name').text();
        let productCode = $('#pos_item_info_product_code').text().replace('Code: ', '');
        let quantity = parseFloat($('#pos_item_info_quantity').val()) || 1;
        let unitPrice = parseFloat($('#pos_item_info_price').val()) || 0;
        const discountInput = $('#pos_item_info_discount').val().toString().trim();
        const discountType = $('#pos_item_info_discount_type').val();
        let total = parseFloat($('#pos_item_info_total').val()) || 0;
        
        // Handle Combo_Product - get price from combo items total
        const isComboProduct = productType === 'Combo_Product';
        let comboItemsData = [];
        if (isComboProduct) {
            comboItemsData = this.getComboItemsData();
            // Price is already set from combo items total
            unitPrice = parseFloat($('#pos_item_info_price').val()) || 0;
            total = unitPrice; // For combo products, total = price (no discount applied to combo items individually)
            quantity = 1; // Combo products always have quantity 1
        }

        // Validation
        if (!productId || isNaN(productId)) {
            showErrorNotification('Product information is missing');
            return;
        }


        // Check if IMEI/Serial product and validate selection
        const isIMEISerialProduct = productType === 'IMEI_Product' || productType === 'Serial_Product';
        const isMedicineProduct = productType === 'Medicine_Product' && $('#pos_item_info_medicine_wrap').is(':visible');
        let selectedImeiSerial = [];
        let selectedMedicineExpiry = [];
        
        if (isIMEISerialProduct) {
            selectedImeiSerial = $('#pos_item_info_imei_select').val() || [];
            if (!selectedImeiSerial || selectedImeiSerial.length === 0) {
                const labelText = productType === 'IMEI_Product' ? 'IMEI' : 'Serial Number';
                showErrorNotification(`Please select at least one ${labelText}`);
                return;
            }
            // For IMEI/Serial products, quantity should match the number of selected IMEI/serial numbers
            if (quantity !== selectedImeiSerial.length) {
                quantity = selectedImeiSerial.length;
                $('#pos_item_info_quantity').val(quantity);
            }
        }
        
        if (isMedicineProduct) {
            // Get selected medicine expiry dates from table
            if (!this.selectedMedicineExpiryDates || this.selectedMedicineExpiryDates.length === 0) {
                showErrorNotification('Please select at least one expiry date');
                return;
            }
            
            // Update quantities from table inputs
            this.selectedMedicineExpiryDates.forEach(medExpiry => {
                const qtyInput = $(`.medicine-quantity-input[data-expiry-date="${medExpiry.expiry_date}"]`);
                if (qtyInput.length > 0) {
                    const qty = parseFloat(qtyInput.val()) || 1;
                    if (qty > medExpiry.stock_quantity) {
                        showErrorNotification(`Quantity for ${this.formatDateForDisplay(medExpiry.expiry_date)} cannot exceed stock (${medExpiry.stock_quantity})`);
                        return;
                    }
                    medExpiry.quantity = qty;
                }
            });
            
            selectedMedicineExpiry = this.selectedMedicineExpiryDates;
            
            // Calculate total quantity
            quantity = selectedMedicineExpiry.reduce((sum, med) => sum + med.quantity, 0);
        }
        
        // For Variation_Product, check if variation is selected
        let selectedVariationId = null;
        let stock = null;
        let parentProductName = '';
        let isVariationServiceProduct = false;
        if (productType === 'Variation_Product') {
            selectedVariationId = $('#pos_item_info_variation_select').val();
            if (!selectedVariationId) {
                showErrorNotification('Please select a variation');
                return;
            }
            
            // Get parent product name (from modal title)
            parentProductName = $('#pos_item_info_product_name').text().trim();
            
            // Get variation details
            const selectedOption = $('#pos_item_info_variation_select option:selected');
            const variationType = selectedOption.data('type') || '';
            isVariationServiceProduct = variationType === 'Service_Product';

            const variationName = selectedOption.text().split(' (')[0]; // Get name without code
            
            // Combine parent name with variation name: "Parent Name - Variation Name"
            productName = parentProductName ? `${parentProductName} - ${variationName}` : variationName;
            productCode = selectedOption.text().match(/\(([^)]+)\)/)?.[1] || '';
            stock = parseFloat(selectedOption.data('stock')) || 0;
        } else if (isIMEISerialProduct) {
            // For IMEI/Serial products, check available IMEI/serial numbers from IndexedDB
            try {
                // Ensure IndexedDB is initialized
                if (!posIndexedDB.isInitialized) {
                    await posIndexedDB.init();
                }
                
                // Ensure productId is a number (IndexedDB key is number)
                const numericProductId = parseInt(productId, 10);
                if (isNaN(numericProductId)) {
                    console.error(`Invalid productId for IMEI product: ${productId}`);
                    stock = 0;
                } else {
                    let imeiCount = 0;
                    if (typeof posIndexedDB.getAvailableImeiSerialsForItem === 'function') {
                        const fromTable = await posIndexedDB.getAvailableImeiSerialsForItem(numericProductId);
                        imeiCount = fromTable ? fromTable.length : 0;
                    }
                    if (imeiCount > 0) {
                        stock = imeiCount;
                    } else {
                        const product = await posIndexedDB.getProductById(numericProductId);
                        if (product && product.imei_number && Array.isArray(product.imei_number)) {
                            stock = product.imei_number.length;
                        } else {
                            stock = 0;
                        }
                    }
                }
            } catch (error) {
                console.error('Error getting IMEI/Serial product stock:', error);
                stock = 0;
            }
        } else if (isMedicineProduct) {
            // For Medicine products, calculate total stock from selected expiry dates
            stock = selectedMedicineExpiry.reduce((sum, med) => sum + med.stock_quantity, 0);
            
            // Validate stock for each expiry date
            for (const medExpiry of selectedMedicineExpiry) {
                if (medExpiry.quantity > medExpiry.stock_quantity) {
                    showErrorNotification(`Quantity for ${this.formatDateForDisplay(medExpiry.expiry_date)} cannot exceed stock (${medExpiry.stock_quantity})`);
                    return;
                }
            }
        } else {
            // Always get stock from IndexedDB (not from data attribute) for regular products
            try {
                // Ensure IndexedDB is initialized
                if (!posIndexedDB.isInitialized) {
                    await posIndexedDB.init();
                }
                
                // Ensure productId is a number (IndexedDB key is number)
                const numericProductId = parseInt(productId, 10);
                if (isNaN(numericProductId)) {
                    console.error(`Invalid productId: ${productId}`);
                    stock = 0;
                } else {
                    console.log(`Fetching product from IndexedDB with ID: ${numericProductId} (type: ${typeof numericProductId})`);
                    const product = await posIndexedDB.getProductById(numericProductId);
                    
                    console.log(`Product retrieved from IndexedDB:`, product);

                    if (product) {
                        // Ensure stock is a valid number
                        if (product.stock !== undefined && product.stock !== null) {
                            stock = parseFloat(product.stock);
                            // If parseFloat returns NaN, set to 0
                            if (isNaN(stock)) {
                                console.warn(`Product ${numericProductId} stock is NaN, setting to 0`);
                                stock = 0;
                            } else {
                                console.log(`Product ${numericProductId} stock from IndexedDB:`, stock);
                            }
                        } else {
                            console.warn(`Product ${numericProductId} stock is undefined or null`);
                            stock = 0;
                        }
                    } else {
                        console.warn(`Product ${numericProductId} not found in IndexedDB when adding to cart`);
                        // Try to ensure product is in IndexedDB
                        await posIndexedDB.ensureProductInIndexedDB(numericProductId);
                        const productRetry = await posIndexedDB.getProductById(numericProductId);
                        if (productRetry && productRetry.stock !== undefined && productRetry.stock !== null) {
                            stock = parseFloat(productRetry.stock);
                            if (isNaN(stock)) {
                                stock = 0;
                            }
                            console.log(`Product ${numericProductId} stock after retry:`, stock);
                        } else {
                            console.error(`Product ${numericProductId} still not found after ensureProductInIndexedDB`);
                            stock = 0;
                        }
                    }
                }
            } catch (error) {
                console.error('Error getting product stock from IndexedDB:', error);
                stock = 0;
            }
        }

        // Check if Service_Product or Combo_Product - skip stock check (for both direct Service_Product and Service_Product variations)
        const isServiceProduct = productType === 'Service_Product' || isVariationServiceProduct;
        
        // Check stock based on allow_less_sale setting (skip for Service_Product and Combo_Product)
        if (!isServiceProduct && !isComboProduct) {
            const companyData = JSON.parse($('#company_data').val() || '{}');
            const allowLessSale = companyData.allow_less_sale || 'No';
            
            // Convert stock to number and check properly (handle null/undefined)
            stock = (stock !== null && stock !== undefined) ? parseFloat(stock) : 0;
            if (isNaN(stock)) {
                stock = 0;
            }
            
            // For IMEI/Serial products, check if we have enough IMEI/serial numbers
            if (isIMEISerialProduct) {
                if (allowLessSale === 'No' && (stock <= 0 || selectedImeiSerial.length > stock)) {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(`There are not enough ${productType === 'IMEI_Product' ? 'IMEI' : 'Serial Number'} available. Available: ${stock}`);
                    } else {
                        alert(`There are not enough ${productType === 'IMEI_Product' ? 'IMEI' : 'Serial Number'} available. Available: ${stock}`);
                    }
                    return;
                }
            } else {
                // For regular products, check stock quantity
                if (allowLessSale === 'No' && stock <= 0) {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification('There is no stock available.');
                    } else {
                        alert('There is no stock available.');
                    }
                    return;
                }
                
                // Also check if quantity exceeds available stock (if allow_less_sale is 'No')
                if (allowLessSale === 'No' && quantity > stock) {
                    if (typeof showErrorNotification !== 'undefined') {
                        showErrorNotification(`Insufficient stock. Available: ${stock}, Requested: ${quantity}`);
                    } else {
                        alert(`Insufficient stock. Available: ${stock}, Requested: ${quantity}`);
                    }
                    return;
                }
            }
        }

        if (quantity <= 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Quantity must be greater than 0');
            } else {
                alert('Quantity must be greater than 0');
            }
            return;
        }

        if (unitPrice <= 0) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('Price must be greater than 0');
            } else {
                alert('Price must be greater than 0');
            }
            return;
        }

        // Handle discount - if discount exists, format it properly
        let discount = 0;
        if (discountInput && discountInput !== '0' && discountInput !== '') {
            if (discountType === 'percentage') {
                // For percentage, store as "10%" format
                discount = parseFloat(discountInput) || 0;
            } else {
                // For fixed, store as numeric value
                discount = parseFloat(discountInput) || 0;
            }
        }

        // Use variation ID if Variation_Product, otherwise use product ID
        // Ensure both are numbers for IndexedDB compatibility
        const finalProductId = selectedVariationId ? parseInt(selectedVariationId, 10) : productId;

        // Get product/variation for tax info (applicable_tax_id, tax_type, tax_information)
        let taxInformation = [];
        let applicableTaxId = null;
        let itemTaxType = 'Inclusive';
        try {
            const getItem = await posIndexedDB.getProductOrVariationById(finalProductId);
            if (getItem) {
                if (getItem.tax_information) {
                    if (Array.isArray(getItem.tax_information)) {
                        taxInformation = getItem.tax_information;
                    } else if (typeof getItem.tax_information === 'string') {
                        try {
                            taxInformation = JSON.parse(getItem.tax_information) || [];
                        } catch (e) {
                            taxInformation = [];
                        }
                    }
                }
                applicableTaxId = getItem.applicable_tax_id || null;
                itemTaxType = getItem.tax_type || 'Inclusive';
            }
        } catch (error) {
            console.error('Error getting product tax information:', error);
        }

        // Get employee ID for Service_Product (both direct and variation)
        let itemSellerId = null;
        if (isServiceProduct) {
            const selectedEmployeeId = $('#pos_item_info_employee_select').val();
            if (selectedEmployeeId && selectedEmployeeId !== '') {
                itemSellerId = parseInt(selectedEmployeeId);
            }
        }
        
        // Update productType for variation if it's a Service_Product variation
        if (isVariationServiceProduct) {
            productType = 'Service_Product';
        }
        
        // Get promotion data if exists
        const promotion = $('#pos_item_info_product_id').data('promotion');
        
        // Create cart item (include applicable_tax_id, tax_type for new GST logic)
        const cartItem = {
            product_id: finalProductId,
            product_name: productName,
            product_code: productCode,
            product_type: productType,
            quantity: quantity,
            unit_price: unitPrice,
            discount: discount,
            discount_type: discountType,
            total: total,
            tax_information: taxInformation,
            applicable_tax_id: applicableTaxId,
            tax_type: itemTaxType,
            selected_imei_serial: selectedImeiSerial, // Store selected IMEI/serial numbers
            selected_medicine_expiry: selectedMedicineExpiry, // Store selected medicine expiry dates
            item_seller_id: itemSellerId, // Store employee ID for Service_Product
            combo_items: isComboProduct ? comboItemsData : [], // Store combo items data for Combo_Product
            promotion: promotion || null, // Store promotion data
            has_promotion_discount: (promotion && promotion.type === '1') ? true : false // Flag for promotion discount
        };
        
        // Add to cart - use replaceIfExists=true to replace existing item instead of adding quantity
        if (typeof posCartManager !== 'undefined') {
            posCartManager.addItem(cartItem, true);
            
            // Handle Buy X Get Y promotion (Type 3)
            if (promotion && promotion.type === '3' && promotion.get_item_id) {
                await this.handleBuyXGetYPromotion(promotion, quantity, cartItem);
            }
        } else {
            // Fallback: add to cart using basic function
            this.addToCartBasic(cartItem);
        }

        // Close modal
        const itemInfoModalEl = document.getElementById('modal_item_info');
        if (itemInfoModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = bootstrap.Modal.getInstance(itemInfoModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        }

        // Show success notification
        if (typeof showSuccessNotification !== 'undefined') {
            showSuccessNotification(`${productName} added to cart`);
        } else {
            console.log('Cart item added:', cartItem);
        }
    }
    
    /**
     * Handle Buy X Get Y promotion - add free item to cart
     */
    async handleBuyXGetYPromotion(promotion, mainItemQuantity, mainCartItem) {
        try {
            // buy_qty from API; fallback to qty (DB column) for compatibility
            const buyQty = Math.max(1, Number(promotion.buy_qty ?? promotion.qty) || 1);
            const getQty = Math.max(1, Number(promotion.get_qty) || 1);
            const getItemId = promotion.get_item_id;
            
            // Calculate how many free items should be given
            // Example: Buy 5 Get 2, if quantity is 10, then free items = (10 / 5) * 2 = 4
            // If quantity is 9, then free items = Math.floor(9 / 5) * 2 = 1 * 2 = 2
            const freeItemQuantity = Math.floor(mainItemQuantity / buyQty) * getQty;
            
            if (freeItemQuantity <= 0) {
                // Remove only the free item linked to this main item (same structure as General_Product)
                if (typeof posCartManager !== 'undefined' && typeof posCartManager.removeFreeItemLinkedToMainItem === 'function') {
                    posCartManager.removeFreeItemLinkedToMainItem(mainCartItem.product_id);
                }
                return;
            }
            
            // Get free item details from IndexedDB (use getProductOrVariationById for both General_Product and Variation_Product)
            let getItem = typeof posIndexedDB !== 'undefined' && posIndexedDB.isInitialized
                ? await posIndexedDB.getProductOrVariationById(getItemId)
                : null;
            if (!getItem) {
                getItem = {
                    id: getItemId,
                    name: 'Free Item',
                    code: '',
                    type: 'Standard',
                    tax_information: [],
                    tax_string: ''
                };
            }
            
            // Check if free item already exists in cart (linked to this main item)
            if (typeof posCartManager !== 'undefined') {
                const existingFreeItem = posCartManager.cartItems.find(item => 
                    item.product_id == getItemId && 
                    item.is_promotion_free_item === true &&
                    item.promotion_main_item_id == mainCartItem.product_id
                );
                
                if (existingFreeItem) {
                    // Update quantity of existing free item directly (don't use updateItemQuantity as it will be blocked)
                    existingFreeItem.quantity = freeItemQuantity;
                    existingFreeItem.total = 0; // Free items always have 0 total
                    posCartManager.saveCartToStorage();
                    posCartManager.renderCart();
                } else {
                    // Add new free item to cart
                    const freeCartItem = {
                        product_id: getItemId,
                        product_name: getItem.name || 'Free Item',
                        product_code: getItem.code || '',
                        product_type: getItem.type || 'Standard',
                        unit_price: 0, // Free item has no price
                        quantity: freeItemQuantity,
                        discount: 0,
                        discount_type: 'fixed',
                        total: 0, // Free item total is always 0
                        tax_information: getItem.tax_information || [],
                        tax_string: getItem.tax_string || '',
                        applicable_tax_id: getItem.applicable_tax_id || null,
                        tax_type: getItem.tax_type || 'Inclusive',
                        is_promotion_free_item: true, // Mark as promotion free item
                        promotion_id: promotion.id,
                        promotion_main_item_id: mainCartItem.product_id, // Link to main item
                        imei_number: [],
                        medicine: [],
                        combo_items: []
                    };
                    
                    posCartManager.addItem(freeCartItem, true);
                }
            }
        } catch (error) {
            console.error('Error handling Buy X Get Y promotion:', error);
        }
    }
    

    /**
     * Basic add to cart function (fallback if cart manager doesn't exist)
     */
    addToCartBasic(cartItem) {
        // If cart manager exists, use it (it should exist, but just in case)
        if (typeof posCartManager !== 'undefined') {
            posCartManager.addItem(cartItem);
        } else {
            console.error('Cart manager not initialized');
        }
    }
    
    /**
     * Format date for display (DD-MM-YYYY)
     */
    formatDateForDisplay(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        } catch (e) {
            return dateString;
        }
    }
    
    /**
     * Add medicine expiry date to table
     */
    addMedicineExpiryToTable(expiryDate, stockQuantity, quantity = 1) {
        // Check if already added
        const exists = this.selectedMedicineExpiryDates.some(med => med.expiry_date === expiryDate);
        if (exists) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('This expiry date is already added');
            }
            return false;
        }
        
        // Check if quantity exceeds stock
        if (quantity > stockQuantity) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Quantity cannot exceed available stock (${stockQuantity})`);
            }
            return false;
        }
        
        // Add to selected array
        this.selectedMedicineExpiryDates.push({
            expiry_date: expiryDate,
            stock_quantity: stockQuantity,
            quantity: quantity
        });
        
        // Add to table
        const formattedDate = this.formatDateForDisplay(expiryDate);
        const row = $(`
            <tr data-expiry-date="${expiryDate}">
                <td>${formattedDate}</td>
                <td>${stockQuantity}</td>
                <td>
                    <input type="text" class="form-control number-input form-control-sm medicine-quantity-input" 
                           value="${quantity}" min="1" max="${stockQuantity}" data-expiry-date="${expiryDate}">
                </td>
                <td>
                    <button type="button" class="btn btn-sm text-danger remove-medicine-expiry" 
                            data-expiry-date="${expiryDate}">
                        <i class="icon-base ti tabler-trash"></i>
                    </button>
                </td>
            </tr>
        `);
        
        $('#pos_item_info_medicine_table_body').append(row);
        $('#pos_item_info_medicine_table_wrap').show();
        
        // Remove from select dropdown
        $(`#pos_item_info_medicine_expiry_select option[value="${expiryDate}"]`).remove();
        $('#pos_item_info_medicine_expiry_select').val('');
        
        return true;
    }
    
    /**
     * Remove medicine expiry date from table
     */
    removeMedicineExpiryFromTable(expiryDate) {
        // Get stock quantity from the row before removing
        const removedRow = $(`#pos_item_info_medicine_table_body tr[data-expiry-date="${expiryDate}"]`);
        const stockQty = removedRow.find('td').eq(1).text();
        
        // Remove from selected array
        this.selectedMedicineExpiryDates = this.selectedMedicineExpiryDates.filter(
            med => med.expiry_date !== expiryDate
        );
        
        // Remove from table
        removedRow.remove();
        
        // Re-add to select dropdown
        const productId = $('#pos_item_info_product_id').val();
        if (productId && stockQty) {
            const stockQuantity = parseFloat(stockQty) || 0;
            const formattedDate = this.formatDateForDisplay(expiryDate);
            const option = $('<option></option>')
                .attr('value', expiryDate)
                .attr('data-stock', stockQuantity)
                .text(`${formattedDate} (Stock: ${stockQuantity})`);
            $('#pos_item_info_medicine_expiry_select').append(option);
        }
        
        // Hide table if empty
        if (this.selectedMedicineExpiryDates.length === 0) {
            $('#pos_item_info_medicine_table_wrap').hide();
        }
    }
}

// Initialize global instance
const posProductsDisplay = new POSProductsDisplay();

// Initialize when DOM is ready and IndexedDB is ready
$(document).ready(function() {
    // Initialize products display after a short delay to ensure IndexedDB is ready
    // The auto-sync in pos_indexeddb.js starts after 500ms, so we wait a bit longer
    setTimeout(async () => {
        try {
            await posProductsDisplay.init();
        } catch (error) {
            console.error('Failed to initialize POS products display:', error);
            // Show error in UI
            $('#pos-products-grid').html(`
                <div class="pos-error-state">
                    <i class="icon-base ti tabler-alert-circle"></i>
                    <h5 class="mb-2 text-danger">Initialization Error</h5>
                    <p class="text-muted">Failed to load products. Please refresh the page.</p>
                </div>
            `);
        }
    }, 1500); // Wait 1.5 seconds to allow IndexedDB sync to start

    // Modal event handlers for item info
    $(document).on('input change', '#pos_item_info_quantity, #pos_item_info_price, #pos_item_info_discount, #pos_item_info_discount_type', function() {
        posProductsDisplay.calculateItemTotal();
    });

    // Price type radio button change handler
    $(document).on('change', 'input[name="pos_item_info_price_type"]', function() {
        const priceType = $(this).val();
        let priceValue = 0;
        
        if (priceType === 'purchase') {
            priceValue = parseFloat($('#pos_item_info_purchase_price').val()) || 0;
        } else if (priceType === 'last_purchase') {
            priceValue = parseFloat($('#pos_item_info_last_purchase_price').val()) || 0;
        } else if (priceType === 'whole_sale') {
            priceValue = parseFloat($('#pos_item_info_whole_sale_price').val()) || 0;
        } else {
            priceValue = parseFloat($('#pos_item_info_sale_price').val()) || 0;
        }
        
        $('#pos_item_info_price').val(priceValue);
        posProductsDisplay.calculateItemTotal();
    });

    // Variation selection change handler
    $(document).on('change', '#pos_item_info_variation_select', async function() {
        const selectedOption = $(this).find('option:selected');
        if (selectedOption.val()) {
            const variationId = selectedOption.val();
            const salePrice = parseFloat(selectedOption.data('sale-price')) || 0;
            const rawLastThree = parseFloat(selectedOption.attr('data-last-three-purchase-avg')) || 0;
            const rawLastPurchase = parseFloat(selectedOption.attr('data-last-purchase-price')) || 0;
            const wholeSalePrice = parseFloat(selectedOption.data('whole-sale-price')) || 0;
            const stock = parseFloat(selectedOption.data('stock')) || 0;
            const variationPhoto = selectedOption.data('photo') || '';
            const unitType = selectedOption.data('unit-type') || '1';
            const conversionRate = parseFloat(selectedOption.data('conversion-rate')) || 1;
            const saleUnitName = selectedOption.data('sale-unit-name') || 'PCS';
            const stockDisplay = stock > 0 ? `${stock} ${saleUnitName}` : 'N/A';
            
            // Update variation image
            const baseUrl = $('#base_url').val() || window.location.origin;
            let variationImageUrl;
            if (variationPhoto) {
                variationImageUrl = `${baseUrl}/public/uploads/items/${variationPhoto}`;
            } else {
                variationImageUrl = `${baseUrl}/public/uploads/dummy_images/default-picture-pos.png`;
            }
            $('#pos_item_info_product_image').attr('src', variationImageUrl);
            
            const lastThreePurchase = posProductsDisplay.convertPurchasePrice(
                rawLastThree,
                unitType,
                conversionRate
            );
            const lastPurchasePrice = posProductsDisplay.convertPurchasePrice(
                rawLastPurchase,
                unitType,
                conversionRate
            );

            // Update sale unit name
            $('#pos_item_info_sale_unit_display').text(saleUnitName);

            posProductsDisplay.updatePriceFields(lastThreePurchase, lastPurchasePrice, salePrice, wholeSalePrice);
            $('#pos_item_info_stock').text(stockDisplay);
            
            // Ensure Sale Price radio is selected and price field is populated
            $('#pos_item_info_price_type_sale').prop('checked', true);
            $('input[name="pos_item_info_price_type"]').not('#pos_item_info_price_type_sale').prop('checked', false);
            $('#pos_item_info_price').val(salePrice);
            
            // Apply variation-level promotion (from pos_item_info_variation_select option data)
            let variationPromotion = null;
            const promotionRaw = selectedOption.attr('data-promotion');
            if (promotionRaw) {
                try {
                    variationPromotion = JSON.parse(promotionRaw.replace(/&#39;/g, "'"));
                    if (variationPromotion && variationPromotion.discount_type === 'flat') {
                        variationPromotion.discount_type = 'fixed';
                    }
                } catch (e) {
                    console.warn('Could not parse variation promotion:', e);
                }
            }
            $('#pos_item_info_product_id').data('promotion', variationPromotion);
            posProductsDisplay.handlePromotionDisplay({ promotion: variationPromotion });
            
            // Check if variation is Service_Product
            const variationType = selectedOption.data('type') || '';
            const isVariationServiceProduct = variationType === 'Service_Product';
            
            // Handle employee dropdown for Service_Product variation
            if (isVariationServiceProduct) {
                $('#pos_item_info_employee_wrap').show();
                // Hide stock display for Service_Product
                $('#pos_item_info_stock').closest('.stock-wrap').hide();
                // Initialize select2 for employee dropdown if not already initialized
                if (!$('#pos_item_info_employee_select').hasClass('select2-hidden-accessible')) {
                    $('#pos_item_info_employee_select').select2({
                        placeholder: 'Select Employee',
                        allowClear: false,
                        width: '100%',
                        dropdownParent: $('#modal_item_info')
                    });
                }
            } else {
                $('#pos_item_info_employee_wrap').hide();
                $('#pos_item_info_stock').closest('.stock-wrap').show();
            }
            
            // Check if this variation is already in cart and populate fields
            if (typeof posCartManager !== 'undefined') {
                const existingItem = posCartManager.cartItems.find(item => item.product_id == variationId);
                if (existingItem) {
                    $('#pos_item_info_quantity').val(existingItem.quantity);
                    $('#pos_item_info_discount').val(existingItem.discount || 0);
                    $('#pos_item_info_discount_type').val(existingItem.discount_type || 'fixed');
                    if (existingItem.unit_price && existingItem.unit_price !== salePrice) {
                        $('#pos_item_info_price').val(existingItem.unit_price);
                    }
                    // Pre-select employee if Service_Product and already in cart
                    if (isVariationServiceProduct && existingItem.item_seller_id) {
                        $('#pos_item_info_employee_select').val(existingItem.item_seller_id).trigger('change');
                    }
                }
            }
            
            posProductsDisplay.calculateItemTotal();
        } else {
            // Reset to parent product when no variation selected
            const productIdStr = $('#pos_item_info_product_id').val();
            const productId = productIdStr ? parseInt(productIdStr, 10) : null;
            
            // Clear variation promotion when no variation selected
            $('#pos_item_info_product_id').data('promotion', null);
            $('#pos_item_info_promotion_wrap').hide();
            $('#pos_item_info_discount').prop('disabled', false);
            $('#pos_item_info_discount_type').prop('disabled', false);
            
            // Hide employee dropdown when no variation is selected (will be shown if parent is Service_Product)
            $('#pos_item_info_employee_wrap').hide();
            
            // Always get stock from IndexedDB (not from data attribute)
            if (productId && !isNaN(productId)) {
                try {
                    const product = await posIndexedDB.getProductById(productId);
                    if (product) {
                        const productStock = product.stock !== undefined && product.stock !== null ? parseFloat(product.stock) : 0;
                        const stockDisplay = productStock > 0 ? `${productStock} ${product.sale_unit_name || 'PCS'}` : 'N/A';
                        $('#pos_item_info_stock').text(stockDisplay);
                        
                        // Show/hide stock display and employee dropdown based on product type
                        const isParentServiceProduct = product.type === 'Service_Product';
                        if (isParentServiceProduct) {
                            $('#pos_item_info_stock').closest('.stock-wrap').hide();
                            $('#pos_item_info_employee_wrap').show();
                            // Initialize select2 for employee dropdown if not already initialized
                            if (!$('#pos_item_info_employee_select').hasClass('select2-hidden-accessible')) {
                                $('#pos_item_info_employee_select').select2({
                                    placeholder: 'Select Employee',
                                    allowClear: false,
                                    width: '100%',
                                    dropdownParent: $('#modal_item_info')
                                });
                            }
                        } else {
                            $('#pos_item_info_stock').closest('.stock-wrap').show();
                            $('#pos_item_info_employee_wrap').hide();
                        }
                        
                        // Reset image to parent product image
                        const baseUrl = $('#base_url').val() || window.location.origin;
                        const parentImageUrl = product.photo 
                            ? `${baseUrl}/public/uploads/items/${product.photo}`
                            : `${baseUrl}/public/uploads/dummy_images/default-picture-pos.png`;
                        $('#pos_item_info_product_image').attr('src', parentImageUrl);
                        
                        // Reset sale unit name
                        if (product.sale_unit_name) {
                            $('#pos_item_info_sale_unit_display').text(product.sale_unit_name);
                        }
                        
                        const rawLastThree = Number(product.last_three_purchase_avg ?? product.purchase_price ?? 0) || 0;
                        const rawLastPurchase = Number(product.last_purchase_price ?? product.purchase_price ?? 0) || 0;
                        const lastThreePurchase = posProductsDisplay.convertPurchasePrice(
                            rawLastThree,
                            product.unit_type,
                            product.conversion_rate
                        );
                        const lastPurchasePrice = posProductsDisplay.convertPurchasePrice(
                            rawLastPurchase,
                            product.unit_type,
                            product.conversion_rate
                        );

                        posProductsDisplay.updatePriceFields(
                            lastThreePurchase,
                            lastPurchasePrice,
                            product.sale_price || 0,
                            product.whole_sale_price || 0
                        );
                        
                        // Ensure Sale Price is selected and populated
                        $('#pos_item_info_price_type_sale').prop('checked', true);
                        $('input[name="pos_item_info_price_type"]').not('#pos_item_info_price_type_sale').prop('checked', false);
                        $('#pos_item_info_price').val(product.sale_price || 0);
                    }
                } catch (error) {
                    console.error('Error getting product stock:', error);
                }
            }
        }
    });

    // Add to cart button handler
    $(document).on('click', '#pos_item_info_add_to_cart_btn', function(e) {
        e.preventDefault();
        posProductsDisplay.addItemToCartFromModal();
    });
    
    // Medicine expiry date selection handler
    $(document).on('change', '#pos_item_info_medicine_expiry_select', function() {
        const selectedOption = $(this).find('option:selected');
        const expiryDate = selectedOption.val();
        const stockQuantity = parseFloat(selectedOption.data('stock')) || 0;
        
        if (expiryDate && stockQuantity > 0) {
            posProductsDisplay.addMedicineExpiryToTable(expiryDate, stockQuantity, 1);
        }
    });
    
    // Medicine expiry date quantity change handler
    $(document).on('input', '.medicine-quantity-input', function() {
        const expiryDate = $(this).data('expiry-date');
        const quantity = parseFloat($(this).val()) || 1;
        const maxStock = parseFloat($(this).attr('max')) || 0;
        
        if (quantity > maxStock) {
            $(this).val(maxStock);
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification(`Quantity cannot exceed available stock (${maxStock})`);
            }
        }
        
        // Update selected array
        if (posProductsDisplay.selectedMedicineExpiryDates) {
            const medExpiry = posProductsDisplay.selectedMedicineExpiryDates.find(m => m.expiry_date === expiryDate);
            if (medExpiry) {
                medExpiry.quantity = Math.min(quantity, maxStock);
            }
        }
    });
    
    // Medicine expiry date remove handler
    $(document).on('click', '.remove-medicine-expiry', function() {
        const expiryDate = $(this).data('expiry-date');
        posProductsDisplay.removeMedicineExpiryFromTable(expiryDate);
    });
});
