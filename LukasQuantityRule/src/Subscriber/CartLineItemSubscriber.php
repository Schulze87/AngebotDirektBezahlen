<?php declare(strict_types=1);

namespace LukasQuantityRule\Subscriber;

use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartLineItemSubscriber implements EventSubscriberInterface
{
    private const LOCKED_PRODUCT_NUMBERS = ['10550580', '10550570', '10300300'];

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class    => 'onCartLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onCartLoaded',
            OffcanvasCartPageLoadedEvent::class   => 'onCartLoaded',
        ];
    }

    public function onCartLoaded(mixed $event): void
    {
        $cart = $event->getPage()->getCart();

        foreach ($cart->getLineItems() as $lineItem) {
            $payload = $lineItem->getPayload();
            $productNumber = $payload['productNumber'] ?? null;

            if (in_array($productNumber, self::LOCKED_PRODUCT_NUMBERS, true)) {
                $lineItem->setPayload(array_merge($payload, ['lukasQuantityLocked' => true]));
            }
        }
    }
}
