<?php declare(strict_types=1);

namespace LukasQuantityRule\Core\Cart;

use Shopware\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LukasLineItemPayloadEnricher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $productRepository
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CartLoadedEvent::class => 'onCartLoaded',
        ];
    }

    public function onCartLoaded(CartLoadedEvent $event): void
    {
        $cart    = $event->getCart();
        $context = $event->getSalesChannelContext()->getContext();

        $productLineItems = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        if ($productLineItems->count() === 0) {
            return;
        }

        $productIds = [];
        foreach ($productLineItems as $lineItem) {
            $productIds[] = $lineItem->getReferencedId();
        }

        $criteria = new Criteria(array_values(array_unique(array_filter($productIds))));
        $products = $this->productRepository->search($criteria, $context)->getEntities();

        foreach ($productLineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if (!$productId || !$products->has($productId)) {
                continue;
            }

            $product      = $products->get($productId);
            $customFields = $product?->getCustomFields() ?? [];

            $a     = $this->toBool($customFields['custom_lukas_quantityA'] ?? null);
            $b     = $this->toBool($customFields['custom_lukas_quantityB'] ?? null);
            $c     = $this->toBool($customFields['custom_lukas_quantityC'] ?? null);
            $width = isset($customFields['custom_lukas_width'])
                ? (float) $customFields['custom_lukas_width']
                : 0.0;

            $payloadCustomFields = $lineItem->getPayloadValue('customFields') ?? [];
            if ($payloadCustomFields instanceof ArrayEntity) {
                $payloadCustomFields = $payloadCustomFields->all();
            }

            $payloadCustomFields['custom_lukas_quantityA'] = $a;
            $payloadCustomFields['custom_lukas_quantityB'] = $b;
            $payloadCustomFields['custom_lukas_quantityC'] = $c;
            $payloadCustomFields['custom_lukas_width']     = $width;

            $lineItem->setPayloadValue('customFields', $payloadCustomFields);
        }
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return $value === '1' || strtolower($value) === 'true';
        }
        return false;
    }
}
