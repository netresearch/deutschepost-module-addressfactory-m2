<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Integration\Fixture\Data;

use Magento\Catalog\Model\Product\Type;

/**
 * Regular simple product with qty=1.
 *
 * @author  Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class SimpleProduct implements ProductInterface
{
    public function getType(): string
    {
        return Type::TYPE_SIMPLE;
    }

    public function getSku(): string
    {
        return 'DHL-01';
    }

    public function getPrice(): float
    {
        return 24.99;
    }

    public function getWeight(): float
    {
        return 2.4;
    }

    public function getCheckoutQty(): int
    {
        return 2;
    }

    public function getDescription(): string
    {
        return 'Test Product Description';
    }
}
