<?php

namespace Billing\Entity;

use Illuminate\Contracts\Auth\Authenticatable;
use Money\Currency;
use Billing\Http\Request\PaymentRequest;

class PaymentDetails
{
    public const DEFAULT_CURRENCY = 'USD';
    private ?string $redirectToPayUrl = null;

    public function __construct(
        private readonly Authenticatable $user,
        private readonly PaymentGateway  $paymentGateway,
        private readonly ?Currency       $currency,
        private ?float                   $amount = 0,
        private readonly ?string         $firstName = null,
        private readonly ?string         $lastName = null,
        private readonly ?string         $phone = null,
        private readonly bool            $isTest = false,
        private readonly ?string         $plan = null,
    )
    {
    }

    /**
     * @return Authenticatable
     */
    public function getUser(): Authenticatable
    {
        return $this->user;
    }

    /**
     * @param float $amount
     * @return void
     */
    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return ?float
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }

    /**
     * @return ?Currency
     */
    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /**
     * @return PaymentGateway
     */
    public function getPaymentGateway(): PaymentGateway
    {
        return $this->paymentGateway;
    }

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->isTest;
    }

    /**
     * @return string|null
     */
    public function getPlan(): ?string
    {
        return $this->plan;
    }

    /**
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstName;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * @return string|null
     */
    public function getRedirectToPayUrl(): ?string
    {
        return $this->redirectToPayUrl;
    }

    /**
     * @param string|null $redirectToPayUrl
     * @return void
     */
    public function setRedirectToPayUrl(?string $redirectToPayUrl): void
    {
        $this->redirectToPayUrl = $redirectToPayUrl;
    }

    /**
     * @param Authenticatable $user
     * @param PaymentGateway $gateway
     * @param PaymentRequest $request
     * @param Subscription|null $subscription
     * @return PaymentDetails
     */
    public static function newByRequest(
        Authenticatable $user,
        PaymentGateway $gateway,
        PaymentRequest $request,
        ?Subscription $subscription,
    ): PaymentDetails
    {
        return new PaymentDetails(
            user: $user,
            paymentGateway: $gateway,
            currency: new Currency(config('services.payment_gateway.pay_currency')),
            amount: $subscription?->getPrice() ?? config('services.payment_gateway.default_pay_amount'),
            firstName: $request->getFirstname(),
            lastName: $request->getLastName(),
            phone: $request->getPhone(),
            isTest: $request->isTest(),
            plan: $request->getPlan(),
        );
    }
}
