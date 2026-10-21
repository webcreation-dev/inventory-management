/**
 * POS IndexedDB Manager
 * Consolidated IndexedDB management for POS products
 * Database Structure:
 *   - Database: POS_Database
 *   - Store: products (primary key: id)
 *   - Store: imei_serial_numbers (primary key: imei_serial; value: { imei_serial, item_id, item_type })
 *   - Store: customers (primary key: id; negative ids = offline-only until synced)
 *   - Store: pending_customers (auto-increment; unsynced POS customer creates)
 *   - Store: sales (offline sales queue)
 */

class POSIndexedDB {
    constructor() {
        this.dbName = 'POS_Database';
        this.dbVersion = 6;
        this.storeName = 'products';
        this.imeiSerialStoreName = 'imei_serial_numbers';
        this.salesStoreName = 'sales';
        this.customersStoreName = 'customers';
        this.pendingCustomersStoreName = 'pending_customers';
        this.db = null;
        this.isInitialized = false;
        this.syncInProgress = false;
    }

    /**
     * Initialize IndexedDB
     * Creates database with products store using id as primary key
     */
    async init() {
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                reject(new Error('IndexedDB is not supported in this browser'));
                return;
            }
            const request = indexedDB.open(this.dbName, this.dbVersion);
            request.onerror = () => {
                reject(new Error('Failed to open IndexedDB'));
            };
            request.onsuccess = (event) => {
                this.db = event.target.result;
                this.isInitialized = true;
                resolve(this.db);
            };
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                const oldVersion = event.oldVersion;
                // Create products store with id as primary key
                if (!db.objectStoreNames.contains(this.storeName)) {
                    db.createObjectStore(this.storeName, { keyPath: 'id' });
                }
                // Create sales store for offline sales with auto-increment key
                if (!db.objectStoreNames.contains(this.salesStoreName)) {
                    db.createObjectStore(this.salesStoreName, { keyPath: 'id', autoIncrement: true });
                }
                if (!db.objectStoreNames.contains(this.customersStoreName)) {
                    db.createObjectStore(this.customersStoreName, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(this.pendingCustomersStoreName)) {
                    db.createObjectStore(this.pendingCustomersStoreName, { keyPath: 'id', autoIncrement: true });
                }
                // v6: imei_serial as sole keyPath (like customers id); no indexes
                if (oldVersion < 6 && db.objectStoreNames.contains(this.imeiSerialStoreName)) {
                    db.deleteObjectStore(this.imeiSerialStoreName);
                }
                if (!db.objectStoreNames.contains(this.imeiSerialStoreName)) {
                    db.createObjectStore(this.imeiSerialStoreName, { keyPath: 'imei_serial' });
                }
            };
        });
    }

    /**
     * Build flat IMEI/serial rows from a product payload (parent + variations).
     * @param {Object} product
     * @returns {Array<{item_id: number, imei_serial: string, item_type: string}>}
     */
    _collectImeiRowsFromProduct(product) {
        const rows = [];
        const addRows = (itemId, itemType, imeiList) => {
            if (itemType !== 'IMEI_Product' && itemType !== 'Serial_Product') {
                return;
            }
            if (!Array.isArray(imeiList)) {
                return;
            }
            const idNum = Number(itemId);
            if (Number.isNaN(idNum)) {
                return;
            }
            imeiList.forEach((raw) => {
                if (raw === null || raw === undefined) {
                    return;
                }
                const imei_serial = String(raw).trim();
                if (!imei_serial) {
                    return;
                }
                rows.push({
                    item_id: idNum,
                    imei_serial,
                    item_type: itemType,
                });
            });
        };
        if (product && product.id != null) {
            addRows(product.id, product.type, product.imei_number);
        }
        if (product && product.variations && Array.isArray(product.variations)) {
            product.variations.forEach((v) => {
                if (!v || v.id == null) {
                    return;
                }
                const vType = v.type || product.type;
                addRows(v.id, vType, v.imei_number);
            });
        }
        return rows;
    }

    /**
     * Store products in IndexedDB
     * Each product uses its id as the primary key
     */
    async storeProducts(products) {
        if (!products || products.length === 0) {
            return { success: 0, errors: 0 };
        }
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(
                [this.storeName, this.imeiSerialStoreName],
                'readwrite'
            );
            const productStore = transaction.objectStore(this.storeName);
            const imeiStore = transaction.objectStore(this.imeiSerialStoreName);

            let successCount = 0;
            let errorCount = 0;
            let current = 0;

            const advanceWithError = () => {
                errorCount++;
                current++;
                processNext();
            };

            const clearImeiRowsForItemIds = (idSet, onDone) => {
                const req = imeiStore.openCursor();
                req.onsuccess = (ev) => {
                    const cursor = ev.target.result;
                    if (cursor) {
                        const v = cursor.value;
                        if (v && v.item_id != null && idSet.has(Number(v.item_id))) {
                            cursor.delete();
                        }
                        cursor.continue();
                    } else {
                        onDone();
                    }
                };
                req.onerror = () => advanceWithError();
            };

            const putImeiRowsSequentially = (rows, i, onDone) => {
                if (i >= rows.length) {
                    onDone();
                    return;
                }
                const putReq = imeiStore.put(rows[i]);
                putReq.onsuccess = () => putImeiRowsSequentially(rows, i + 1, onDone);
                putReq.onerror = () => advanceWithError();
            };

            const processNext = () => {
                if (current >= products.length) {
                    return;
                }
                const product = products[current];
                const idSet = new Set();
                if (product.id != null && !Number.isNaN(Number(product.id))) {
                    idSet.add(Number(product.id));
                }
                if (product.variations && Array.isArray(product.variations)) {
                    product.variations.forEach((v) => {
                        if (v && v.id != null && !Number.isNaN(Number(v.id))) {
                            idSet.add(Number(v.id));
                        }
                    });
                }

                clearImeiRowsForItemIds(idSet, () => {
                    const putReq = productStore.put(product);
                    putReq.onerror = () => advanceWithError();
                    putReq.onsuccess = () => {
                        const rows = this._collectImeiRowsFromProduct(product);
                        putImeiRowsSequentially(rows, 0, () => {
                            successCount++;
                            current++;
                            processNext();
                        });
                    };
                });
            };

            transaction.oncomplete = () => {
                resolve({ success: successCount, errors: errorCount });
            };
            transaction.onerror = () => {
                reject(transaction.error || new Error('storeProducts transaction failed'));
            };

            processNext();
        });
    }

    /**
     * All available IMEI/serial strings for a sellable item (product or variation id).
     * @param {number|string} itemId
     * @returns {Promise<string[]>}
     */
    async getAvailableImeiSerialsForItem(itemId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        const id = parseInt(itemId, 10);
        if (Number.isNaN(id)) {
            return [];
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.imeiSerialStoreName], 'readonly');
            const store = transaction.objectStore(this.imeiSerialStoreName);
            const list = [];
            const request = store.openCursor();
            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    const v = cursor.value;
                    if (v && Number(v.item_id) === id && v.imei_serial) {
                        list.push(String(v.imei_serial).trim());
                    }
                    cursor.continue();
                } else {
                    resolve(list);
                }
            };
            request.onerror = () => {
                reject(new Error('Failed to read imei_serial_numbers from IndexedDB'));
            };
        });
    }

    /**
     * Remove IMEI/serial records by primary key (imei_serial string).
     * @param {number|string} _itemId - unused; kept for caller compatibility
     * @param {Array<string>} imeiSerialNumbers
     */
    async deleteImeiSerialRecordsForItem(_itemId, imeiSerialNumbers) {
        if (!imeiSerialNumbers || !imeiSerialNumbers.length) {
            return true;
        }
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        const keys = imeiSerialNumbers
            .map((s) => String(s).trim())
            .filter((s) => s !== '');
        if (!keys.length) {
            return true;
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.imeiSerialStoreName], 'readwrite');
            const store = transaction.objectStore(this.imeiSerialStoreName);
            keys.forEach((key) => {
                store.delete(key);
            });
            transaction.oncomplete = () => {
                resolve(true);
            };
            transaction.onerror = () => {
                reject(new Error('Failed to delete imei_serial_numbers from IndexedDB'));
            };
        });
    }

    /**
     * Get one IMEI/serial row by primary key (imei_serial string).
     * @param {string} imeiSerial
     * @returns {Promise<{imei_serial: string, item_id: number, item_type: string}|null>}
     */
    async getImeiSerialRecord(imeiSerial) {
        const key = String(imeiSerial || '').trim();
        if (!key) {
            return null;
        }
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.imeiSerialStoreName], 'readonly');
            const store = transaction.objectStore(this.imeiSerialStoreName);
            const request = store.get(key);
            request.onsuccess = () => {
                resolve(request.result || null);
            };
            request.onerror = () => {
                reject(new Error('Failed to read imei_serial_numbers from IndexedDB'));
            };
        });
    }

    /**
     * Resolve parent product row and optional variation for a sellable item id (product or variation id).
     * @param {number|string} sellableId
     * @returns {Promise<{parent: Object, variation: Object|null}|null>}
     */
    async findParentProductAndVariationForSellableId(sellableId) {
        const id = parseInt(sellableId, 10);
        if (Number.isNaN(id)) {
            return null;
        }
        try {
            const top = await this.getProductById(id);
            if (top) {
                return { parent: top, variation: null };
            }
            const all = await this.getAllProducts();
            for (let i = 0; i < all.length; i++) {
                const p = all[i];
                if (!p || !p.variations || !Array.isArray(p.variations)) {
                    continue;
                }
                const v = p.variations.find(
                    (vv) => vv && Number(vv.id) === id
                );
                if (v) {
                    return { parent: p, variation: v };
                }
            }
            return null;
        } catch (e) {
            console.error('findParentProductAndVariationForSellableId:', e);
            return null;
        }
    }

    /**
     * Get all products from IndexedDB
     */
    async getAllProducts() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();

            request.onsuccess = () => {
                resolve(request.result);
            };
            request.onerror = () => {
                reject(new Error('Failed to get products from IndexedDB'));
            };
        });
    }

    /**
     * Get product by ID (primary key)
     */
    async getProductById(id) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(id);
            request.onsuccess = () => {
                resolve(request.result);
            };
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }

    /**
     * Get product or variation by ID.
     * For General_Product etc.: returns product directly (same as getProductById).
     * For Variation_Product: variations are stored in parent's variations array - searches and returns the variation.
     * @param {number} id - Product or variation ID
     * @returns {Promise<Object|null>} Product/variation object or null
     */
    async getProductOrVariationById(id) {
        const numericId = parseInt(id, 10);
        if (isNaN(numericId)) return null;
        try {
            const product = await this.getProductById(numericId);
            if (product) {
                return product;
            }
            const allProducts = await this.getAllProducts();
            for (const p of allProducts) {
                if (p.variations && Array.isArray(p.variations)) {
                    const variation = p.variations.find(v => v && (v.id == numericId || v.id === numericId));
                    if (variation) {
                        const parentName = p.name || '';
                        const variationName = variation.name || '';
                        const displayName = parentName && variationName ? `${parentName} - ${variationName}` : (variationName || parentName || 'Free Item');
                        return {
                            id: variation.id,
                            name: displayName,
                            code: variation.code,
                            type: variation.type || 'Standard',
                            tax_information: variation.tax_information || p.tax_information || [],
                            tax_string: variation.tax_string || p.tax_string || '',
                            applicable_tax_id: variation.applicable_tax_id ?? p.applicable_tax_id ?? null,
                            tax_type: variation.tax_type || p.tax_type || 'Inclusive'
                        };
                    }
                }
            }
            return null;
        } catch (error) {
            console.error('Error in getProductOrVariationById:', error);
            return null;
        }
    }

    /**
     * Search products by name, code, brand, category, or generic_name
     */
    async searchProducts(searchTerm) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result;
                const searchLower = searchTerm.toLowerCase();
                const filtered = products.filter((product) => {
                    return (
                        (product.name && product.name.toLowerCase().includes(searchLower)) ||
                        (product.code && product.code.toLowerCase().includes(searchLower)) ||
                        (product.alternative_name && product.alternative_name.toLowerCase().includes(searchLower)) ||
                        (product.generic_name && product.generic_name.toLowerCase().includes(searchLower)) ||
                        (product.brand_name && product.brand_name.toLowerCase().includes(searchLower)) ||
                        (product.category_name && product.category_name.toLowerCase().includes(searchLower))
                    );
                });
                resolve(filtered);
            };
            request.onerror = () => {
                reject(new Error('Failed to search products in IndexedDB'));
            };
        });
    }

    /**
     * Get products by category
     */
    async getProductsByCategory(categoryId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result;
                // Compare as String to avoid type-mismatch (number vs string) from different server PHP/MySQL setups
                const catIdStr = String(categoryId);
                const filtered = products.filter((product) => {
                    return product.category_id != null && String(product.category_id) === catIdStr;
                });
                resolve(filtered);
            };
            request.onerror = () => {
                reject(new Error('Failed to get products by category from IndexedDB'));
            };
        });
    }

    /**
     * Get products by generic name (for medicine alternatives)
     */
    async getProductsByGenericName(genericName) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result;
                const filtered = products.filter((product) => {
                    return product.generic_name && product.generic_name.toLowerCase() === genericName.toLowerCase();
                });
                resolve(filtered);
            };
            request.onerror = () => {
                reject(new Error('Failed to get products by generic name from IndexedDB'));
            };
        });
    }

    /**
     * Clear all products from IndexedDB
     */
    async clearAllProducts() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(
                [this.storeName, this.imeiSerialStoreName],
                'readwrite'
            );
            transaction.oncomplete = () => {
                resolve(true);
            };
            transaction.onerror = () => {
                reject(new Error('Failed to clear products or imei_serial_numbers from IndexedDB'));
            };
            transaction.objectStore(this.storeName).clear();
            transaction.objectStore(this.imeiSerialStoreName).clear();
        });
    }

    /**
     * Get count of products in IndexedDB
     */
    async getProductCount() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.count();
            request.onsuccess = () => {
                resolve(request.result);
            };
            request.onerror = () => {
                reject(new Error('Failed to get product count from IndexedDB'));
            };
        });
    }

    // --- Customers (offline cache + pending sync) ---

    async storeCustomers(customers) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        if (!customers || !customers.length) {
            return { success: 0, errors: 0 };
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.customersStoreName], 'readwrite');
            const store = transaction.objectStore(this.customersStoreName);
            let successCount = 0;
            let errorCount = 0;
            const done = () => {
                if (successCount + errorCount === customers.length) {
                    resolve({ success: successCount, errors: errorCount });
                }
            };
            customers.forEach((customer) => {
                const request = store.put(customer);
                request.onsuccess = () => {
                    successCount++;
                    done();
                };
                request.onerror = () => {
                    errorCount++;
                    done();
                };
            });
        });
    }

    async getAllCustomers() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.customersStoreName], 'readonly');
            const store = transaction.objectStore(this.customersStoreName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(new Error('Failed to get customers from IndexedDB'));
        });
    }

    async getCustomerById(id) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        const numericId = parseInt(id, 10);
        if (isNaN(numericId)) return null;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.customersStoreName], 'readonly');
            const store = transaction.objectStore(this.customersStoreName);
            const request = store.get(numericId);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(new Error('Failed to get customer from IndexedDB'));
        });
    }

    async deleteCustomerById(id) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        const numericId = parseInt(id, 10);
        if (isNaN(numericId)) return false;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.customersStoreName], 'readwrite');
            const store = transaction.objectStore(this.customersStoreName);
            const request = store.delete(numericId);
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(new Error('Failed to delete customer from IndexedDB'));
        });
    }

    /**
     * Sort like POS API: Walk-in first, then newest id first; used for empty search (first 50).
     */
    sortCustomersPosStyle(list) {
        return [...list].sort((a, b) => {
            const aw = a.name === 'Walk-in Customer' ? 0 : 1;
            const bw = b.name === 'Walk-in Customer' ? 0 : 1;
            if (aw !== bw) return aw - bw;
            return (b.id || 0) - (a.id || 0);
        });
    }

    async searchCustomers(searchTerm) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        const all = await this.getAllCustomers();
        const term = (searchTerm || '').trim().toLowerCase();
        if (!term) {
            return this.sortCustomersPosStyle(all).slice(0, 50);
        }
        const filtered = all.filter((c) => {
            return (
                (c.name && c.name.toLowerCase().includes(term)) ||
                (c.phone && String(c.phone).toLowerCase().includes(term)) ||
                (c.email && String(c.email).toLowerCase().includes(term))
            );
        });
        return this.sortCustomersPosStyle(filtered).slice(0, 50);
    }

    /**
     * Select2 options: search + optional include_id (matches server behaviour).
     */
    async getCustomersForSelect2({ search, includeId }) {
        const term = (search || '').trim();
        let list = await this.searchCustomers(term);
        const byId = {};
        list.forEach((c) => {
            byId[c.id] = c;
        });
        if (includeId) {
            const inc = parseInt(includeId, 10);
            if (!isNaN(inc) && !byId[inc]) {
                const one = await this.getCustomerById(inc);
                if (one) {
                    list = [one, ...list.filter((c) => c.id !== one.id)];
                }
            }
        }
        return list.map((c) => ({ id: c.id, text: c.name }));
    }

    async fetchCustomersBatch(page, perPage = 500) {
        const baseUrl = $('#base_url').val() || window.location.origin;
        const url = `${baseUrl}/pos/customers/batch?page=${page}&per_page=${perPage}`;
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching customers batch:', error);
            return { status: 'error', message: error.message };
        }
    }

    async preserveAndClearCustomersForResync() {
        const all = await this.getAllCustomers();
        const locals = all.filter((c) => typeof c.id === 'number' && c.id < 0);
        return new Promise((resolve, reject) => {
            if (!this.isInitialized || !this.db) {
                this.init()
                    .then(() => this.preserveAndClearCustomersForResync())
                    .then(resolve)
                    .catch(reject);
                return;
            }
            const transaction = this.db.transaction([this.customersStoreName], 'readwrite');
            const store = transaction.objectStore(this.customersStoreName);
            const clearReq = store.clear();
            clearReq.onerror = () => reject(new Error('Failed to clear customers'));
            clearReq.onsuccess = () => {
                if (!locals.length) {
                    resolve([]);
                    return;
                }
                let done = 0;
                let failed = false;
                locals.forEach((c) => {
                    const putReq = store.put(c);
                    putReq.onsuccess = () => {
                        done++;
                        if (done === locals.length && !failed) resolve(locals);
                    };
                    putReq.onerror = () => {
                        failed = true;
                        reject(new Error('Failed to restore local customers'));
                    };
                });
            };
        });
    }

    async fetchAndStoreAllCustomers(progressCallback = null, perPage = 500) {
        let currentPage = 1;
        let totalPages = 1;
        let totalStored = 0;
        let totalCustomers = 0;
        let hasError = false;
        let errorMessage = '';
        try {
            await this.preserveAndClearCustomersForResync();
            const firstResponse = await this.fetchCustomersBatch(1, perPage);
            if (firstResponse.status === 'success') {
                totalPages = Math.max(1, firstResponse.pagination.total_pages);
                totalCustomers = firstResponse.pagination.total || 0;
                const storeResult = await this.storeCustomers(firstResponse.data || []);
                totalStored += storeResult.success;
                if (progressCallback) {
                    progressCallback({
                        currentPage: 1,
                        totalPages,
                        stored: totalStored,
                        total: totalCustomers,
                    });
                }
                for (currentPage = 2; currentPage <= totalPages; currentPage++) {
                    try {
                        const response = await this.fetchCustomersBatch(currentPage, perPage);
                        if (response.status === 'success') {
                            const sr = await this.storeCustomers(response.data || []);
                            totalStored += sr.success;
                            if (progressCallback) {
                                progressCallback({
                                    currentPage,
                                    totalPages,
                                    stored: totalStored,
                                    total: totalCustomers,
                                });
                            }
                            await this.delay(50);
                        } else {
                            hasError = true;
                            errorMessage = response.message || 'Failed to fetch customers';
                            break;
                        }
                    } catch (error) {
                        hasError = true;
                        errorMessage = error.message || 'Error fetching customers';
                        break;
                    }
                }
                return {
                    success: !hasError,
                    totalStored,
                    totalPages,
                    error: hasError ? errorMessage : null,
                };
            }
            return {
                success: false,
                totalStored: 0,
                totalPages: 0,
                error: firstResponse.message || 'Failed to fetch customers',
            };
        } catch (error) {
            return {
                success: false,
                totalStored,
                totalPages,
                error: error.message || 'Unknown error',
            };
        }
    }

    allocateLocalCustomerId() {
        const k = 'pos_local_customer_id_seq';
        try {
            let n = parseInt(localStorage.getItem(k) || '0', 10);
            if (isNaN(n)) n = 0;
            n -= 1;
            localStorage.setItem(k, String(n));
            return n;
        } catch (e) {
            return -Math.floor(Date.now() / 1000);
        }
    }

    async savePendingCustomerRecord({ localCustomerId, formBody, meta }) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.pendingCustomersStoreName], 'readwrite');
            const store = transaction.objectStore(this.pendingCustomersStoreName);
            const record = {
                local_customer_id: localCustomerId,
                form_body: formBody,
                meta: meta || {},
                synced: false,
                created_at: new Date().toISOString(),
            };
            const request = store.add(record);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(new Error('Failed to save pending customer'));
        });
    }

    async getUnsyncedPendingCustomers() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.pendingCustomersStoreName], 'readonly');
            const store = transaction.objectStore(this.pendingCustomersStoreName);
            const request = store.getAll();
            request.onsuccess = () => {
                const rows = request.result || [];
                resolve(rows.filter((r) => !r.synced));
            };
            request.onerror = () => reject(new Error('Failed to read pending customers'));
        });
    }

    async markPendingCustomerSynced(pendingRowId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.pendingCustomersStoreName], 'readwrite');
            const store = transaction.objectStore(this.pendingCustomersStoreName);
            const request = store.get(pendingRowId);
            request.onsuccess = () => {
                const row = request.result;
                if (!row) {
                    resolve(false);
                    return;
                }
                row.synced = true;
                row.synced_at = new Date().toISOString();
                const putReq = store.put(row);
                putReq.onsuccess = () => resolve(true);
                putReq.onerror = () => reject(new Error('Failed to mark pending customer synced'));
            };
            request.onerror = () => reject(new Error('Failed to get pending customer'));
        });
    }

    async deletePendingCustomerRecord(pendingRowId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.pendingCustomersStoreName], 'readwrite');
            const store = transaction.objectStore(this.pendingCustomersStoreName);
            const request = store.delete(pendingRowId);
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(new Error('Failed to delete pending customer'));
        });
    }

    /**
     * Remap customer_id in unsynced offline sales after a local customer receives a server id.
     */
    async remapOfflineSalesCustomerId(localId, serverId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        const unsynced = await this.getUnsyncedSales();
        for (const sale of unsynced) {
            const cid = sale.sale_detail && sale.sale_detail.customer_id;
            if (cid != null && parseInt(cid, 10) === parseInt(localId, 10)) {
                sale.sale_detail.customer_id = parseInt(serverId, 10);
                await new Promise((resolve, reject) => {
                    const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
                    const store = transaction.objectStore(this.salesStoreName);
                    const putReq = store.put(sale);
                    putReq.onsuccess = () => resolve();
                    putReq.onerror = () => reject(new Error('Failed to update sale customer id'));
                });
            }
        }
    }

    /**
     * POST pending customers to server (call while online, before syncing offline sales).
     */
    async syncPendingCustomers() {
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            return { success: 0, errors: 0, total: 0, message: 'Offline' };
        }
        const baseUrl = $('#base_url').val() || window.location.origin;
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const pending = await this.getUnsyncedPendingCustomers();
        if (!pending.length) {
            return { success: 0, errors: 0, total: 0 };
        }
        let successCount = 0;
        let errorCount = 0;
        const errors = [];

        for (const row of pending) {
            const localId = row.local_customer_id;
            try {
                const response = await fetch(`${baseUrl}/pos/customer`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: row.form_body,
                });
                const result = await response.json().catch(() => ({}));
                if (response.ok && result.status === 'success' && result.customer && result.customer.id) {
                    const serverId = result.customer.id;
                    await this.deleteCustomerById(localId);
                    const stateId =
                        row.meta && row.meta.state_id !== undefined && row.meta.state_id !== ''
                            ? row.meta.state_id
                            : null;
                    await this.storeCustomers([
                        {
                            id: serverId,
                            name: result.customer.name,
                            phone: result.customer.phone,
                            email: result.customer.email || '',
                            state_id: stateId,
                            encrypted_id: result.customer.encrypted_id || null,
                        },
                    ]);
                    await this.remapOfflineSalesCustomerId(localId, serverId);
                    if (typeof window.upsertPosCustomerState === 'function') {
                        window.upsertPosCustomerState(serverId, stateId);
                    }
                    const $sel = $('#customer-select');
                    if ($sel.length && String($sel.val()) === String(localId)) {
                        $sel.val(String(serverId)).trigger('change');
                    }
                    await this.deletePendingCustomerRecord(row.id);
                    successCount++;
                } else {
                    errorCount++;
                    let reason = result.message || 'Create failed';
                    if (result.errors && typeof result.errors === 'object') {
                        const firstField = Object.keys(result.errors)[0];
                        const firstMsg =
                            firstField && Array.isArray(result.errors[firstField]) && result.errors[firstField].length
                                ? result.errors[firstField][0]
                                : null;
                        if (firstMsg) {
                            reason = `${reason} (${firstMsg})`;
                        }
                    }
                    errors.push({ pendingId: row.id, localCustomerId: localId, error: reason });
                }
            } catch (e) {
                errorCount++;
                errors.push({ pendingId: row.id, localCustomerId: localId, error: e.message || 'Network error' });
            }
        }

        return {
            success: successCount,
            errors: errorCount,
            total: pending.length,
            errorDetails: errors,
        };
    }

    /**
     * Fetch products in batches from server and store in IndexedDB.
     * Fetches every page until all products are stored (scalable to 50k+).
     * @param {Function|null} progressCallback - optional callback({ currentPage, totalPages, stored, total })
     * @param {number} perPage - products per batch (100-1000, default 500)
     */
    async fetchAndStoreAllProducts(progressCallback = null, perPage = 500) {
        let currentPage = 1;
        let totalPages = 1;
        let totalStored = 0;
        let totalProducts = 0;
        let hasError = false;
        let errorMessage = '';
        try {
            await this.clearAllProducts();
            const firstResponse = await this.fetchProductsBatch(1, perPage);
            if (firstResponse.status === 'success') {
                totalPages = Math.max(1, firstResponse.pagination.total_pages);
                totalProducts = firstResponse.pagination.total || 0;
                const storeResult = await this.storeProducts(firstResponse.data);
                totalStored += storeResult.success;
                if (progressCallback) {
                    progressCallback({
                        currentPage: 1,
                        totalPages: totalPages,
                        stored: totalStored,
                        total: totalProducts
                    });
                }
                for (currentPage = 2; currentPage <= totalPages; currentPage++) {
                    try {
                        const response = await this.fetchProductsBatch(currentPage, perPage);
                        if (response.status === 'success') {
                            const storeResult = await this.storeProducts(response.data);
                            totalStored += storeResult.success;
                            if (progressCallback) {
                                progressCallback({
                                    currentPage: currentPage,
                                    totalPages: totalPages,
                                    stored: totalStored,
                                    total: totalProducts
                                });
                            }
                            await this.delay(100);
                        } else {
                            hasError = true;
                            errorMessage = response.message || 'Failed to fetch products';
                            break;
                        }
                    } catch (error) {
                        hasError = true;
                        errorMessage = error.message || 'Error fetching products';
                        break;
                    }
                }
                return {
                    success: !hasError,
                    totalStored: totalStored,
                    totalPages: totalPages,
                    error: hasError ? errorMessage : null
                };
            } else {
                return {
                    success: false,
                    totalStored: 0,
                    totalPages: 0,
                    error: firstResponse.message || 'Failed to fetch products'
                };
            }
        } catch (error) {
            return {
                success: false,
                totalStored: totalStored,
                totalPages: totalPages,
                error: error.message || 'Unknown error occurred'
            };
        }
    }

    /**
     * Fetch a single batch of products from server.
     * Uses per_page (default 500) so all products sync efficiently (e.g. 50k = 100 requests).
     */
    async fetchProductsBatch(page, perPage = 500) {
        const baseUrl = $('#base_url').val() || window.location.origin;
        const url = `${baseUrl}/pos/products/batch?page=${page}&per_page=${perPage}`;
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('Error fetching products batch:', error);
            return {
                status: 'error',
                message: error.message
            };
        }
    }

    /**
     * Utility function to add delay
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Start product sync with UI updates
     */
    async startSync() {
        if (this.syncInProgress) {
            if (typeof showWarningNotification !== 'undefined') {
                showWarningNotification('Product sync is already in progress');
            } else {
                alert('Product sync is already in progress');
            }
            return;
        }

        if (!('indexedDB' in window)) {
            if (typeof showErrorNotification !== 'undefined') {
                showErrorNotification('IndexedDB is not supported in this browser');
            } else {
                alert('IndexedDB is not supported in this browser');
            }
            return;
        }

        this.syncInProgress = true;
        $('#pos-sync-products-btn').prop('disabled', true);
        
        const syncModal = document.getElementById('pos-sync-progress-modal');
        if (syncModal) {
            const modal = new bootstrap.Modal(syncModal);
            $('#pos-sync-close-btn').hide();
            $('#pos-sync-error').hide();
            this.updateSyncProgress(0, 0, 0, 0, 'Starting sync...');
            modal.show();

            try {
                await this.init();
                const result = await this.fetchAndStoreAllProducts((progress) => {
                    this.updateSyncProgress(
                        progress.currentPage,
                        progress.totalPages,
                        progress.stored,
                        progress.total,
                        `Syncing page ${progress.currentPage} of ${progress.totalPages}...`
                    );
                });

                this.syncInProgress = false;
                $('#pos-sync-products-btn').prop('disabled', false);
                
                if (result.success) {
                    $('#pos-sync-status-text').text('Syncing customers to offline cache...');
                    const pendingSyncRes = await this.syncPendingCustomers();
                    const custRes = await this.fetchAndStoreAllCustomers();
                    this.updateSyncProgress(
                        result.totalPages,
                        result.totalPages,
                        result.totalStored,
                        result.totalStored,
                        'Sync completed successfully!'
                    );
                    this.updateFooterProgressBar(0, result.totalStored, result.totalStored, false);
                    if (typeof showSuccessNotification !== 'undefined') {
                        let msg = `Successfully synced ${result.totalStored} products to IndexedDB`;
                        if (custRes.success) {
                            msg += ` and ${custRes.totalStored} customers`;
                        }
                        showSuccessNotification(msg);
                        if (pendingSyncRes.errors > 0) {
                            const firstErr = pendingSyncRes.errorDetails && pendingSyncRes.errorDetails.length
                                ? pendingSyncRes.errorDetails[0].error
                                : '';
                            showWarningNotification(
                                `${pendingSyncRes.errors} offline customer(s) still pending sync.${firstErr ? ' ' + firstErr : ''}`
                            );
                        }
                    }
                    $('#pos-sync-close-btn').show();
                } else {
                    this.updateFooterProgressBar(0, 0, 0, false);
                    $('#pos-sync-error').show();
                    $('#pos-sync-error-message').text(result.error || 'Unknown error occurred');
                    this.updateSyncProgress(0, 0, 0, 0, 'Sync failed');
                    $('#pos-sync-close-btn').show();
                }
            } catch (error) {
                this.syncInProgress = false;
                $('#pos-sync-products-btn').prop('disabled', false);
                this.updateFooterProgressBar(0, 0, 0, false);
                $('#pos-sync-error').show();
                $('#pos-sync-error-message').text(error.message || 'Failed to sync products');
                this.updateSyncProgress(0, 0, 0, 0, 'Sync failed');
                $('#pos-sync-close-btn').show();
                console.error('Product sync error:', error);
            }
        } else {
            try {
                await this.init();
                const result = await this.fetchAndStoreAllProducts((progress) => {
                    this.updateFooterProgressBar(
                        progress.total > 0 ? Math.round((progress.stored / progress.total) * 100) : 0,
                        progress.stored,
                        progress.total,
                        true
                    );
                });
                this.updateFooterProgressBar(0, result.totalStored, result.totalStored, false);
                if (result.success) {
                    await this.syncPendingCustomers();
                    await this.fetchAndStoreAllCustomers();
                    if (typeof showSuccessNotification !== 'undefined') {
                        showSuccessNotification('Products and customers synced successfully');
                    }
                } else if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification(result.error || 'Product sync failed');
                }
            } catch (error) {
                this.updateFooterProgressBar(0, 0, 0, false);
                if (typeof showErrorNotification !== 'undefined') {
                    showErrorNotification('Failed to sync products: ' + error.message);
                }
                console.error('Product sync error:', error);
            } finally {
                this.syncInProgress = false;
                $('#pos-sync-products-btn').prop('disabled', false);
            }
        }
    }

    /**
     * Update sync progress UI (modal and footer progress bar)
     */
    updateSyncProgress(currentPage, totalPages, stored, total, statusText) {
        const percentage = total > 0 ? Math.round((stored / total) * 100) : 0;
        
        $('#pos-sync-status-text').text(statusText);
        $('#pos-sync-percentage').text(percentage + '%');
        $('#pos-sync-progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage);
        $('#pos-sync-details').text(`Page ${currentPage} of ${totalPages} | Stored: ${stored} of ${total} products`);
        
        this.updateFooterProgressBar(percentage, stored, total, true);
    }

    /**
     * Show/update/hide the footer product loading progress bar
     * @param {number} percentage - 0-100
     * @param {number} stored - loaded count
     * @param {number} total - total count
     * @param {boolean} show - true to show bar, false to hide
     */
    updateFooterProgressBar(percentage, stored, total, show) {
        const $wrap = $('#pos-product-load-progress-wrap');
        const $bar = $('#pos-product-load-progress-bar');
        const $text = $('#pos-product-load-progress-text');
        const $count = $('#pos-total-products-count');
        if (show) {
            $wrap.removeClass('d-none').addClass('d-flex');
            $('.loading-text').removeClass('d-none');
            $bar.css('width', percentage + '%').attr('aria-valuenow', percentage);
            $text.text(percentage + '%');
            if (total !== undefined && total !== null) {
                $count.text(typeof total === 'number' ? total.toLocaleString() : total);
            }
        } else {
            $('.loading-text').addClass('d-none');
            $wrap.addClass('d-none').removeClass('d-flex');
            $bar.css('width', '0%').attr('aria-valuenow', 0);
            $text.text('0%');
            if (stored !== undefined && stored !== null && $count.length) {
                $count.text(typeof stored === 'number' ? stored.toLocaleString() : stored);
            }
        }
    }

    /**
     * Sync a single product to IndexedDB (for new products)
     * @param {number} productId - Product ID to sync
     */
    async syncSingleProduct(productId) {
        try {
            if (!this.isInitialized || !this.db) {
                await this.init();
            }
            
            const baseUrl = $('#base_url').val() || window.location.origin;
            const perPage = 500;
            const response = await fetch(`${baseUrl}/pos/products/batch?page=1&per_page=${perPage}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'success' && data.data) {
                    // Find the specific product
                    const product = data.data.find(p => p.id == productId);
                    if (product) {
                        await this.storeProducts([product]);
                        return true;
                    }
                    // If not found in first page, search through all pages
                    const totalPages = data.pagination?.total_pages || 1;
                    for (let page = 2; page <= totalPages; page++) {
                        const pageResponse = await fetch(`${baseUrl}/pos/products/batch?page=${page}&per_page=${perPage}`, {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin'
                        });
                        if (pageResponse.ok) {
                            const pageData = await pageResponse.json();
                            if (pageData.status === 'success' && pageData.data) {
                                const foundProduct = pageData.data.find(p => p.id == productId);
                                if (foundProduct) {
                                    await this.storeProducts([foundProduct]);
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
            return false;
        } catch (error) {
            console.error('Error syncing single product:', error);
            return false;
        }
    }

    /**
     * Check and sync product if not in IndexedDB
     * @param {number} productId - Product ID to check
     */
    async ensureProductInIndexedDB(productId) {
        try {
            const product = await this.getProductById(productId);
            if (!product) {
                // Product not found, try to sync it
                await this.syncSingleProduct(productId);
            }
        } catch (error) {
            console.error('Error ensuring product in IndexedDB:', error);
        }
    }

    /**
     * Update product stock in IndexedDB
     * @param {number} productId - Product ID
     * @param {number} quantity - Quantity to decrease (positive number)
     */
    async decreaseProductStock(productId, quantity) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(productId);
            
            request.onsuccess = () => {
                const product = request.result;
                if (product) {
                    const currentStock = parseFloat(product.stock) || 0;
                    const newStock = Math.max(0, currentStock - quantity);
                    product.stock = newStock;
                    
                    // Update product in IndexedDB
                    const updateRequest = store.put(product);
                    updateRequest.onsuccess = () => {
                        resolve(true);
                    };
                    updateRequest.onerror = () => {
                        reject(new Error('Failed to update product stock in IndexedDB'));
                    };
                } else {
                    resolve(false); // Product not found
                }
            };
            
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }

    /**
     * Remove IMEI/Serial numbers from product in IndexedDB
     * @param {number} productId - Product ID
     * @param {Array<string>} imeiSerialNumbers - Array of IMEI/Serial numbers to remove
     */
    async removeIMEISerialNumbers(productId, imeiSerialNumbers) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(productId);
            
            request.onsuccess = () => {
                const product = request.result;
                if (product && product.imei_number && Array.isArray(product.imei_number)) {
                    const originalCount = product.imei_number.length;
                    
                    // Remove the sold IMEI/serial numbers
                    const updatedIMEINumbers = product.imei_number.filter(imei => {
                        const imeiStr = String(imei).trim();
                        return !imeiSerialNumbers.some(sold => String(sold).trim() === imeiStr);
                    });
                    
                    product.imei_number = updatedIMEINumbers;
                    
                    // Also update stock (decrease by the number of IMEI/serial removed)
                    const currentStock = parseFloat(product.stock) || 0;
                    const removedCount = originalCount - updatedIMEINumbers.length;
                    product.stock = Math.max(0, currentStock - removedCount);
                    
                    // Update product in IndexedDB
                    const updateRequest = store.put(product);
                    updateRequest.onsuccess = () => {
                        resolve(true);
                    };
                    updateRequest.onerror = () => {
                        reject(new Error('Failed to remove IMEI/Serial numbers from IndexedDB'));
                    };
                } else {
                    resolve(false); // Product not found or not IMEI/Serial product
                }
            };
            
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }

    /**
     * Decrease medicine stock for specific expiry dates
     * @param {number} productId - Product ID
     * @param {Array} medicineExpiryData - Array of {expiry_date, quantity}
     */
    async decreaseMedicineStock(productId, medicineExpiryData) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(productId);
            
            request.onsuccess = () => {
                const product = request.result;
                if (product && product.medicine && Array.isArray(product.medicine)) {
                    // Process each expiry date
                    medicineExpiryData.forEach(medExpiry => {
                        const expiryDate = medExpiry.expiry_date;
                        const quantityToDecrease = parseFloat(medExpiry.quantity) || 0;
                        
                        if (quantityToDecrease <= 0) return;
                        
                        // Find and update medicine entries with matching expiry date
                        let remainingQty = quantityToDecrease;
                        product.medicine = product.medicine.filter(med => {
                            if (med.expiry_imei_serial === expiryDate && remainingQty > 0) {
                                const currentStock = parseFloat(med.stock_quantity) || 0;
                                if (currentStock <= remainingQty) {
                                    // Remove this entry completely
                                    remainingQty -= currentStock;
                                    return false;
                                } else {
                                    // Decrease stock quantity
                                    med.stock_quantity = currentStock - remainingQty;
                                    remainingQty = 0;
                                    return true;
                                }
                            }
                            return true;
                        });
                    });
                    
                    // Update product in IndexedDB
                    const updateRequest = store.put(product);
                    updateRequest.onsuccess = () => {
                        resolve(true);
                    };
                    updateRequest.onerror = () => {
                        reject(new Error('Failed to update medicine stock in IndexedDB'));
                    };
                } else {
                    resolve(false); // Product not found or not medicine product
                }
            };
            
            request.onerror = () => {
                reject(new Error('Failed to get product from IndexedDB'));
            };
        });
    }
    
    /**
     * Product IDs to refresh on POS cards after a sale (parent lines + combo components).
     * @param {Array} cartItems
     * @returns {number[]}
     */
    collectProductIdsForStockUiRefresh(cartItems) {
        const ids = [];
        const seen = new Set();
        const add = (rawId) => {
            const n = parseInt(rawId, 10);
            if (n && !seen.has(n)) {
                seen.add(n);
                ids.push(n);
            }
        };
        for (const item of cartItems || []) {
            if (!item) continue;
            add(item.product_id);
            if (item.product_type === 'Combo_Product' && Array.isArray(item.combo_items)) {
                for (const c of item.combo_items) {
                    if (c && c.item_id != null) {
                        add(c.item_id);
                    }
                }
            }
        }
        return ids;
    }

    /**
     * Update multiple products after sale (decrease stock and remove IMEI/serial)
     * @param {Array} cartItems - Array of cart items with product_id, quantity, selected_imei_serial, selected_medicine_expiry
     */
    async updateProductsAfterSale(cartItems) {
        try {
            const updatePromises = cartItems.map(async (item) => {
                try {
                    const productId = item.product_id;
                    const quantity = parseFloat(item.quantity) || 0;
                    const selectedImeiSerial = item.selected_imei_serial || [];
                    const selectedMedicineExpiry = item.selected_medicine_expiry || [];
                    const productType = item.product_type || '';
                    const comboItems = item.combo_items || [];

                    if (productType === 'Combo_Product' && Array.isArray(comboItems) && comboItems.length > 0) {
                        await Promise.all(
                            comboItems.map(async (ci) => {
                                const cid = parseInt(ci.item_id, 10);
                                if (!cid) return;
                                const perCombo = parseFloat(ci.quantity) || 0;
                                const dec = perCombo * quantity;
                                if (dec <= 0) return;
                                const ok = await this.decreaseProductStock(cid, dec);
                                if (ok) {
                                    console.log(`Combo: decreased stock by ${dec} for component ${cid}`);
                                } else {
                                    console.warn(`Combo: could not decrease stock for component ${cid} (not in IndexedDB?)`);
                                }
                            })
                        );
                        return;
                    }

                    // Check if it's IMEI/Serial product
                    const isIMEISerialProduct = productType === 'IMEI_Product' || productType === 'Serial_Product';

                    // Check if it's Medicine_Product with expiry dates
                    const isMedicineProduct = productType === 'Medicine_Product' && selectedMedicineExpiry.length > 0;

                    if (isIMEISerialProduct && selectedImeiSerial.length > 0) {
                        // Remove IMEI/Serial numbers (this also decreases stock)
                        const result = await this.removeIMEISerialNumbers(productId, selectedImeiSerial);
                        if (result) {
                            console.log(`Successfully removed ${selectedImeiSerial.length} IMEI/Serial numbers for product ${productId}`);
                        } else {
                            console.warn(`Failed to remove IMEI/Serial numbers for product ${productId} - product not found or not IMEI/Serial product`);
                        }
                        await this.deleteImeiSerialRecordsForItem(productId, selectedImeiSerial);
                    } else if (isMedicineProduct) {
                        // Decrease medicine stock for specific expiry dates
                        const result = await this.decreaseMedicineStock(productId, selectedMedicineExpiry);
                        if (result) {
                            console.log(`Successfully decreased medicine stock for product ${productId}`);
                        } else {
                            console.warn(`Failed to decrease medicine stock for product ${productId} - product not found or not medicine product`);
                        }
                    } else {
                        // Regular product - just decrease stock
                        if (quantity > 0) {
                            const result = await this.decreaseProductStock(productId, quantity);
                            if (result) {
                                console.log(`Successfully decreased stock by ${quantity} for product ${productId}`);
                            } else {
                                console.warn(`Failed to decrease stock for product ${productId} - product not found`);
                            }
                        }
                    }
                } catch (itemError) {
                    console.error(`Error updating product ${item.product_id} after sale:`, itemError);
                    // Continue with other items even if one fails
                }
            });
            
            await Promise.all(updatePromises);
            return true;
        } catch (error) {
            console.error('Error updating products after sale:', error);
            return false;
        }
    }

    /**
     * Save offline sale to IndexedDB
     * @param {Object} saleData - Sale data with sale_detail and sale_payment properties
     * @returns {Promise<number>} - Returns the ID of the saved sale
     */
    async saveOfflineSale(saleData) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
            const store = transaction.objectStore(this.salesStoreName);
            
            // Add timestamp for tracking
            const saleRecord = {
                ...saleData,
                created_at: new Date().toISOString(),
                synced: false
            };
            
            const request = store.add(saleRecord);
            request.onsuccess = () => {
                resolve(request.result); // Returns the auto-increment ID
            };
            request.onerror = () => {
                reject(new Error('Failed to save offline sale to IndexedDB'));
            };
        });
    }

    /**
     * Get all unsynced offline sales
     * @returns {Promise<Array>} - Returns array of unsynced sales
     */
    async getUnsyncedSales() {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readonly');
            const store = transaction.objectStore(this.salesStoreName);
            const request = store.getAll();

            request.onsuccess = () => {
                const allSales = request.result;
                const unsyncedSales = allSales.filter(sale => !sale.synced);
                resolve(unsyncedSales);
            };
            request.onerror = () => {
                reject(new Error('Failed to get unsynced sales from IndexedDB'));
            };
        });
    }

    /**
     * Mark sale as synced
     * @param {number} saleId - Sale ID in IndexedDB
     * @returns {Promise<boolean>}
     */
    async markSaleAsSynced(saleId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
            const store = transaction.objectStore(this.salesStoreName);
            const request = store.get(saleId);
            
            request.onsuccess = () => {
                const sale = request.result;
                if (sale) {
                    sale.synced = true;
                    sale.synced_at = new Date().toISOString();
                    const updateRequest = store.put(sale);
                    updateRequest.onsuccess = () => resolve(true);
                    updateRequest.onerror = () => reject(new Error('Failed to mark sale as synced'));
                } else {
                    resolve(false);
                }
            };
            request.onerror = () => {
                reject(new Error('Failed to get sale from IndexedDB'));
            };
        });
    }

    /**
     * Delete synced sale from IndexedDB
     * @param {number} saleId - Sale ID in IndexedDB
     * @returns {Promise<boolean>}
     */
    async deleteSyncedSale(saleId) {
        if (!this.isInitialized || !this.db) {
            await this.init();
        }
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.salesStoreName], 'readwrite');
            const store = transaction.objectStore(this.salesStoreName);
            const request = store.delete(saleId);
            
            request.onsuccess = () => {
                resolve(true);
            };
            request.onerror = () => {
                reject(new Error('Failed to delete synced sale from IndexedDB'));
            };
        });
    }

    /**
     * Get count of unsynced sales
     * @returns {Promise<number>}
     */
    async getUnsyncedSalesCount() {
        const unsyncedSales = await this.getUnsyncedSales();
        return unsyncedSales.length;
    }

    /**
     * Company default payment method id from session (same as POS quick sale / server fallback).
     */
    _getCompanyDefaultPaymentId() {
        try {
            const c = JSON.parse($('#company_data').val() || '{}');
            const id = parseInt(c.default_payment, 10);
            return Number.isFinite(id) && id > 0 ? id : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Build POST /pos/sale body from an IndexedDB offline record (fixes legacy payment_id: 1 for new companies).
     */
    _preparePosSalePayloadFromOfflineRecord(sale) {
        const detail = sale.sale_detail || {};
        const pay = sale.sale_payment || {};
        const randomCode = (detail.random_code || sale.random_code || `OFFLINE-${sale.id || Date.now()}`);
        let payments = Array.isArray(pay.payments) ? pay.payments.map((p) => Object.assign({}, p)) : [];
        let saleAsDue = !!detail.sale_as_due;
        const dueAmt = parseFloat(pay.due_amount) || 0;

        if (!saleAsDue && payments.length === 0 && dueAmt > 0.001) {
            saleAsDue = true;
        }

        if (saleAsDue) {
            payments = [];
        }

        const defaultPayId = this._getCompanyDefaultPaymentId();
        if (!saleAsDue && payments.length === 1 && !payments[0].gateway_transaction_id && defaultPayId) {
            const p = payments[0];
            const nm = (p.payment_name || '').trim();
            const pid = parseInt(p.payment_id, 10);
            if (nm === 'Cash' && pid === 1) {
                p.payment_id = defaultPayId;
            }
        }

        return {
            customer_id: detail.customer_id,
            employee_id: detail.employee_id,
            cart_items: detail.cart_items,
            subtotal: detail.subtotal,
            tax: detail.tax,
            discount: detail.discount,
            discount_type: detail.discount_type,
            shipping: detail.shipping,
            total_payable: detail.total_payable,
            payments: payments,
            total_paid: pay.total_paid,
            change_amount: pay.change_amount,
            due_amount: pay.due_amount,
            sale_as_due: saleAsDue,
            sale_date: detail.sale_date,
            note: detail.note,
            random_code: randomCode
        };
    }

    /**
     * Sync offline sales to server
     * @returns {Promise<Object>} - Returns sync result with success count and errors
     */
    async syncOfflineSales() {
        try {
            const unsyncedSales = await this.getUnsyncedSales();
            if (unsyncedSales.length === 0) {
                return { success: 0, errors: 0, message: 'No offline sales to sync' };
            }

            const baseUrl = $('#base_url').val() || window.location.origin;
            let successCount = 0;
            let errorCount = 0;
            const errors = [];

            for (const sale of unsyncedSales) {
                try {
                    const saleDataToSync = this._preparePosSalePayloadFromOfflineRecord(sale);

                    const response = await fetch(`${baseUrl}/pos/sale`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(saleDataToSync)
                    });

                    let result = {};
                    try {
                        const text = await response.text();
                        result = text ? JSON.parse(text) : {};
                    } catch (parseErr) {
                        errorCount++;
                        errors.push({
                            saleId: sale.id,
                            error: 'Invalid server response while syncing sale'
                        });
                        continue;
                    }

                    if (response.ok && result.status === 'success') {
                        // Mark as synced
                        await this.markSaleAsSynced(sale.id);
                        // Delete from IndexedDB after successful sync
                        await this.deleteSyncedSale(sale.id);
                        successCount++;
                    } else {
                        errorCount++;
                        errors.push({
                            saleId: sale.id,
                            error: result.message || 'Unknown error'
                        });
                    }
                } catch (error) {
                    errorCount++;
                    errors.push({
                        saleId: sale.id,
                        error: error.message || 'Network error'
                    });
                }
            }

            return {
                success: successCount,
                errors: errorCount,
                total: unsyncedSales.length,
                errorDetails: errors
            };
        } catch (error) {
            console.error('Error syncing offline sales:', error);
            return {
                success: 0,
                errors: 0,
                total: 0,
                error: error.message || 'Failed to sync offline sales'
            };
        }
    }
}

// Initialize global instance
const posIndexedDB = new POSIndexedDB();

// Auto-initialize IndexedDB on page load
$(document).ready(function() {
    posIndexedDB.init().then(() => {
        console.log('IndexedDB initialized successfully');
    }).catch((error) => {
        console.error('Failed to initialize IndexedDB:', error);
    });

    // Sync button click handler
    $(document).on('click', '#pos-sync-products-btn', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Alert!',
            text: "This will re-sync all products to IndexedDB. This may take a few minutes. Continue?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Continue',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function(result) {
            if (result.value) {
                posIndexedDB.startSync();
            }
        });
    });

    // Auto-sync products on page load
    if ('indexedDB' in window) {
        setTimeout(function() {
            if (!posIndexedDB.syncInProgress) {
                posIndexedDB.startSync();
            }
        }, 500);
    }
});
