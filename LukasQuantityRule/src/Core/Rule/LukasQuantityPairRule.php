<?php declare(strict_types=1);

namespace LukasQuantityRule\Core\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Symfony\Component\Validator\Constraints\Choice;

class LukasQuantityPairRule extends Rule
{
    final public const RULE_NAME = 'lukasQuantityPairRule';
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

        $hasA = false;
        $hasB = false;
        $hasC = false;

        foreach ($items as $lineItem) {
            $customFields = $lineItem->getPayloadValue('customFields') ?? [];

            if (!empty($customFields['custom_lukas_quantityA'])) $hasA = true;
            if (!empty($customFields['custom_lukas_quantityB'])) $hasB = true;
            if (!empty($customFields['custom_lukas_quantityC'])) $hasC = true;
        }

        // Keines der drei → nicht relevant → true
        if (!$hasA && !$hasB && !$hasC) {
            return $this->operator === self::OPERATOR_EQ;
        }

        // Gültige Kombinationen: A+B, A+C, A+B+C
        // Ungültig: A allein, B allein, C allein, B+C ohne A
        $result = $hasA && ($hasB || $hasC);

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
