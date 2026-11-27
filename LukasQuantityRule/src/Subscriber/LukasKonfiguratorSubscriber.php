<?php declare(strict_types=1);

namespace LukasQuantityRule\Subscriber;

use YourPlugin\Struct\LukasKonfiguratorConfigStruct;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LukasKonfiguratorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $productRepository,
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
        $context = $event->getContext();

        // global config => salesChannelId = null
        $productIds = $this->systemConfigService->get('YourPluginName.config.optionB.productIds', null) ?? [];
        $formulasJson = (string) ($this->systemConfigService->get('YourPluginName.config.optionB.formulasByProductNumber', null) ?? '{}');

        if (!\is_array($productIds) || $productIds === []) {
            return;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('id', $productIds));

        $result = $this->productRepository->search($criteria, $context);

        $products = [];
        /** @var ProductEntity $p */
        foreach ($result->getEntities() as $p) {
            if (!$p->getProductNumber() || !$p->getTranslation('name')) {
                continue;
            }

            $products[] = [
                'id' => $p->getId(),
                'productNumber' => $p->getProductNumber(),
                'name' => (string) $p->getTranslation('name'),
            ];
        }

        $struct = new LukasKonfiguratorConfigStruct();
        $struct->setOptionBProducts($products);
        $struct->setOptionBFormulasByProductNumberJson($formulasJson);

        $event->getPage()->addExtension('lukasKonfiguratorConfig', $struct);
    }
}
