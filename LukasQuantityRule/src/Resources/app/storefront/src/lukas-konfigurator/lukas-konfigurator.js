export default class LukasKonfigurator {
    init() {
        this.initKonfigurator();
        this.initCheckoutComment();
        this.startGlobalKontaktObserver();
    }

    normalizeFormula(raw) {
        if (typeof raw !== 'string') return '';

        let s = raw.replace(/[\u00A0\u1680\u2000-\u200A\u202F\u205F\u3000]/g, ' ');
        s = s.replace(/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/g, '');

        return s.replace(/\s+/g, ' ').trim();
    }

    safeEvalFormula(formula, vars = {}) {
        if (!formula) return 0;

        let expr = this.normalizeFormula(formula);

        ['anzahl', 'laenge', 'mengeGewebe', 'mengeZusatz', 'width', 'shrink', 'menge'].forEach(name => {
            const v = Number(vars[name] ?? 0);
            expr = expr.replace(new RegExp(`\\b${name}\\b`, 'g'), v);
        });

        ['ceil', 'floor', 'round', 'min', 'max'].forEach(fn => {
            expr = expr.replace(new RegExp(`\\b${fn}\\s*\\(`, 'gi'), `Math.${fn}(`);
        });

        if (!/^[0-9+\-*/().,\sA-Za-z]+$/.test(expr)) {
            throw new Error(`Ungültige Zeichen in Formel: ${expr}`);
        }

        const result = Function('"use strict"; return (' + expr + ');')();
        return Number.isFinite(result) ? result : 0;
    }

    safeEvalFormulaWithFallback(formula, vars = {}, fallback = 0) {
        const normalized = this.normalizeFormula(formula);

        if (!normalized) {
            return Number(fallback || 0);
        }

        try {
            const result = this.safeEvalFormula(normalized, vars);
            return Number.isFinite(result) ? result : Number(fallback || 0);
        } catch (e) {
            return Number(fallback || 0);
        }
    }

    getStoredComment() {
        return sessionStorage.getItem('lukasKonfiguratorKommentar');
    }

    setStoredComment(text) {
        sessionStorage.setItem('lukasKonfiguratorKommentar', text);
    }

    clearStoredCommentOnSubmit(textarea) {
        const form = textarea?.closest('form');

        if (form && !form.lukasListener) {
            form.lukasListener = true;
            form.addEventListener('submit', () => {
                sessionStorage.removeItem('lukasKonfiguratorKommentar');
            });
        }
    }

initCheckoutComment() {
    const kommentar = this.getStoredComment();
    if (!kommentar) return;

    const checkoutTextarea = document.querySelector('textarea[name="customerComment"]');
    if (!checkoutTextarea) return;

    checkoutTextarea.value = kommentar;
    checkoutTextarea.dispatchEvent(new Event('input', { bubbles: true }));
    checkoutTextarea.dispatchEvent(new Event('change', { bubbles: true }));

    this.clearStoredCommentOnSubmit(checkoutTextarea);
}



    findModalCommentTextarea() {
        return (
            document.querySelector('.modal textarea[name="comment"]') ||
            document.querySelector('textarea[name="comment"]') ||
            document.querySelector('.modal textarea') ||
            document.querySelector('textarea[name="customerComment"]')
        );
    }

fillModalCommentFromStorage() {
    const kommentar = this.getStoredComment();
    if (!kommentar) return false;

    const textarea = this.findModalCommentTextarea();
    if (!textarea) return false;

    if (textarea.value !== kommentar) {
        textarea.value = kommentar;
        ['input', 'change', 'blur'].forEach(eventType => {
            textarea.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
    }

        textarea.lukasFilled = true;
        this.clearStoredCommentOnSubmit(textarea);

        return true;
    }

    fillModalTextareaWithRetry(maxTries = 25, delay = 200) {
        let tries = 0;
        const run = () => {
            tries += 1;
            
            const textarea = this.findModalCommentTextarea();
            const text = this.getStoredComment();
            
            if (!text || !textarea) {
                if (tries < maxTries) {
                    setTimeout(run, delay);
                }
                return;
            }

textarea.value = text;

            ['input', 'change', 'blur'].forEach(eventType => {
                textarea.dispatchEvent(new Event(eventType, { bubbles: true }));
            });

            textarea.lukasFilled = true;
        };
        run();
    }

    buildExtraCommentDOM(productNumber, productName, getMainQuantity) {
    const lines = [];

    // Hauptprodukt
    lines.push(`Produkt: ${productName}`);
    lines.push(`Art.Nr.: ${productNumber}`);
    lines.push(`Menge: ${getMainQuantity()}`);
    lines.push(''); // Leerzeile

    // Zusatzprodukte
    document.querySelectorAll('.lukas-extra-product-check').forEach(chk => {
        if (!chk.checked) return;

        const idx = chk.dataset.slotIndex;
        const row = chk.closest('.lukas-extra-item-row');

        const name = row?.querySelector('.lukas-extra-name')?.textContent?.trim() || 'Zusatzprodukt';
        const artnr = chk.dataset.productNumber || '';
        const qty = document.getElementById(`lukas-extra-qty-${idx}`)?.textContent?.trim() || '1';
        const unit = row?.querySelector('.lukas-extra-unit')?.textContent?.trim() || 'Stück';

        lines.push(`Zusatzprodukt:`);
        lines.push(`${name}`);
        lines.push(`Art.Nr.: ${artnr}`);
        lines.push(`Menge: ${qty} ${unit}`);
        lines.push(''); // Leerzeile nach jedem Zusatzprodukt
    });

    return lines.join('\n');
}


    buildGewebeCommentDOM(productNumber, productName) {
        const anzahl = document.getElementById('lukas-anzahl')?.value || '';
        const laenge = document.getElementById('lukas-laenge')?.value || '';
        const mengeGewebe = document.getElementById('lukas-menge-display')?.textContent?.trim() || '–';
        const mengeZusatz = document.getElementById('lukas-zusatz-display')?.textContent?.trim() || '–';
        const optionANumber = document.getElementById('lukas-option-a-dynamic-number')?.textContent?.trim() || '–';
        const optionAName = document.getElementById('lukas-option-a-dynamic-name')?.textContent?.trim() || '–';
        const optionAUnit = document.getElementById('lukas-zusatz-unit')?.textContent?.trim() || 'Stück';

        const lines = [];
        lines.push(`Produkt: ${productName} (${productNumber})`);
        lines.push(`Anzahl Bahnen: ${anzahl}`);
        lines.push(`Laenge Bahnen: ${laenge} m`);
        lines.push(`Gewebemenge: ${mengeGewebe}`);
        lines.push(`Option A: ${optionAName} / Art.Nr. ${optionANumber} / Menge: ${mengeZusatz} ${optionAUnit}`);

        document.querySelectorAll('.lukas-option-b-dynamic').forEach(chk => {
            if (!chk.checked) return;

            const label = document.querySelector(`label[for="${chk.id}"]`)?.textContent?.trim() || 'Zubehör';
            lines.push(`Zubehoer: ${label}`);
        });

        return lines.join('\n');
    }

    initKontaktFlow({ btnKontakt, kontaktLink, berechne, buildKonfigurationsText }) {
        if (!btnKontakt || !kontaktLink || btnKontakt.dataset.lukasKontaktBound === 'true') {
            return;
        }

        btnKontakt.dataset.lukasKontaktBound = 'true';

        // Alten Handler entfernen
        btnKontakt.onclick = null;

        const konfigurator = document.getElementById('lukas-konfigurator');
        const isExtraMode = konfigurator?.dataset.showExtraMode === 'true';
        const productNumber = konfigurator?.dataset.productNumber || '';
        const productName = konfigurator?.dataset.productName || '';

        const getMainQuantity = () => {
            const qtyInput = this.getShopwareQuantityInput();
            return Number(qtyInput?.value || 1) > 0 ? Number(qtyInput?.value || 1) : 1;
        };

        const buildComment = () => {
            if (isExtraMode) {
                return this.buildExtraCommentDOM(productNumber, productName, getMainQuantity);
            }
            return this.buildGewebeCommentDOM(productNumber, productName);
        };

btnKontakt.addEventListener('click', (e) => {
    e.preventDefault();

    // Fallback: Original-Logik
    let result = berechne?.();
    let text = result ? buildKonfigurationsText(result) : null;

    // Primär: DOM-basierte Logik (robuster)
    if (!text) {
        text = buildComment();
    }

    if (!text) {
        console.warn('Keine Konfigurationsdaten verfügbar');
        return;
    }

    this.setStoredComment(text);

    // 🔥 Betreff setzen
    const subject = document.getElementById('cms-form-contact-form-subject');
    if (subject) {
        subject.value = `Kundenanfrage zu ${productName} (${productNumber})`;
    }

    document.querySelectorAll('textarea[name="comment"]').forEach(t => {
        t.lukasFilled = false;
    });

    kontaktLink.removeAttribute('data-ajax-modal');
    kontaktLink.click();

    this.fillModalTextareaWithRetry(25, 200);
});
}

    getShopwareQuantityInput() {
        return (
            document.querySelector('input[name="quantity"]') ||
            document.querySelector('.buy-widget input[name="quantity"]') ||
            document.querySelector('form[action*="/checkout/line-item/add"] input[name="quantity"]') ||
            document.querySelector('input.quantity-selector-group-input') ||
            document.querySelector('input.js-quantity-selector') ||
            document.querySelector('input[type="number"][name*="quantity"]')
        );
    }

    async addToCartByProductNumber(number, qty) {
        await fetch(`/add-to-cart/${number}?qty=${qty}&redirectToCart=false`, {
            redirect: 'manual'
        });
    }

    initExtraMode(c) {
        const btnExtraAdd = document.getElementById('lukas-extra-add-to-cart');
        const btnKontakt = document.getElementById('lukas-kontakt-btn');
        const kontaktLink = document.getElementById('lukas-kontakt-link');
        const divError = document.getElementById('lukas-extra-error');
        const btnAddText = c.dataset.btnAdd || 'In den Warenkorb';
        const btnAddingText = c.dataset.btnAdding || 'Wird hinzugefügt';
        const productNumber = c.dataset.productNumber || '';
        const productName = c.dataset.productName || '';

        const quantityInput = this.getShopwareQuantityInput();
        const extraChecks = [...c.querySelectorAll('.lukas-extra-product-check')];
        const extraFormulas = [...c.querySelectorAll('.lukas-extra-formula')];

        const getMainQuantity = () => {
            const qty = Number(quantityInput?.value || 1);
            return qty > 0 ? qty : 1;
        };

        const getFormulaForSlot = (slotIndex) => {
            return extraFormulas.find(el => String(el.dataset.slotIndex) === String(slotIndex))?.value || '';
        };

        const formatPrice = (raw) => {
            const n = Number(raw);
            if (!Number.isFinite(n)) return '–';
            return `${n.toFixed(2).replace('.', ',')} €`;
        };

        const getPackagingUnitForPricePerStack = (pricePerStackRaw, unitSingular, unitPlural) => {
            const normalized = String(pricePerStackRaw ?? '').trim().replace(',', '.');
            const numericValue = Number(normalized);

            if (Number.isFinite(numericValue)) {
                return numericValue > 1 ? unitPlural : unitSingular;
            }

            return unitSingular;
        };

        const calculateExtraItems = () => {
            const menge = getMainQuantity();

            return extraChecks.map(chk => {
                const slotIndex = chk.dataset.slotIndex || '';
                const formula = getFormulaForSlot(slotIndex);
                const qty = Math.ceil(this.safeEvalFormulaWithFallback(formula, { menge }, menge));
                const unitPlural = chk.dataset.unitPlural || 'Stück';
                const unitSingular = chk.dataset.unitSingular || unitPlural;
                const unitPrice = Number(chk.dataset.unitPrice || 0);
                const productNumberExtra = chk.dataset.productNumber || '';
                const pricePerStack = chk.dataset.pricePerStack || '';
                const row = chk.closest('.lukas-extra-item-row');
                const label = row?.querySelector('.lukas-extra-name')?.textContent?.trim() || 'Zusatzprodukt';

                return {
                    slotIndex,
                    checked: chk.checked,
                    qty,
                    unitPlural,
                    unitSingular,
                    unitPrice,
                    pricePerStack,
                    productNumber: productNumberExtra,
                    label
                };
            });
        };

        const renderExtraItems = () => {
            const items = calculateExtraItems();

            items.forEach(item => {
                const qtyEl = document.getElementById(`lukas-extra-qty-${item.slotIndex}`);
                const unitEl = document.querySelector(`.lukas-extra-item-row[data-slot-index="${item.slotIndex}"] .lukas-extra-unit`);
                const priceEl = document.getElementById(`lukas-extra-price-${item.slotIndex}`);

                const currentUnit = item.qty === 1 ? item.unitSingular : item.unitPlural;
                const packagingUnitForPricePerStack = getPackagingUnitForPricePerStack(
                    item.pricePerStack,
                    item.unitSingular,
                    item.unitPlural
                );

                if (qtyEl) {
                    qtyEl.textContent = item.qty;
                }

                if (unitEl) {
                    unitEl.textContent = currentUnit;
                }

                if (priceEl) {
                    const priceText = formatPrice(item.unitPrice);

                    if (priceText === '–') {
                        priceEl.textContent = '–';
                    } else if (item.pricePerStack) {
                        priceEl.textContent = `${priceText} / ${item.pricePerStack} ${packagingUnitForPricePerStack}`;
                    } else {
                        priceEl.textContent = `${priceText} / ${currentUnit}`;
                    }
                }
            });

            if (btnExtraAdd) {
                btnExtraAdd.disabled = getMainQuantity() <= 0;
            }

            if (btnKontakt) {
                btnKontakt.disabled = getMainQuantity() <= 0;
            }

            return items;
        };

        const berechneExtra = () => renderExtraItems();

        if (quantityInput) {
            quantityInput.addEventListener('input', renderExtraItems);
            quantityInput.addEventListener('change', renderExtraItems);
        }

        extraChecks.forEach(chk => {
            chk.addEventListener('change', renderExtraItems);
        });

        if (btnExtraAdd && btnExtraAdd.dataset.lukasBound !== 'true') {
            btnExtraAdd.dataset.lukasBound = 'true';

            btnExtraAdd.addEventListener('click', async () => {
                if (divError) divError.style.display = 'none';

                const mainQty = getMainQuantity();
                if (mainQty <= 0) return;

                const items = renderExtraItems();

                btnExtraAdd.disabled = true;
                btnExtraAdd.textContent = btnAddingText;

                try {
                    await this.addToCartByProductNumber(productNumber, mainQty);

                    for (const item of items) {
                        if (!item.checked || !item.productNumber) continue;
                        await this.addToCartByProductNumber(item.productNumber, item.qty);
                    }

                    this.setStoredComment(this.buildExtraCommentDOM(productNumber, productName, getMainQuantity));
                    document.querySelector('[data-cart-widget="true"]')?.click();

                    btnExtraAdd.textContent = btnAddText;
                    btnExtraAdd.disabled = false;
                } catch (e) {
                    console.error(e);
                    if (divError) divError.style.display = 'block';
                    btnExtraAdd.textContent = btnAddText;
                    btnExtraAdd.disabled = false;
                }
            });
        }

        if (btnKontakt && kontaktLink) {
            this.initKontaktFlow({
                btnKontakt,
                kontaktLink,
                berechne: berechneExtra,
                buildKonfigurationsText: () => this.buildExtraCommentDOM(productNumber, productName, getMainQuantity)
            });
        }

        renderExtraItems();
        return true;
    }

    initKonfigurator() {
        const c = document.getElementById('lukas-konfigurator');
        if (!c) return;

        const showExtraMode = c.dataset.showExtraMode === 'true';

        const toggleBtn = document.getElementById('lukas-toggle');
        const toggleBody = document.getElementById('lukas-konfigurator-body');

        if (toggleBtn && toggleBody) {
            toggleBtn.addEventListener('click', () => {
                const isOpen = toggleBody.style.display !== 'none';
                toggleBody.style.display = isOpen ? 'none' : 'block';
                toggleBtn.textContent = isOpen
                    ? toggleBtn.textContent.replace('▲', '▼')
                    : toggleBtn.textContent.replace('▼', '▲');
            });
        }

        if (showExtraMode) {
            this.initExtraMode(c);
            return;
        }

        const productNumber = c.dataset.productNumber || '';
        const productName = c.dataset.productName || '';
        const productWidth = Number(c.dataset.productWidth || 0);
        const productShrink = Number(c.dataset.productShrink || 0);
        const unitPlural = c.dataset.unitPlural || 'Stück';
        const fabricFormula = c.dataset.fabricFormula || 'ceil(anzahl * (laenge + 0.1) * (width / 100) * (1 + shrink / 100))';
        const btnAddText = c.dataset.btnAdd || 'In den Warenkorb';
        const btnAddingText = c.dataset.btnAdding || 'Wird hinzugefügt';
        const lukasShaken = c.dataset.lukasShaken === 'true';

        const inputAnzahl = document.getElementById('lukas-anzahl');
        const inputLaenge = document.getElementById('lukas-laenge');
        const mengeDisplay = document.getElementById('lukas-menge-display');
        const zusatzDisplay = document.getElementById('lukas-zusatz-display');

        const radioA1 = document.getElementById('lukas-option-a1');
        const radioA2 = document.getElementById('lukas-option-a2');

        const btnAdd = document.getElementById('lukas-add-to-cart');
        const btnKontakt = document.getElementById('lukas-kontakt-btn');
        const kontaktLink = document.getElementById('lukas-kontakt-link');
        const divError = document.getElementById('lukas-error');

        if (!lukasShaken && radioA1) {
    const p1Row = radioA1.closest('.form-check');
    if (p1Row) p1Row.style.display = 'none';
    if (radioA2) radioA2.checked = true;
}

        const p1s1 = Number(c.dataset.p1Stage1Max || 5);
        const p1s2 = Number(c.dataset.p1Stage2Max || 20);
        const p2s1 = Number(c.dataset.p2Stage1Max || 5);
        const p2s2 = Number(c.dataset.p2Stage2Max || 10);

        const getStage = (len, useP1) => {
            if (useP1) {
                if (len <= p1s1) return 1;
                if (len <= p1s2) return 2;
                return 3;
            }

            if (len <= p2s1) return 1;
            if (len <= p2s2) return 2;
            return 3;
        };

        const getOptionAData = () => {
            const len = Number(inputLaenge?.value || 0);
            const useP1 = !!(radioA1 && radioA1.checked);
            const stage = getStage(len, useP1);
            const prefix = useP1 ? 'p1' : 'p2';

            return {
                stage,
                prefix,
                number: c.dataset[`${prefix}Stage${stage}Number`] || '',
                name: c.dataset[`${prefix}Stage${stage}Name`] || '',
                unit: c.dataset[`${prefix}Stage${stage}UnitPlural`] || unitPlural,
                formula: c.dataset[`${prefix}Stage${stage}Formula`] || 'mengeGewebe'
            };
        };

        const updateOptionAName = () => {
            const d = getOptionAData();

            const activeNum = document.getElementById('lukas-option-a-dynamic-number');
            const activeName = document.getElementById('lukas-option-a-dynamic-name');
            const zusatzUnit = document.getElementById('lukas-zusatz-unit');

            if (activeNum) activeNum.textContent = d.number;
            if (activeName) activeName.textContent = d.name;
            if (zusatzUnit) zusatzUnit.textContent = d.unit;
        };

        const renderOptionBOutput = (items) => {
            const output = document.getElementById('lukas-optionb-dynamic-output');
            if (!output) return;

            output.innerHTML = '';

            items.forEach(item => {
                const p = document.createElement('p');

                if (item.error) {
                    p.className = 'text-danger';
                    p.textContent = `${item.label}: Fehler – ${item.error}`;
                } else {
                    p.textContent = `${item.label}: ${item.qty} ${item.unitPlural}`;
                }

                output.appendChild(p);
            });
        };

        const berechne = () => {
            const anzahl = Number(inputAnzahl?.value || 0);
            const laenge = Number(inputLaenge?.value || 0);

            if (anzahl <= 0 || laenge <= 0) {
                if (mengeDisplay) mengeDisplay.textContent = '-';
                if (zusatzDisplay) zusatzDisplay.textContent = '-';
                renderOptionBOutput([]);

                if (btnAdd) btnAdd.disabled = true;
                if (btnKontakt) btnKontakt.disabled = true;

                return null;
            }

            const optionA = getOptionAData();

            const mengeGewebe = Math.ceil(this.safeEvalFormula(fabricFormula, {
                anzahl,
                laenge,
                width: productWidth,
                shrink: productShrink,
                mengeGewebe: 0,
                mengeZusatz: 0
            }));

            const mengeZusatz = Math.ceil(this.safeEvalFormula(optionA.formula, {
                anzahl,
                laenge,
                mengeGewebe,
                mengeZusatz: mengeGewebe,
                width: productWidth,
                shrink: productShrink
            }));

            if (mengeDisplay) mengeDisplay.textContent = mengeGewebe;
            if (zusatzDisplay) zusatzDisplay.textContent = mengeZusatz;

            const vars = {
                anzahl,
                laenge,
                mengeGewebe,
                mengeZusatz,
                width: productWidth,
                shrink: productShrink
            };

            const optionBItems = [];
            c.querySelectorAll('.lukas-option-b-dynamic').forEach(chk => {
                if (!chk.checked) return;

                const label = c.querySelector(`label[for="${chk.id}"]`)?.textContent.trim() || 'Zubehör';
                const unitPluralB = chk.dataset.unitPlural || 'Stück';
                const formula = c.querySelector(`.lukas-optionb-formula[data-slot-index="${chk.dataset.slotIndex}"]`)?.value || '';

                try {
                    const qty = Math.ceil(this.safeEvalFormula(formula, vars));
                    optionBItems.push({
                        label,
                        qty,
                        unitPlural: unitPluralB,
                        productNumber: chk.dataset.productNumber || ''
                    });
                } catch (e) {
                    optionBItems.push({
                        label,
                        qty: 0,
                        unitPlural: unitPluralB,
                        productNumber: chk.dataset.productNumber || '',
                        error: e.message
                    });
                }
            });

            renderOptionBOutput(optionBItems);

            if (btnAdd) btnAdd.disabled = false;
            if (btnKontakt) btnKontakt.disabled = false;

            return {
                anzahl,
                laenge,
                mengeGewebe,
                mengeZusatz,
                optionA,
                optionBItems
            };
        };

        const buildKonfigurationsText = (result) => {
            const lines = [];
            lines.push(`Produkt: ${productName} (${productNumber})`);
            lines.push(`Anzahl Bahnen: ${result.anzahl}`);
            lines.push(`Laenge Bahnen: ${result.laenge} m`);
            lines.push(`Gewebemenge: ${result.mengeGewebe} ${unitPlural}`);
            lines.push(`Option A: ${result.optionA.name || '–'} / Art.Nr. ${result.optionA.number || '–'} / Menge: ${result.mengeZusatz} ${result.optionA.unit || unitPlural}`);

            result.optionBItems.forEach(item => {
                lines.push(`Zubehoer: ${item.label} / Art.Nr. ${item.productNumber || '–'} / Menge: ${item.qty} ${item.unitPlural}`);
            });

            return lines.join('\n');
        };

        inputAnzahl?.addEventListener('input', () => {
            updateOptionAName();
            berechne();
        });

        inputLaenge?.addEventListener('input', () => {
            updateOptionAName();
            berechne();
        });

        radioA1?.addEventListener('change', () => {
            updateOptionAName();
            berechne();
        });

        radioA2?.addEventListener('change', () => {
            updateOptionAName();
            berechne();
        });

        c.querySelectorAll('.lukas-option-b-dynamic').forEach(chk => {
            chk.addEventListener('change', berechne);
        });

        if (btnAdd && btnAdd.dataset.lukasBound !== 'true') {
            btnAdd.dataset.lukasBound = 'true';

            btnAdd.addEventListener('click', async () => {
                if (divError) divError.style.display = 'none';

                const result = berechne();
                if (!result) return;

                btnAdd.disabled = true;
                btnAdd.textContent = btnAddingText;

                try {
                    await this.addToCartByProductNumber(productNumber, result.mengeGewebe);
                    await this.addToCartByProductNumber(result.optionA.number, result.mengeZusatz);

                    for (const item of result.optionBItems) {
                        if (item.productNumber) {
                            await this.addToCartByProductNumber(item.productNumber, item.qty);
                        }
                    }

                    const neueKonfiguration = buildKonfigurationsText(result);
                    const bisheriger = this.getStoredComment();
                    const nummer = bisheriger ? ((bisheriger.match(/Konfiguration \d+/g) ?? []).length + 1) : 1;

                    const neuerKommentar = bisheriger
                        ? `${bisheriger}\n\nKonfiguration ${nummer}:\n${neueKonfiguration}`
                        : `Konfiguration 1:\n${neueKonfiguration}`;

                    this.setStoredComment(neuerKommentar);

                    document.querySelector('[data-cart-widget="true"]')?.click();

                    btnAdd.textContent = btnAddText;
                    btnAdd.disabled = false;
                } catch (e) {
                    console.error(e);
                    if (divError) divError.style.display = 'block';
                    btnAdd.textContent = btnAddText;
                    btnAdd.disabled = false;
                }
            });
        }

        updateOptionAName();
        berechne();

        if (btnKontakt && kontaktLink) {
            this.initKontaktFlow({
                btnKontakt,
                kontaktLink,
                berechne,
                buildKonfigurationsText
            });
        }
    }
}
