<?php declare(strict_types=1);

namespace YourPlugin\Struct;

use Shopware\Core\Framework\Struct\Struct;

class LukasKonfiguratorConfigStruct extends Struct
{
    /**
     * @var array<int, array{id: string, productNumber: string, name: string}>
     */
    protected array $optionBProducts = [];

    protected string $optionBFormulasByProductNumberJson = '{}';

    public function getOptionBProducts(): array
    {
        return $this->optionBProducts;
    }

    public function setOptionBProducts(array $optionBProducts): void
    {
        $this->optionBProducts = $optionBProducts;
    }

    public function getOptionBFormulasByProductNumberJson(): string
    {
        return $this->optionBFormulasByProductNumberJson;
    }

    public function setOptionBFormulasByProductNumberJson(string $json): void
    {
        $this->optionBFormulasByProductNumberJson = $json;
    }
}
