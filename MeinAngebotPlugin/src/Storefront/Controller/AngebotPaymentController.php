<?php declare(strict_types=1);

namespace MeinVendor\MeinAngebotPlugin\Storefront\Controller;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AngebotPaymentController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $orderRepository
    ) {
    }

    #[Route(
        path: '/angebot/pay/{orderId}',
        name: 'frontend.angebot.pay',
        methods: ['GET']
    )]
    public function pay(string $orderId, SalesChannelContext $context): RedirectResponse
    {
        // 1. Bestellung laden und prüfen, ob sie dem Kunden gehört
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('stateMachineState');
        $criteria->addFilter(
            new EqualsFilter('orderCustomer.customerId', $context->getCustomerId())
        );

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$order) {
            $this->addFlash('danger', 'Bestellung nicht gefunden.');
            return $this->redirectToRoute('frontend.account.order.page');
        }

        // 2. Status prüfen: Order = "offer_created" && Transaction = "open"
        $orderState = $order->getStateMachineState()?->getTechnicalName();
        $transaction = $order->getTransactions()->first();
        $paymentState = $transaction?->getStateMachineState()?->getTechnicalName();

        if ($orderState !== 'offer_created' || $paymentState !== 'open') {
            $this->addFlash('warning', 'Diese Bestellung kann derzeit nicht bezahlt werden.');
            return $this->redirectToRoute('frontend.account.order.page');
        }

        // 3. Weiterleitung in den nativen Checkout zur Zahlungsabwicklung
        // Die orderId wird übergeben, damit das Zahlungs-Plugin die bestehende Transaktion nutzt.
        return $this->redirectToRoute('frontend.checkout.confirm.page', [
            'orderId' => $orderId,
        ]);
    }
}
