<?php declare(strict_types=1);

namespace LukasQuantityRule\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class KonfiguratorCmsElementResolver extends AbstractCmsElementResolver
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $languageRepository,
        private readonly SystemConfigService $systemConfigService
    ) {}

    public function getType(): string
    {
        return 'lukas-konfigurator';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?\Shopware\Core\Content\Cms\DataResolver\CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $context = $resolverContext->getSalesChannelContext()->getContext();

        // Hilfsfunktion: parse config value -> [id|null, number|null]
        $parse = function ($raw) {
            $id = null;
            $number = null;
            if ($raw === null) return [$id, $number];

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    if (!empty($decoded['id'])) $id = (string)$decoded['id'];
                    if (!empty($decoded['number'])) $number = (string)$decoded['number'];
                    if (!empty($decoded['value'])) {
                        $val = (string)$decoded['value'];
                        if (preg_match('/^[0-9a-fA-F-]{36}$/', $val)) $id = $val;
                        else $number = $val;
                    }
                } else {
                    $val = trim($raw);
                    if ($val !== '') {
                        if (preg_match('/^[0-9a-fA-F-]{36}$/', $val)) $id = $val;
                        else $number = $val;
                    }
                }
            } elseif (is_array($raw)) {
                if (!empty($raw['id'])) $id = (string)$raw['id'];
                if (!empty($raw['number'])) $number = (string)$raw['number'];
                if (!empty($raw['value'])) {
                    $val = (string)$raw['value'];
                    if (preg_match('/^[0-9a-fA-F-]{36}$/', $val)) $id = $val;
                    else $number = $val;
                }
            } elseif (is_object($raw)) {
                if (!empty($raw->id)) $id = (string)$raw->id;
                if (!empty($raw->number)) $number = (string)$raw->number;
                if (!empty($raw->value)) {
                    $val = (string)$raw->value;
                    if (preg_match('/^[0-9a-fA-F-]{36}$/', $val)) $id = $val;
                    else $number = $val;
                }
            }

            return [$id, $number];
        };

        // Keys: OptionA (P1/P2), OptionB (5), OptionC (5)
        $optionASlots = [];
        // Option A: P1 and P2 stages (example keys from config.xml)
        $optionASlots['p1'] = [
            'stage1' => $this->systemConfigService->get('LukasQuantityRule.config.optionAP1Stage1ProductId'),
            'stage2' => $this->systemConfigService->get('LukasQuantityRule.config.optionAP1Stage2ProductId'),
            'stage3' => $this->systemConfigService->get('LukasQuantityRule.config.optionAP1Stage3ProductId'),
        ];
        $optionASlots['p2'] = [
            'stage1' => $this->systemConfigService->get('LukasQuantityRule.config.optionAP2Stage1ProductId'),
            'stage2' => $this->systemConfigService->get('LukasQuantityRule.config.optionAP2Stage2ProductId'),
            'stage3' => $this->systemConfigService->get('LukasQuantityRule.config.optionAP2Stage3ProductId'),
        ];

        // Option B and C: 5 slots each
        $collectSlots = function (string $prefix) use ($parse) {
            $slots = [];
            for ($i = 1; $i <= 5; $i++) {
                $key = sprintf('LukasQuantityRule.config.%sSlot%dProductId', $prefix, $i);
                $altKey = sprintf('LukasQuantityRule.config.%sSlot%d', $prefix, $i);
                $raw = $this->systemConfigService->get($key);
                if ($raw === null) $raw = $this->systemConfigService->get($altKey);
                [$id, $number] = $parse($raw);
                $slots[$i] = [
                    'slotIndex' => $i,
                    'productId' => $id,
                    'productNumberFromConfig' => $number,
                    'productNumber' => null,
                    'productName' => null,
                    'slotRaw' => $raw,
                ];
            }
            return $slots;
        };

        $optionBSlots = $collectSlots('optionB');
        $optionCSlots = $collectSlots('optionC');

        // Sammle IDs und Nummern zum Laden
        $ids = [];
        $numbers = [];
        foreach (array_merge($optionBSlots, $optionCSlots) as $meta) {
            if (!empty($meta['productId'])) $ids[] = $meta['productId'];
            if (!empty($meta['productNumberFromConfig'])) $numbers[] = $meta['productNumberFromConfig'];
        }
        $ids = array_values(array_unique($ids));
        $numbers = array_values(array_unique($numbers));

        $mappedById = [];
        $mappedByNumber = [];

        if (!empty($ids)) {
            $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('id', $ids));
            $products = $this->productRepository->search($criteria, $context)->getEntities();
            /** @var ProductEntity $p */
            foreach ($products as $p) {
                $entry = [
                    'id' => $p->getId(),
                    'number' => $p->getProductNumber(),
                    'name' => $p->getTranslation('name') ?? $p->getName(),
                ];
                $mappedById[$p->getId()] = $entry;
                $mappedByNumber[$p->getProductNumber()] = $entry;
            }
        }

        if (!empty($numbers)) {
            $toQuery = array_filter($numbers, fn($n) => !isset($mappedByNumber[$n]));
            if (!empty($toQuery)) {
                $criteria2 = (new Criteria())->addFilter(new EqualsAnyFilter('productNumber', array_values($toQuery)));
                $products2 = $this->productRepository->search($criteria2, $context)->getEntities();
                foreach ($products2 as $p) {
                    $entry = [
                        'id' => $p->getId(),
                        'number' => $p->getProductNumber(),
                        'name' => $p->getTranslation('name') ?? $p->getName(),
                    ];
                    $mappedById[$p->getId()] = $entry;
                    $mappedByNumber[$p->getProductNumber()] = $entry;
                }
            }
        }

        // Fülle productNumber/productName in slots
        foreach ($optionBSlots as $i => $meta) {
            if (!empty($meta['productId']) && isset($mappedById[$meta['productId']])) {
                $optionBSlots[$i]['productNumber'] = $mappedById[$meta['productId']]['number'];
                $optionBSlots[$i]['productName'] = $mappedById[$meta['productId']]['name'];
            } elseif (!empty($meta['productNumberFromConfig']) && isset($mappedByNumber[$meta['productNumberFromConfig']])) {
                $optionBSlots[$i]['productNumber'] = $mappedByNumber[$meta['productNumberFromConfig']]['number'];
                $optionBSlots[$i]['productName'] = $mappedByNumber[$meta['productNumberFromConfig']]['name'];
            } elseif (!empty($meta['productNumberFromConfig'])) {
                $optionBSlots[$i]['productNumber'] = $meta['productNumberFromConfig'];
            }
        }

        foreach ($optionCSlots as $i => $meta) {
            if (!empty($meta['productId']) && isset($mappedById[$meta['productId']])) {
                $optionCSlots[$i]['productNumber'] = $mappedById[$meta['productId']]['number'];
                $optionCSlots[$i]['productName'] = $mappedById[$meta['productId']]['name'];
            } elseif (!empty($meta['productNumberFromConfig']) && isset($mappedByNumber[$meta['productNumberFromConfig']])) {
                $optionCSlots[$i]['productNumber'] = $mappedByNumber[$meta['productNumberFromConfig']]['number'];
                $optionCSlots[$i]['productName'] = $mappedByNumber[$meta['productNumberFromConfig']]['name'];
            } elseif (!empty($meta['productNumberFromConfig'])) {
                $optionCSlots[$i]['productNumber'] = $meta['productNumberFromConfig'];
            }
        }

        // Option A: nur ProduktIds aus config parsen (falls vorhanden), Name-Auflösung optional
        $optionAResolved = [];
        foreach ($optionASlots as $group => $stages) {
            $optionAResolved[$group] = [];
            foreach ($stages as $stageKey => $raw) {
                [$id, $number] = $parse($raw);
                $name = null;
                if ($id !== null && isset($mappedById[$id])) $name = $mappedById[$id]['name'];
                elseif ($number !== null && isset($mappedByNumber[$number])) $name = $mappedByNumber[$number]['name'];
                $optionAResolved[$group][$stageKey] = [
                    'productId' => $id,
                    'productNumber' => $number,
                    'productName' => $name,
                    'raw' => $raw,
                ];
            }
        }

        // Setze element.data
        $slot->setData(new ArrayStruct([
            'optionA' => $optionAResolved,
            'optionBSlots' => array_values($optionBSlots),
            'optionCSlots' => array_values($optionCSlots),
            'productsById' => $mappedById,
            'productsByNumber' => $mappedByNumber,
        ]));
    }
}
