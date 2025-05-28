/**
 * Catalog application class
 * Handles product filtering, sorting and pagination
 */
class CatalogApp {
    /**
     * Initialize catalog application
     */
    constructor() {
        this.filters = {};
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.sortBy = '';
        this.cache = new Map();
        this.isLoading = false;

        this.init();
    }

    /**
     * Initialize application components
     */
    async init() {
        this.setupEventListeners();
        this.setupLoader();
        await this.loadFilters();
        await this.loadProducts();
    }

    /**
     * Setup CSS styles for loader and animations
     */
    setupLoader() {
        const style = document.createElement('style');
        style.textContent = `
        .loader-container {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(255, 255, 255, 0.9);
        }

        .loader {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .products-grid {
            min-height: 400px;
            position: relative;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .product-card {
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }
    `;
        document.head.appendChild(style);
    }

    /**
     * Show loading indicator
     */
    showLoader() {
        if (!this.isLoading) {
            this.isLoading = true;
            const productsContainer = document.getElementById('products');
            productsContainer.innerHTML = `
            <div class="loader-container">
                <div class="loader"></div>
            </div>
        `;
        }
    }

    /**
     * Hide loading indicator
     */
    hideLoader() {
        this.isLoading = false;
    }

    /**
     * Setup event listeners for sorting and pagination
     */
    setupEventListeners() {
        const sortSelect = document.getElementById('sort');
        sortSelect.addEventListener('change', this.debounce((e) => {
            this.sortBy = e.target.value;
            this.currentPage = 1;
            this.loadProducts();
        }, 300));

        document.getElementById('prevPage').addEventListener('click', () => {
            if (this.currentPage > 1 && !this.isLoading) {
                this.currentPage--;
                this.loadProducts();
            }
        });

        document.getElementById('nextPage').addEventListener('click', () => {
            if (!this.isLoading) {
                this.currentPage++;
                this.loadProducts();
            }
        });
    }

    /**
     * Debounce function to limit rate of execution
     * @param {Function} func Function to debounce
     * @param {number} wait Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Generate cache key for requests
     * @param {string} type Request type ('filters' or 'products')
     * @param {Object} params Request parameters
     * @returns {string} Cache key
     */
    getCacheKey(type, params) {
        return `${type}:${JSON.stringify(params)}`;
    }

    /**
     * Load available filters from API
     */
    async loadFilters() {
        try {
            const cacheKey = this.getCacheKey('filters', this.filters);
            const cachedFilters = this.cache.get(cacheKey);

            if (cachedFilters) {
                this.renderFilters(cachedFilters);
                return;
            }

            const queryParams = new URLSearchParams();
            for (const [param, values] of Object.entries(this.filters)) {
                if (Array.isArray(values)) {
                    values.forEach(value => queryParams.append(`filter[${param}][]`, value));
                } else {
                    queryParams.append(`filter[${param}]`, values);
                }
            }

            const response = await fetch(`/api/catalog/filters?${queryParams}`);
            const filters = await response.json();

            this.cache.set(cacheKey, filters);
            this.renderFilters(filters);
        } catch (error) {
            console.error('Error loading filters:', error);
            this.showError('Помилка завантаження фільтрів');
        }
    }

