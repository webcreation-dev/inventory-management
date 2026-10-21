(function (window, $) {
    'use strict';

    /**
     * QZ Tray 2.x security + connection manager.
     * Official workflow: https://qz.io/docs/signing
     */
    const QZManager = {
        connected: false,
        connecting: false,
        reconnectTimer: null,
        securityConfigured: false,

        getConfig() {
            return window.PrintManagerConfig || {};
        },

        getQzConfig() {
            const config = this.getConfig();
            return config.qz || {};
        },

        isProductionMode() {
            const env = (this.getConfig().printEnv || this.getQzConfig().env || 'development').toLowerCase();
            return env === 'production';
        },

        shouldUseServerSigning() {
            const qz = this.getQzConfig();
            if (this.isProductionMode()) {
                return true;
            }
            return !!(qz.certificateConfigured && qz.privateKeyConfigured);
        },

        getReconnectInterval() {
            return parseInt(this.getQzConfig().reconnect_interval_ms || 3000, 10);
        },

        getSignatureAlgorithm() {
            return this.getQzConfig().signature_algorithm || 'SHA512';
        },

        setupSecurity() {
            if (typeof qz === 'undefined') {
                return;
            }

            const config = this.getConfig();
            const algorithm = this.getSignatureAlgorithm();

            if (typeof qz.security.setSignatureAlgorithm === 'function') {
                qz.security.setSignatureAlgorithm(algorithm);
            }

            if (!this.shouldUseServerSigning()) {
                // Official QZ Tray 2.2.4 unsigned pattern (sample.html "Alternate method 1").
                // Promises MUST be registered; skipping them leaves the library default
                // certHandler which calls reject() and logs "Failed to get certificate: undefined".
                this.securityConfigured = false;
                console.info('[PrintManager] QZ unsigned mode: anonymous certificate + signature (Allow prompts).');

                qz.security.setCertificatePromise(function (resolve) {
                    // resolve();
                    resolve(null);
                });

                qz.security.setSignaturePromise(function () {
                    return function (resolve) {
                        resolve();
                    };
                });

                return;
            }

            this.securityConfigured = true;

            qz.security.setCertificatePromise(function (resolve, reject) {
                $.ajax({
                    url: config.routes.certificate,
                    method: 'GET',
                    cache: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    success: function (response) {
                        if (response && response.success && response.certificate) {
                            resolve(response.certificate);
                            return;
                        }
                        reject((response && response.message) || 'Certificate not available.');
                    },
                    error: function (xhr) {
                        let message = 'Unable to load QZ certificate.';
                        try {
                            const json = xhr.responseJSON;
                            if (json && json.message) {
                                message = json.message;
                            }
                        } catch (e) {
                            // ignore
                        }
                        reject(message);
                    }
                });
            });

            qz.security.setSignaturePromise(function (toSign) {
                return function (resolve, reject) {
                    $.ajax({
                        url: config.routes.sign,
                        method: 'POST',
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrfToken,
                            'Accept': 'application/json'
                        },
                        data: {
                            request: toSign,
                            toSign: toSign,
                            _token: config.csrfToken
                        },
                        success: function (response) {
                            if (response && response.success && response.signature) {
                                resolve(response.signature);
                                return;
                            }
                            reject((response && response.message) || 'Invalid signature response.');
                        },
                        error: function (xhr) {
                            let message = 'Unable to sign QZ request.';
                            try {
                                const json = xhr.responseJSON;
                                if (json && json.message) {
                                    message = json.message;
                                }
                            } catch (e) {
                                // ignore
                            }
                            reject(message);
                        }
                    });
                };
            });
        },



        connect(forceReconnect) {
            const self = this;

            if (typeof qz === 'undefined') {
                return $.Deferred().reject('QZ Tray JavaScript library is not loaded.').promise();
            }

            if (this.connected && !forceReconnect) {
                return $.Deferred().resolve().promise();
            }

            // Wait for an in-flight connect instead of resolving early (that broke printer lists).
            if (this.connecting && this._connectDeferred) {
                return this._connectDeferred.promise();
            }

            this.connecting = true;
            this.setupSecurity();

            const deferred = $.Deferred();
            this._connectDeferred = deferred;

            const qzConfig = this.getQzConfig();
            const connectOptions = {};

            if (qzConfig.host) {
                connectOptions.host = qzConfig.host;
            }

            qz.websocket.connect(connectOptions)
                .then(function () {
                    self.connected = true;
                    self.connecting = false;
                    self.clearReconnectTimer();
                    $(document).trigger('printmanager:qz:connected');
                    deferred.resolve(true);
                })
                .catch(function (error) {
                    self.connected = false;
                    self.connecting = false;
                    self.scheduleReconnect();
                    deferred.reject(self.normalizeError(error));
                })
                .finally(function () {
                    if (self._connectDeferred === deferred) {
                        self._connectDeferred = null;
                    }
                });

            return deferred.promise();
        },

        disconnect() {

            this.clearReconnectTimer();

            if (typeof qz === 'undefined' || !this.connected) {
                this.connected = false;
                return $.Deferred().resolve().promise();
            }

            return qz.websocket.disconnect()
                .then(() => {
                    this.connected = false;
                });
        },

        scheduleReconnect() {
            const self = this;

            if (this.reconnectTimer) {
                return;
            }

            this.reconnectTimer = window.setTimeout(function () {
                self.reconnectTimer = null;
                self.connect(true);
            }, this.getReconnectInterval());
        },

        clearReconnectTimer() {
            if (this.reconnectTimer) {
                window.clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
        },

        findPrinters() {
            const self = this;
            const deferred = $.Deferred();

            if (typeof qz === 'undefined') {
                return deferred.reject('QZ Tray JavaScript library is not loaded.').promise();
            }

            const resolvePrinters = function () {
                qz.printers.find()
                    .then(function (printers) {
                        deferred.resolve(printers || []);
                    })
                    .catch(function (error) {
                        deferred.reject(self.normalizeError(error));
                    });
            };

            if (qz.websocket.isActive()) {
                resolvePrinters();
            } else {
                this.connect()
                    .then(resolvePrinters)
                    .catch(function (error) {
                        deferred.reject(error);
                    });
            }

            return deferred.promise();
        },

        findDefaultPrinter() {
            return this.connect().then(function () {
                return qz.printers.getDefault();
            });
        },

        getSecurityStatus() {
            const config = this.getConfig();
            if (!config.routes || !config.routes.status) {
                return $.Deferred().resolve(null).promise();
            }

            return $.getJSON(config.routes.status);
        },

        normalizeError(error) {
            if (!error) {
                return 'Unknown QZ Tray error.';
            }

            if (typeof error === 'string') {
                if (error.toLowerCase().indexOf('connection') !== -1) {
                    return 'QZ Tray is not running. Please start QZ Tray on this computer.';
                }
                return error;
            }

            const message = error.message || error.toString();

            if (message.toLowerCase().indexOf('connection') !== -1) {
                return 'QZ Tray is not running. Please start QZ Tray on this computer.';
            }

            return message;
        }
    };

    window.PrintManagerQZ = QZManager;
})(window, jQuery);
