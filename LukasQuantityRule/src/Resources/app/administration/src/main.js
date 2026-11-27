// ============================================================
// Regel A/B/C: Gültige Kombination im Warenkorb
// ============================================================
Shopware.Component.register('lukas-quantity-pair-condition', {
    template: `
        <div class="lukas-quantity-pair-condition">
            <sw-select-field
                v-model="condition.value.operator"
                :options="operatorOptions"
                label="Operator">
            </sw-select-field>
        </div>
    `,
    props: {
        condition: { type: Object, required: true },
    },
    data() {
        return {
            operatorOptions: [
                { value: 'eq',  label: 'A+B oder A+C im Korb (erfüllt)' },
                { value: 'neq', label: 'A+B oder A+C im Korb (nicht erfüllt)' },
            ],
        };
    },
    created() {
        if (!this.condition.value) this.condition.value = { operator: 'eq' };
        if (!this.condition.value.operator) this.condition.value.operator = 'eq';
    },
});

Shopware.Service('ruleConditionDataProviderService').addCondition('lukasQuantityPairRule', {
    component: 'lukas-quantity-pair-condition',
    label: 'Lukas: Gültige Produktkombination (A+B / A+C)',
    scopes: ['global', 'cart'],
    group: 'general',
});

// ============================================================
// Regel A/C: Laufmeter C = berechnete Laufmeter aus A
// ============================================================
Shopware.Component.register('lukas-quantity-pair-condition-c', {
    template: `
        <div class="lukas-quantity-pair-condition-c">
            <sw-select-field
                v-model="condition.value.operator"
                :options="operatorOptions"
                label="Operator">
            </sw-select-field>
        </div>
    `,
    props: {
        condition: { type: Object, required: true },
    },
    data() {
        return {
            operatorOptions: [
                { value: 'eq',  label: 'Laufmeter C = berechnete Laufmeter aus A (erfüllt)' },
                { value: 'neq', label: 'Laufmeter C ≠ berechnete Laufmeter aus A (erfüllt)' },
            ],
        };
    },
    created() {
        if (!this.condition.value) this.condition.value = { operator: 'eq' };
        if (!this.condition.value.operator) this.condition.value.operator = 'eq';
    },
});

Shopware.Service('ruleConditionDataProviderService').addCondition('lukasQuantityPairRuleC', {
    component: 'lukas-quantity-pair-condition-c',
    label: 'Lukas: Laufmeter C = berechnete Laufmeter aus A',
    scopes: ['global', 'cart'],
    group: 'general',
});


// ============================================================
// CMS Element: Lukas Konfigurator
// ============================================================
Shopware.Component.register('sw-cms-el-lukas-konfigurator', {
    template: `<div>Lukas Konfigurator</div>`
});

Shopware.Service('cmsService').registerCmsElement({
    name: 'lukas-konfigurator',
    label: 'Lukas Konfigurator',
    component: 'sw-cms-el-lukas-konfigurator',
    previewComponent: 'sw-cms-el-lukas-konfigurator',
    defaultConfig: {}
});
// src/Resources/app/administration/src/main.js

const { Component } = Shopware;

// Vorschau in der Block-Auswahl
Component.register('sw-cms-preview-lukas-4columns', {
    template: `
        <div class="sw-cms-preview-lukas-4columns" style="display:flex;gap:8px;">
            <div style="flex:1;padding:8px;background:#f7f7f8;border:1px solid #ddd;text-align:center;font-size:12px;">1</div>
            <div style="flex:1;padding:8px;background:#f7f7f8;border:1px solid #ddd;text-align:center;font-size:12px;">2</div>
            <div style="flex:1;padding:8px;background:#f7f7f8;border:1px solid #ddd;text-align:center;font-size:12px;">3</div>
            <div style="flex:1;padding:8px;background:#f7f7f8;border:1px solid #ddd;text-align:center;font-size:12px;">4</div>
        </div>
    `
});

// Block-Komponente im Layout-Editor
Component.register('sw-cms-block-lukas-4columns', {
    template: `
        <div class="sw-cms-block-lukas-4columns" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
            <slot name="column1"></slot>
            <slot name="column2"></slot>
            <slot name="column3"></slot>
            <slot name="column4"></slot>
        </div>
    `
});

// Block registrieren
Shopware.Service('cmsService').registerCmsBlock({
    name: 'lukas-4columns',
    label: 'Lukas 4 Spalten',
    category: 'text',
    component: 'sw-cms-block-lukas-4columns',
    previewComponent: 'sw-cms-preview-lukas-4columns',
    defaultConfig: {
        marginTop: '20px',
        marginBottom: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        column1: 'text',
        column2: 'text',
        column3: 'text',
        column4: 'text'
    }
});
