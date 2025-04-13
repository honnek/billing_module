<?php

namespace Http\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Billing\{Adapter\PaymentGatewayAdapter,
    Entity\PaymentDetails,
    Entity\PaymentGateway,
    Exception\PaymentProcessingException,
    Http\Request\PaymentRequest,
    Service\PaymentLogger,
    Service\PaymentService};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Money\Currency;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentLogger $paymentLogger,
    ) {}

    /**
     * @param PaymentRequest $request
     * @return JsonResponse|RedirectResponse
     */
    public function pay(PaymentRequest $request): JsonResponse|RedirectResponse
    {
        $this->paymentLogger->logPaymentRequest($request);

        try {
            $user = $this->getAuthenticatedUser();
            [$gateway, $gatewayEntity] = $this->paymentService->resolvePaymentGateway($request);
            $subscription = $this->resolveSubscription($gatewayEntity, $request->getPlan());

            $paymentDetails = PaymentDetails::newByRequest($user, $gatewayEntity, $request, $subscription);
            $adapter = new PaymentGatewayAdapter($gateway);
            $transaction = $adapter->processPayment($paymentDetails);
            $adapter->notifyPaymentInitiation($paymentDetails);

            return $this->createPaymentResponse($adapter, $paymentDetails, $transaction);
        } catch (PaymentProcessingException $e) {
            $this->paymentLogger->logPaymentError($e, $request);
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function webhook(Request $request): JsonResponse|RedirectResponse
    {
        $this->paymentLogger->logWebhookRequest($request);

        try {
            [$gateway, $gatewayEntity] = $this->paymentService->resolvePaymentGateway($request, true);
            $adapter = new PaymentGatewayAdapter($gateway);

            if (!$adapter->validateRequest($request, $request->all())) {
                throw new PaymentProcessingException('Invalid webhook request', 400);
            }

            $transaction = $adapter->handleTransactionCallback($request->all());
            $adapter->notifyWebhookReceived($transaction, $request);

            return $this->createWebhookResponse($adapter);
        } catch (PaymentProcessingException $e) {
            $this->paymentLogger->logWebhookError($e, $request);
            return $this->errorRedirect();
        }
    }

    /**
     * @param PaymentGatewayAdapter $adapter
     * @param PaymentDetails $details
     * @param PaymentGatewayTransaction $transaction
     * @return JsonResponse
     */
    private function createPaymentResponse(
        PaymentGatewayAdapter $adapter,
        PaymentDetails $details,
        PaymentGatewayTransaction $transaction
    ): JsonResponse {
        return response()->json([
            'status' => 'Payment pending',
            'redirectUrl' => $adapter->requiresPaymentRedirect()
                ? $details->getRedirectToPayUrl()
                : null,
            'transactionId' => $transaction->id,
        ]);
    }

    /**
     * @param PaymentGatewayAdapter $adapter
     * @return JsonResponse|RedirectResponse
     */
    private function createWebhookResponse(PaymentGatewayAdapter $adapter): JsonResponse|RedirectResponse
    {
        return $adapter->requiresPostCallbackRedirect()
            ? redirect($this->paymentService->getSuccessCallbackUrl())
            : response()->json(['status' => 'Webhook processed']);
    }

    /**
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    private function errorResponse(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['error' => $message], $code);
    }

    /**
     * @return RedirectResponse
     */
    private function errorRedirect(): RedirectResponse
    {
        return redirect($this->paymentService->getFailedCallbackUrl());
    }

    /**
     * @return mixed
     */
    private function getAuthenticatedUser()
    {
        return auth()->user() ?? auth('sanctum')->user()
            ?? throw new PaymentProcessingException('User not authenticated', 401);
    }

    /**
     * @param PaymentGateway $gateway
     * @param string $plan
     * @return Subscription|null
     */
    private function resolveSubscription(PaymentGateway $gateway, string $plan): ?Subscription
    {
        return $this->subscriptionService->getSubscriptionByGatewayAndPlan($gateway, $plan);
    }
}
