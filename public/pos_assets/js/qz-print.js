/* global qz */
(function (window) {
    "use strict";

    const state = {
        reconnectTimer: null,
    };

    const defaults = {
        charsPerLine: 42,
        encoding: "ISO-8859-1",
    };

    function getCsrfToken() {
        const el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute("content") : "";
    }

    function getConfig() {
        return window.qzTrayConfig || {};
    }

    function configureSecurity() {
        const cfg = getConfig();

        qz.security.setCertificatePromise(function () {
            return fetch(cfg.certificateUrl, { credentials: "same-origin" })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error("Unable to fetch QZ certificate");
                    }
                    return res.json();
                })
                .then(function (json) {
                    return json.certificate;
                });
        });

        qz.security.setSignaturePromise(function (toSign) {
            return fetch(cfg.signatureUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ toSign: toSign }),
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error("Unable to sign QZ payload");
                    }
                    return res.json();
                })
                .then(function (json) {
                    return json.signature;
                });
        });
    }

    function scheduleReconnect() {
        if (state.reconnectTimer) {
            return;
        }

        state.reconnectTimer = window.setTimeout(function () {
            state.reconnectTimer = null;
            connectQZ().catch(function () {
                scheduleReconnect();
            });
        }, 3000);
    }

    async function connectQZ() {
        if (typeof window.qz === "undefined") {
            throw new Error("QZ Tray library not loaded");
        }

        // if (qz.websocket.isActive()) {
        //     return true;
        // }
        if (qz.websocket.isActive()) {
            return $.Deferred().resolve().promise();
        }

        configureSecurity();

        try {
            await qz.websocket.connect({
                retries: 2,
                delay: 1,
            });
            return true;
        } catch (err) {
            scheduleReconnect();
            throw new Error("QZ Tray is not running. Please start QZ Tray and try again.");
        }
    }

    async function findPrinter() {
        const cfg = getConfig();
        const preferred = cfg.defaultPrinter;
        const printers = await qz.printers.find();

        if (!printers || !printers.length) {
            throw new Error("No printers found via QZ Tray");
        }

        if (preferred && printers.indexOf(preferred) !== -1) {
            return preferred;
        }

        if (cfg.printerMatcher) {
            const match = printers.find(function (printer) {
                return printer.toLowerCase().indexOf(String(cfg.printerMatcher).toLowerCase()) !== -1;
            });
            if (match) {
                return match;
            }
        }

        return printers[0];
    }

    function alignText(text, width, align) {
        const safe = String(text || "");
        if (safe.length >= width) {
            return safe.slice(0, width);
        }
        const space = width - safe.length;
        if (align === "right") {
            return " ".repeat(space) + safe;
        }
        if (align === "center") {
            const left = Math.floor(space / 2);
            const right = space - left;
            return " ".repeat(left) + safe + " ".repeat(right);
        }
        return safe + " ".repeat(space);
    }

    function money(value) {
        const num = Number(value || 0);
        return num.toFixed(2);
    }

    function buildEscPosReceipt(payload) {
        const width = Number(getConfig().charsPerLine || defaults.charsPerLine);
        const shop = payload.shop || {};
        const invoice = payload.invoice || {};
        const items = invoice.items || [];
        const totals = invoice.totals || {};
        const lines = [];

        lines.push("\x1B\x40"); // Init
        lines.push("\x1B\x61\x01"); // Center
        lines.push((shop.name || "Store") + "\n");
        if (shop.outlet) lines.push(shop.outlet + "\n");
        if (shop.phone) lines.push("Phone: " + shop.phone + "\n");
        if (shop.email) lines.push("Email: " + shop.email + "\n");
        if (shop.address) lines.push(shop.address + "\n");

        lines.push("\x1B\x61\x00"); // Left
        lines.push("-".repeat(width) + "\n");
        lines.push("Invoice: " + (invoice.invoice_no || "N/A") + "\n");
        lines.push("Date: " + (invoice.date_time || "") + "\n");
        lines.push("Customer: " + ((invoice.customer && invoice.customer.name) || "Walk-in Customer") + "\n");
        lines.push("Cashier: " + (invoice.cashier || "N/A") + "\n");
        lines.push("Status: " + (invoice.status || "N/A") + "\n");
        lines.push("-".repeat(width) + "\n");
        lines.push("Item                 Qty   Price   Total\n");
        lines.push("-".repeat(width) + "\n");

        items.forEach(function (item) {
            const name = String(item.name || "").slice(0, 20);
            const qty = alignText(Number(item.qty || 0).toFixed(2), 5, "right");
            const price = alignText(money(item.price), 7, "right");
            const total = alignText(money(item.line_total), 8, "right");
            lines.push(alignText(name, 20, "left") + " " + qty + " " + price + " " + total + "\n");
        });

        lines.push("-".repeat(width) + "\n");
        lines.push(alignText("Subtotal", 24, "left") + alignText(money(totals.subtotal), width - 24, "right") + "\n");
        lines.push(alignText("VAT", 24, "left") + alignText(money(totals.vat), width - 24, "right") + "\n");
        lines.push(alignText("Discount", 24, "left") + alignText(money(totals.discount), width - 24, "right") + "\n");
        lines.push("\x1B\x45\x01"); // Bold ON
        lines.push(alignText("Grand Total", 24, "left") + alignText(money(totals.grand_total), width - 24, "right") + "\n");
        lines.push("\x1B\x45\x00"); // Bold OFF
        lines.push(alignText("Paid", 24, "left") + alignText(money(totals.paid_amount), width - 24, "right") + "\n");
        lines.push(alignText("Due", 24, "left") + alignText(money(totals.due_amount), width - 24, "right") + "\n");
        lines.push("Payment: " + (invoice.payment_method || "Cash") + "\n");

        lines.push("\n");
        lines.push("\x1B\x61\x01");
        lines.push((shop.footer || "Thank you!") + "\n");
        lines.push("\n\n\n");
        lines.push("\x1D\x56\x00"); // Full cut

        return lines.join("");
    }

    async function fetchInvoice(invoiceId) {
        const cfg = getConfig();
        const url = cfg.invoiceApiBase + "/" + encodeURIComponent(invoiceId);
        const res = await fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });

        if (!res.ok) {
            throw new Error("Invoice API request failed");
        }

        return res.json();
    }

    async function printInvoice(data) {
        await connectQZ();
        const printerName = await findPrinter();
        const config = qz.configs.create(printerName, {
            encoding: getConfig().encoding || defaults.encoding,
            altPrinting: false,
        });

        const escposData = buildEscPosReceipt(data);
        return qz.print(config, [{ type: "raw", format: "plain", data: escposData }]);
    }

    async function printByInvoiceId(invoiceId, options) {
        const opts = options || {};
        try {
            const payload = await fetchInvoice(invoiceId);
            await printInvoice(payload);
            return true;
        } catch (error) {
            if (window.showErrorNotification) {
                window.showErrorNotification(error.message || "Invoice print failed");
            }

            if (opts.fallbackUrl) {
                window.open(opts.fallbackUrl, "_blank");
            }
            return false;
        }
    }

    window.PosQzPrint = {
        connectQZ: connectQZ,
        findPrinter: findPrinter,
        printInvoice: printInvoice,
        printByInvoiceId: printByInvoiceId,
        buildEscPosReceipt: buildEscPosReceipt,
    };
})(window);

