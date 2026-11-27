<?php declare(strict_types=1);

namespace LukasQuantityRule\Core\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Symfony\Component\Validator\Constraints\Choice;

class LukasQuantityPairRuleC extends Rule
{
    final public const RULE_NAME = 'lukasQuantityPairRuleC';
    final public const OPERATOR_EQ  = 'eq';
    final public const OPERATOR_NEQ = 'neq';

    protected string $operator = self::OPERATOR_EQ;

    public function getName(): string
    {
        return self::RULE_NAME;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $items = $scope->getCart()->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $neededLm = 0;
        $sumC     = 0;
        $hasA     = false;
        $hasC     = false;

        foreach ($items as $lineItem) {
            $customFields = $lineItem->getPayloadValue('customFields') ?? [];

            $isA = (bool)($customFields['custom_lukas_quantityA'] ?? false);
            $isC = (bool)($customFields['custom_lukas_quantityC'] ?? false);

            if ($isA) {
                $hasA  = true;
                $qty   = (int) $lineItem->getQuantity();
                $width = (float)($customFields['custom_lukas_width'] ?? 0);

                if ($width > 0) {
                    $neededLm += (int) ceil($qty / $width * 100);
                }
                continue;
            }

            if ($isC) {
                $hasC  = true;
                $sumC += (int) $lineItem->getQuantity();
            }
        }

        // Kein A im Warenkorb → Regel nicht relevant → TRUE
        if (!$hasA) {
            return true;
        }

        // A vorhanden, aber kein C → TRUE (kein C = Bedingung erfüllt)
        if ($hasA && !$hasC) {
            return $this->operator === self::OPERATOR_EQ;
        }

        // A und C vorhanden → Mengen vergleichen
        $result = ($neededLm === $sumC);

        return $this->operator === self::OPERATOR_NEQ ? !$result : $result;
    }

    public function getConstraints(): array
    {
        return [
            'operator' => [
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                ]),
            ],
        ];
    }
}
