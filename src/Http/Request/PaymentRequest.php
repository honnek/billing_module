<?php

namespace Billing\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Billing\Attributes\SubscriptionPaymentAttributesVo;

/**
 * Class PaymentRequest
 *
 * @package Billing\Http\Request
 */
class PaymentRequest extends FormRequest
{
    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'payment_gateway' => 'required|string|max:50',
            'plan' => 'required|string|max:50',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'is_test' => 'sometimes|boolean',
        ];
    }

    /**
     * @return string
     */
    public function getPaymentGateway(): string
    {
        return (string) $this->input('payment_gateway');
    }

    /**
     * @return string
     */
    public function getPlan(): string
    {
        return (string) $this->input('plan');
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->filled('first_name')
            ? (string) $this->input('first_name')
            : null;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->filled('last_name')
            ? (string) $this->input('last_name')
            : null;
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->filled('phone')
            ? (string) $this->input('phone')
            : null;
    }

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return (bool) $this->input('is_test', false);
    }

    /**
     * @return SubscriptionPaymentAttributesVo
     */
    public function getAttributes(): SubscriptionPaymentAttributesVo
    {
        return SubscriptionPaymentAttributesVo::fromArray($this->all());
    }
}
