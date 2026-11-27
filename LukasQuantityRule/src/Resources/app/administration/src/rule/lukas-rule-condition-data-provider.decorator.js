Shopware.Service('ruleConditionDataProviderService').addCondition('lukasQuantityPairRule', {
    component: 'lukas-quantity-pair-condition',
    label: {
        'de-DE': 'Lukas: Summe Menge(A) = Summe Menge(B)',
        'en-GB': 'Lukas: Sum qty(A) = Sum qty(B)'
    },
    scopes: ['cart']
});
