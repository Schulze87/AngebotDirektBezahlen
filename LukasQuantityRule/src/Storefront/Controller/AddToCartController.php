<?php declare(strict_types=1);

namespace LukasQuantityRule\Storefront\Controller;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class AddToCartController extends StorefrontController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityRepository $productRepository
    ) {
    }

    #[Route(
        path: '/add-to-cart/{productNumber}',
        name: 'frontend.lukas_quantity_rule.add_to_cart',
        methods: ['GET']
    )]
    public function addToCart(
        string $productNumber,
        Request $request,
        SalesChannelContext $context
    ): Response {
        $quantity = max(1, (int) $request->query->get('qty', 1));

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productNumber', $productNumber));

        $product = $this->productRepository
            ->search($criteria, $context->getContext())
            ->first();

        if (!$product) {
            return new Response('Product not found', Response::HTTP_NOT_FOUND);
        }

        $lineItem = new LineItem(
            $product->getId(),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $product->getId(),
            $quantity
        );

        $lineItem->setStackable(true);
        $lineItem->setRemovable(true);

        $this->cartService->add($cart, $lineItem, $context);

        return new Response('OK');
    }
}
