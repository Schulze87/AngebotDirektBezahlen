import template from './lukas-quantity-pair-condition.html.twig';

Shopware.Component.register('lukas-quantity-pair-condition', {
    template,
    props: {
        condition: { type: Object, required: true },
    }
});
