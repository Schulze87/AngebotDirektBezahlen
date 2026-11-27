<?php declare(strict_types=1);

namespace LukasQuantityRule\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class KonfiguratorProductSubscriber implements EventSubscriberInterface
{
    private const PRODUCT_NUMBERS = [
        '10550580', '10550581', '10550582',
        '10550570', '10550573', '10550574',
        '10300300', '10500500', '10400400',
    ];

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $languageRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $salesChannelContext = $event->getSalesChannelContext();

        // de-DE Sprach-ID ermitteln
        $langCriteria = new Criteria();
        $langCriteria->addFilter(new EqualsFilter('translationCode.code', 'de-DE'));

        $defaultContext = Context::createDefaultContext();
        $langResult = $this->languageRepository->search($langCriteria, $defaultContext);
        $language = $langResult->first();

        if (!$language) {
            return;
        }

        $deContext = new Context(
            $defaultContext->getSource(),
            $defaultContext->getRuleIds(),
            $defaultContext->getCurrencyId(),
            [$language->getId()],
        );

        // Session-Context für Produktnamen + Einheiten
        $sessionContext = new Context(
            $salesChannelContext->getContext()->getSource(),
            $salesChannelContext->getContext()->getRuleIds(),
            $salesChannelContext->getContext()->getCurrencyId(),
            [$salesChannelContext->getContext()->getLanguageId()],
        );

        // CustomFields des aktuellen Produkts zwingend in de-DE laden
        $productWidth  = null;
        $productShrink = null;
        $lukasShaken   = false;

        $productCriteria = new Criteria([$product->getId()]);
        $deProduct = $this->productRepository->search($productCriteria, $deContext)->first();

        if ($deProduct) {
            $customFields  = $deProduct->getCustomFields() ?? [];
            $productWidth  = $customFields['custom_lukas_width'] ?? null;
            $productShrink = $customFields['custom_lukas_schrumpf'] ?? null;
            $lukasShaken   = $customFields['custom_lukas_shaken'] ?? false;
        }

        // Zubehörprodukte in Session-Sprache laden (Namen + Einheiten)
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productNumber', self::PRODUCT_NUMBERS));
        $criteria->addAssociation('unit');

        $searchResult = $this->productRepository->search($criteria, $sessionContext);

        $mapped = [];
        foreach ($searchResult->getEntities() as $zubehör) {
            $unit = $zubehör->getUnit();
            $mapped[$zubehör->getProductNumber()] = [
                'id'        => $zubehör->getId(),
                'name'      => $zubehör->getName(),
                'number'    => $zubehör->getProductNumber(),
                'unitName'  => $unit ? $unit->getName() : null,
                'unitShort' => $unit ? $unit->getShortCode() : null,
            ];
        }

        // Daten als Extension ans Page-Objekt hängen
        $page->addExtension('lukasKonfigurator', new ArrayStruct([
            'zubehörProdukte' => $mapped,
            'productWidth'    => $productWidth,
            'productShrink'   => $productShrink,
            'lukasShaken'     => $lukasShaken,
        ]));
    }
}
