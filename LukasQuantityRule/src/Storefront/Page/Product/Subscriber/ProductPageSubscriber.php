<?php declare(strict_types=1);

namespace LukasQuantityRule\Storefront\Page\Product\Subscriber;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Unit\UnitEntity;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $unitRepository
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
        $salesChannelContext = $event->getSalesChannelContext();
        $page = $event->getPage();
        $mainProduct = $page->getProduct();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $productCustomFields = $mainProduct->getTranslated()['customFields'] ?? $mainProduct->getCustomFields() ?? [];

        $fixUnit = function (?ProductEntity $p) use ($context) {
            if (!$p) {
                return null;
            }

            if ($p->getUnit() instanceof UnitEntity) {
                return $p->getUnit();
            }

            $packUnit = $p->getPackUnit();
            if (!$packUnit) {
                return null;
            }

            $short = strtolower(substr($packUnit, 0, 2));

            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('name', $packUnit));

            $existing = $this->unitRepository->search($criteria, $context)->first();

            if ($existing instanceof UnitEntity) {
                $this->productRepository->update([[
                    'id' => $p->getId(),
                    'unitId' => $existing->getId(),
                ]], $context);

                return $existing;
            }

            $newId = Uuid::randomHex();

            $this->unitRepository->create([[
                'id' => $newId,
                'name' => $packUnit,
                'shortCode' => strtolower(substr($packUnit, 0, 2)),
                'packUnit' => $packUnit,
            ]], $context);

            $this->productRepository->update([[
                'id' => $p->getId(),
                'unitId' => $newId,
            ]], $context);

            $reloaded = $this->unitRepository->search(new Criteria([$newId]), $context)->first();

            return $reloaded instanceof UnitEntity ? $reloaded : null;
        };

        $fixedMainUnit = $fixUnit($mainProduct);
        if ($fixedMainUnit instanceof UnitEntity) {
            $mainProduct->setUnit($fixedMainUnit);
        }

        $normalizeFormula = static function ($raw, ?string $default = null): ?string {
            if (!is_string($raw)) {
                return $default;
            }

            $formula = trim($raw);

            return $formula !== '' ? $formula : $default;
        };

        $toNullableFloat = static function ($raw): ?float {
            if ($raw === null || $raw === '') {
                return null;
            }

            if (is_string($raw)) {
                $raw = str_replace(',', '.', trim($raw));
            }

            if (!is_numeric($raw)) {
                return null;
            }

            return (float) $raw;
        };

        $getPricePerStack = static function (?SalesChannelProductEntity $p): ?string {
            if (!$p) {
                return null;
            }

            $translatedCustomFields = $p->getTranslated()['customFields'] ?? [];
            $customFields = $p->getCustomFields() ?? [];

            $value = $translatedCustomFields['custom_product_information_price_per_stack']
                ?? $customFields['custom_product_information_price_per_stack']
                ?? null;

            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        };

        $fabricFormula = $normalizeFormula(
            $this->systemConfigService->get('LukasQuantityRule.config.fabricFormula', $salesChannelId),
            'ceil(anzahl * (laenge + 0.1) * (width / 100) * (1 + shrink / 100))'
        );

        $optionAKeys = [
            'p1' => [
                'stage1' => [
                    'productKey' => 'LukasQuantityRule.config.optionAP1Stage1ProductId',
                    'maxKey' => 'LukasQuantityRule.config.optionAP1Stage1Max',
                    'formulaKey' => 'LukasQuantityRule.config.optionAP1Stage1Formula',
                ],
                'stage2' => [
                    'productKey' => 'LukasQuantityRule.config.optionAP1Stage2ProductId',
                    'maxKey' => 'LukasQuantityRule.config.optionAP1Stage2Max',
                    'formulaKey' => 'LukasQuantityRule.config.optionAP1Stage2Formula',
                ],
                'stage3' => [
                    'productKey' => 'LukasQuantityRule.config.optionAP1Stage3ProductId',
                    'maxKey' => null,
                    'formulaKey' => 'LukasQuantityRule.config.optionAP1Stage3Formula',
                ],
            ],
            'p2' => [
                'stage1' => [
                    'productKey' => 'LukasQuantityRule.config.optionAP2Stage1ProductId',
                    'maxKey' => 'LukasQuantityRule.config.optionAP2Stage1Max',
                    'formulaKey' => 'LukasQuantityRule.config.optionAP2Stage1Formula',
                ],
                'stage2' => [
                    'productKey' => 'LukasQuantityRule.config.optionAP2Stage2ProductId',
                    'maxKey' => 'LukasQuantityRule.config.optionAP2Stage2Max',
                    'formulaKey' => 'LukasQuantityRule.config.optionAP2Stage2Formula',
                ],
                'stage3' => [
                    'productKey' => 'LukasQuantityRule.config.optionAP2Stage3ProductId',
                    'maxKey' => null,
                    'formulaKey' => 'LukasQuantityRule.config.optionAP2Stage3Formula',
                ],
            ],
        ];

        $optionBKeys = [];
        for ($i = 1; $i <= 5; $i++) {
            $optionBKeys[$i] = [
                'primary' => "LukasQuantityRule.config.optionBSlot{$i}ProductId",
                'alt' => "LukasQuantityRule.config.optionBSlot{$i}",
                'formula' => "LukasQuantityRule.config.optionBSlot{$i}Formula",
            ];
        }

        $parse = function ($raw): array {
            $productId = null;
            $productNumber = null;

            $treatAsId = static function (string $val): bool {
                return (bool) preg_match('/^[0-9a-fA-F]{32}$/', $val)
                    || (bool) preg_match('/^[0-9a-fA-F-]{36}$/', $val);
            };

            if ($raw === null) {
                return [$productId, $productNumber];
            }

            if (is_array($raw)) {
                if (!empty($raw['id'])) {
                    $val = (string) $raw['id'];
                    if ($treatAsId($val)) {
                        $productId = $val;
                    } else {
                        $productNumber = $val;
                    }
                }

                if (!empty($raw['number'])) {
                    $productNumber = (string) $raw['number'];
                }

                if (!empty($raw['value'])) {
                    $val = (string) $raw['value'];
                    if ($treatAsId($val)) {
                        $productId = $val;
                    } else {
                        $productNumber = $val;
                    }
                }

                return [$productId, $productNumber];
            }

            if (is_object($raw)) {
                if (!empty($raw->id)) {
                    $val = (string) $raw->id;
                    if ($treatAsId($val)) {
                        $productId = $val;
                    } else {
                        $productNumber = $val;
                    }
                }

                if (!empty($raw->number)) {
                    $productNumber = (string) $raw->number;
                }

                if (!empty($raw->value)) {
                    $val = (string) $raw->value;
                    if ($treatAsId($val)) {
                        $productId = $val;
                    } else {
                        $productNumber = $val;
                    }
                }

                return [$productId, $productNumber];
            }

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    if (!empty($decoded['id'])) {
                        $val = (string) $decoded['id'];
                        if ($treatAsId($val)) {
                            $productId = $val;
                        } else {
                            $productNumber = $val;
                        }
                    }

                    if (!empty($decoded['number'])) {
                        $productNumber = (string) $decoded['number'];
                    }

                    if (!empty($decoded['value'])) {
                        $val = (string) $decoded['value'];
                        if ($treatAsId($val)) {
                            $productId = $val;
                        } else {
                            $productNumber = $val;
                        }
                    }

                    return [$productId, $productNumber];
                }

                $val = trim($raw);
                if ($val !== '') {
                    if ($treatAsId($val)) {
                        $productId = $val;
                    } else {
                        $productNumber = $val;
                    }
                }
            }

            return [$productId, $productNumber];
        };

        $optionAMeta = [];
        $optionBMeta = [];
        $extraMeta = [];
        $ids = [];
        $numbers = [];

        foreach ($optionAKeys as $group => $stages) {
            $optionAMeta[$group] = [];

            foreach ($stages as $stage => $config) {
                $raw = $this->systemConfigService->get($config['productKey'], $salesChannelId);
                [$id, $num] = $parse($raw);

                $max = null;
                if (!empty($config['maxKey'])) {
                    $max = $toNullableFloat($this->systemConfigService->get($config['maxKey'], $salesChannelId));
                }

                $formula = $normalizeFormula(
                    $this->systemConfigService->get($config['formulaKey'], $salesChannelId),
                    'mengeGewebe'
                );

                $optionAMeta[$group][$stage] = [
                    'productId' => $id,
                    'productNumber' => $num,
                    'max' => $max,
                    'formula' => $formula,
                ];

                if ($id) {
                    $ids[] = $id;
                }

                if ($num) {
                    $numbers[] = $num;
                }
            }
        }

        foreach ($optionBKeys as $i => $keys) {
            $raw = $this->systemConfigService->get($keys['primary'], $salesChannelId);

            if ($raw === null) {
                $raw = $this->systemConfigService->get($keys['alt'], $salesChannelId);
            }

            [$id, $num] = $parse($raw);

            $optionBMeta[$i] = [
                'slotIndex' => $i,
                'productId' => $id,
                'productNumber' => $num,
                'formula' => $normalizeFormula(
                    $this->systemConfigService->get($keys['formula'], $salesChannelId)
                ),
            ];

            if ($id) {
                $ids[] = $id;
            }

            if ($num) {
                $numbers[] = $num;
            }
        }

        for ($i = 1; $i <= 3; $i++) {
            $rawProduct = $productCustomFields['custom_lukas_extra_product_' . $i] ?? null;
            $rawFormula = $productCustomFields['custom_lukas_extra_formula_' . $i] ?? null;

            [$id, $num] = $parse($rawProduct);

            $extraMeta[$i] = [
                'slotIndex' => $i,
                'productId' => $id,
                'productNumber' => $num,
                'formula' => $normalizeFormula($rawFormula),
            ];

            if ($id) {
                $ids[] = $id;
            }

            if ($num) {
                $numbers[] = $num;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        $numbers = array_values(array_unique(array_filter($numbers)));

        /** @var array<string, SalesChannelProductEntity> $mappedById */
        $mappedById = [];
        /** @var array<string, SalesChannelProductEntity> $mappedByNumber */
        $mappedByNumber = [];

        if (!empty($ids)) {
            $criteria = new Criteria($ids);
            $criteria->addAssociation('unit');
            $criteria->addAssociation('deliveryTime');
            $criteria->addAssociation('cover.media');
            $criteria->addAssociation('tax');

            $products = $this->salesChannelProductRepository->search($criteria, $salesChannelContext)->getEntities();

            foreach ($products as $p) {
                if (!$p instanceof SalesChannelProductEntity) {
                    continue;
                }

                $mappedById[$p->getId()] = $p;
                $mappedByNumber[$p->getProductNumber()] = $p;
            }
        }

        $numbersToQuery = array_filter($numbers, static fn ($n) => !isset($mappedByNumber[$n]));

        if (!empty($numbersToQuery)) {
            $criteria2 = new Criteria();
            $criteria2->addFilter(new EqualsAnyFilter('productNumber', array_values($numbersToQuery)));
            $criteria2->addAssociation('unit');
            $criteria2->addAssociation('deliveryTime');
            $criteria2->addAssociation('cover.media');
            $criteria2->addAssociation('tax');

            $products2 = $this->salesChannelProductRepository->search($criteria2, $salesChannelContext)->getEntities();

            foreach ($products2 as $p) {
                if (!$p instanceof SalesChannelProductEntity) {
                    continue;
                }

                $mappedById[$p->getId()] = $p;
                $mappedByNumber[$p->getProductNumber()] = $p;
            }
        }

        $getUnit = function (?SalesChannelProductEntity $p): array {
            if (!$p) {
                return [
                    'singular' => 'Stück',
                    'plural' => 'Stück',
                    'short' => 'Stk',
                ];
            }

            $unit = $p->getUnit();

            return [
                'singular' => $unit?->getName() ?? 'Stück',
                'plural' => $p->getPackUnitPlural() ?? $unit?->getName() ?? 'Stück',
                'short' => $unit?->getShortCode() ?? 'Stk',
            ];
        };

        $serializePrice = static function (?SalesChannelProductEntity $p): array {
            if (!$p) {
                return [
                    'unitPrice' => null,
                    'totalPrice' => null,
                    'quantity' => null,
                    'referencePrice' => null,
                    'listPrice' => null,
                    'regulationPrice' => null,
                ];
            }

            $displayPrice = null;
            $calculatedPrices = $p->getCalculatedPrices();

            if ($calculatedPrices && $calculatedPrices->count() === 1) {
                $displayPrice = $calculatedPrices->first();
            } else {
                $displayPrice = $p->getCalculatedPrice();
            }

            if (!$displayPrice) {
                return [
                    'unitPrice' => null,
                    'totalPrice' => null,
                    'quantity' => null,
                    'referencePrice' => null,
                    'listPrice' => null,
                    'regulationPrice' => null,
                ];
            }

            $referencePrice = $displayPrice->getReferencePrice();
            $listPrice = $displayPrice->getListPrice();
            $regulationPrice = $displayPrice->getRegulationPrice();

            return [
                'unitPrice' => $displayPrice->getUnitPrice(),
                'totalPrice' => $displayPrice->getTotalPrice(),
                'quantity' => $displayPrice->getQuantity(),
                'referencePrice' => $referencePrice ? [
                    'price' => $referencePrice->getPrice(),
                    'referenceUnit' => $referencePrice->getReferenceUnit(),
                    'unitName' => $referencePrice->getUnitName(),
                ] : null,
                'listPrice' => $listPrice ? [
                    'price' => $listPrice->getPrice(),
                    'percentage' => $listPrice->getPercentage(),
                    'discount' => $listPrice->getDiscount(),
                ] : null,
                'regulationPrice' => $regulationPrice ? [
                    'price' => $regulationPrice->getPrice(),
                ] : null,
            ];
        };

        $getUnitPrice = static function (?SalesChannelProductEntity $p): ?float {
            if (!$p) {
                return null;
            }

            $calculatedPrices = $p->getCalculatedPrices();

            if ($calculatedPrices && $calculatedPrices->count() === 1) {
                $first = $calculatedPrices->first();

                return $first ? $first->getUnitPrice() : null;
            }

            $calculatedPrice = $p->getCalculatedPrice();

            return $calculatedPrice ? $calculatedPrice->getUnitPrice() : null;
        };

        $optionAResolved = [];

        foreach ($optionAMeta as $group => $stages) {
            $optionAResolved[$group] = [];

            foreach ($stages as $stage => $info) {
                $p = null;

                if ($info['productId'] && isset($mappedById[$info['productId']])) {
                    $p = $mappedById[$info['productId']];
                } elseif ($info['productNumber'] && isset($mappedByNumber[$info['productNumber']])) {
                    $p = $mappedByNumber[$info['productNumber']];
                }

                $unitData = $getUnit($p);

                $optionAResolved[$group][$stage] = [
                    'max' => $info['max'],
                    'formula' => $info['formula'],
                    'productId' => $p?->getId() ?? $info['productId'],
                    'productNumber' => $p?->getProductNumber() ?? $info['productNumber'],
                    'productName' => $p?->getTranslation('name') ?? $p?->getName(),
                    'unit' => $unitData,
                    'unitPlural' => $unitData['plural'],
                    'packUnit' => $p?->getPackUnit(),
                    'purchaseUnit' => $p?->getPurchaseUnit(),
                    'pricePerStack' => $getPricePerStack($p),
                    'price' => $serializePrice($p),
                    'unitPrice' => $getUnitPrice($p),
                ];
            }
        }

        $optionBResolved = [];

        for ($i = 1; $i <= 5; $i++) {
            $info = $optionBMeta[$i];
            $p = null;

            if ($info['productId'] && isset($mappedById[$info['productId']])) {
                $p = $mappedById[$info['productId']];
            } elseif ($info['productNumber'] && isset($mappedByNumber[$info['productNumber']])) {
                $p = $mappedByNumber[$info['productNumber']];
            }

            if ($p) {
                $unitData = $getUnit($p);

                $optionBResolved[] = [
                    'slotIndex' => $i,
                    'productId' => $p->getId(),
                    'productNumber' => $p->getProductNumber(),
                    'productNumberResolved' => $p->getProductNumber(),
                    'productName' => $p->getTranslation('name') ?? $p->getName(),
                    'formula' => $info['formula'],
                    'unit' => $unitData,
                    'unitPlural' => $unitData['plural'],
                    'packUnit' => $p->getPackUnit(),
                    'purchaseUnit' => $p->getPurchaseUnit(),
                    'pricePerStack' => $getPricePerStack($p),
                    'price' => $serializePrice($p),
                    'unitPrice' => $getUnitPrice($p),
                ];
            } else {
                $optionBResolved[] = [
                    'slotIndex' => $i,
                    'productId' => null,
                    'productNumber' => $info['productNumber'],
                    'productNumberResolved' => $info['productNumber'],
                    'productName' => null,
                    'formula' => $info['formula'],
                    'unit' => [
                        'singular' => 'Stück',
                        'plural' => 'Stück',
                        'short' => 'Stk',
                    ],
                    'unitPlural' => 'Stück',
                    'packUnit' => null,
                    'purchaseUnit' => null,
                    'pricePerStack' => null,
                    'price' => [
                        'unitPrice' => null,
                        'totalPrice' => null,
                        'quantity' => null,
                        'referencePrice' => null,
                        'listPrice' => null,
                        'regulationPrice' => null,
                    ],
                    'unitPrice' => null,
                ];
            }
        }

        $extraResolved = [];
        $extraModeActive = false;

        for ($i = 1; $i <= 3; $i++) {
            $info = $extraMeta[$i];
            $p = null;

            if ($info['productId'] && isset($mappedById[$info['productId']])) {
                $p = $mappedById[$info['productId']];
            } elseif ($info['productNumber'] && isset($mappedByNumber[$info['productNumber']])) {
                $p = $mappedByNumber[$info['productNumber']];
            }

            if (!$p && !$info['productId'] && !$info['productNumber']) {
                continue;
            }

            $unitData = $getUnit($p);

$cover = $p?->getCover();
$media = $cover?->getMedia();

$entry = [
    'slotIndex' => $i,
    'productId' => $p?->getId() ?? $info['productId'],
    'productNumber' => $p?->getProductNumber() ?? $info['productNumber'],
    'productNumberResolved' => $p?->getProductNumber() ?? $info['productNumber'],
    'productName' => $p?->getTranslation('name') ?? $p?->getName(),
    'formula' => $info['formula'],
    'unit' => $unitData,
    'unitPlural' => $unitData['plural'],
    'packUnit' => $p?->getPackUnit(),
    'purchaseUnit' => $p?->getPurchaseUnit(),
    'pricePerStack' => $getPricePerStack($p),
    'price' => $serializePrice($p),
    'unitPrice' => $getUnitPrice($p),
    'calculationVariable' => 'menge',

    // ✔ KORREKTE COVER-DATEN
    'cover' => $media ? [
        'mediaId' => $media->getId(),
        'url' => $media->getUrl(),
        'alt' => $media->getAlt(),
        'title' => $media->getTitle(),
    ] : null,
];

            if ($entry['productId'] || $entry['productNumber']) {
                $extraModeActive = true;
            }

            $extraResolved[] = $entry;
        }

        $newData = [
            'fabricFormula' => $fabricFormula,
            'optionA' => $optionAResolved,
            'optionB' => $optionBResolved,
            'extraMode' => [
                'active' => $extraModeActive,
                'hintSnippet' => 'custom.lukas.hint.produktdetail',
                'items' => $extraResolved,
            ],
        ];

        $existingKonfigurator = $page->getExtension('lukasKonfigurator');
        $mergedKonfigurator = $existingKonfigurator instanceof ArrayStruct ? $existingKonfigurator->getVars() : [];

        $mergedKonfigurator['fabricFormula'] = $newData['fabricFormula'];
        $mergedKonfigurator['optionA'] = $newData['optionA'];
        $mergedKonfigurator['extraMode'] = $newData['extraMode'];

        $existingOptionB = $mergedKonfigurator['optionB'] ?? [];
        $byIndex = [];

        if (is_array($existingOptionB)) {
            foreach ($existingOptionB as $entry) {
                if (is_array($entry) && isset($entry['slotIndex'])) {
                    $byIndex[(int) $entry['slotIndex']] = $entry;
                }
            }
        }

        foreach ($newData['optionB'] as $entry) {
            $slotIndex = (int) $entry['slotIndex'];
            $byIndex[$slotIndex] = array_merge($byIndex[$slotIndex] ?? [], $entry);
        }

        ksort($byIndex);
        $mergedKonfigurator['optionB'] = array_values($byIndex);

        $page->addExtension('lukasKonfigurator', new ArrayStruct($mergedKonfigurator));
        $page->addExtension('lukasQuantityRule', new ArrayStruct($newData));
    }
}