    /**
     * Render filter options
     * @param {Array} filters Filter data from API
     */
    renderFilters(filters) {
        const filtersContainer = document.getElementById('filters');
        filtersContainer.innerHTML = '';

        filters.forEach(filter => {
            const filterGroup = document.createElement('div');
            filterGroup.className = 'filter-group';

            const activeValues = filter.values.filter(value => value.active);
            const hasActiveValues = activeValues.length > 0;

            const visibleValues = filter.values.filter(value =>
                value.active ||
                (value.count > 0 && !hasActiveValues)
            );

            if (visibleValues.length > 0) {
                filterGroup.innerHTML = `
                <h3>
                    ${filter.name}
                    ${hasActiveValues ? `<span class="active-count">(Вибрано: ${activeValues.length})</span>` : ''}
                </h3>
                <div class="filter-values">
                    ${visibleValues.map(value => `
                        <div class="filter-option ${value.count === 0 ? 'disabled' : ''} ${value.active ? 'active' : ''}"
                             data-has-items="${value.count > 0}">
                            <input type="checkbox" 
                                id="${filter.slug}_${value.value}"
                                data-slug="${filter.slug}"
                                data-value="${value.value}"
                                ${value.active ? 'checked' : ''}
                                ${value.count === 0 && !value.active ? 'disabled' : ''}
                            >
                            <label for="${filter.slug}_${value.value}">
                                <span class="filter-label">${value.value}</span>
                                <span class="filter-count">${value.count}</span>
                            </label>
                        </div>
                    `).join('')}
                </div>
            `;

                filterGroup.addEventListener('change', this.debounce((e) => {
                    if (e.target.tagName === 'INPUT' && !this.isLoading) {
                        const { slug, value } = e.target.dataset;

                        if (!this.filters[slug]) {
                            this.filters[slug] = [];
                        }

                        if (e.target.checked) {
                            const otherCheckboxes = filterGroup.querySelectorAll('input[type="checkbox"]');
                            otherCheckboxes.forEach(checkbox => {
                                if (checkbox !== e.target) {
                                    checkbox.checked = false;
                                }
                            });

                            this.filters[slug] = [value];
                        } else {
                            this.filters[slug] = this.filters[slug].filter(v => v !== value);
                            if (this.filters[slug].length === 0) {
                                delete this.filters[slug];
                            }
                        }

                        this.currentPage = 1;
                        this.loadFilters();
                        this.loadProducts();
                    }
                }, 300));

                filtersContainer.appendChild(filterGroup);
            }
        });
    }

    /**
     * Load products from API
     */
    async loadProducts() {
        if (this.isLoading) return;

        this.showLoader();
        try {
            const queryParams = new URLSearchParams({
                page: this.currentPage,
                limit: this.itemsPerPage,
                ...(this.sortBy && { sort_by: this.sortBy })
            });

            for (const [param, values] of Object.entries(this.filters)) {
                if (Array.isArray(values)) {
                    values.forEach(value => queryParams.append(`filter[${param}][]`, value));
                } else {
                    queryParams.append(`filter[${param}]`, values);
                }
            }

            const cacheKey = this.getCacheKey('products', queryParams.toString());
            const cachedData = this.cache.get(cacheKey);

            if (cachedData) {
                this.renderProducts(cachedData);
                return;
            }

            const response = await fetch(`/api/catalog/products?${queryParams}`);
            const data = await response.json();

            this.cache.set(cacheKey, data);
            this.renderProducts(data);
        } catch (error) {
            console.error('Error loading products:', error);
            this.showError('Помилка завантаження товарів');
        } finally {
            this.hideLoader();
        }
    }

    /**
     * Render product grid
     * @param {Object} response API response with products and metadata
     */
    renderProducts(response) {
        const { data: products, meta } = response;
        const productsContainer = document.getElementById('products');

        if (products.length === 0) {
            productsContainer.innerHTML = `
                <div class="empty-message">
                    Товари не знайдено
                </div>
            `;
        } else {
            productsContainer.innerHTML = products.map(product => `
                <div class="product-card">
                    <h3>${product.name}</h3>
                    <div class="price">${this.formatPrice(product.price)} грн</div>
                    <p>${product.description || ''}</p>
                </div>
            `).join('');
        }

        document.getElementById('prevPage').disabled = this.currentPage === 1;
        document.getElementById('nextPage').disabled = this.currentPage === meta.last_page;
        document.getElementById('currentPage').textContent =
            `Сторінка ${this.currentPage} з ${meta.last_page}`;
    }

    /**
     * Format price with Ukrainian locale
     * @param {number} price Price to format
     * @returns {string} Formatted price
     */
    formatPrice(price) {
        return new Intl.NumberFormat('uk-UA').format(price);
    }

    /**
     * Show error message
     * @param {string} message Error message to display
     */
    showError(message) {
        const productsContainer = document.getElementById('products');
        productsContainer.innerHTML = `
            <div class="empty-message">
                ${message}
            </div>
        `;
    }
}

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new CatalogApp();
});