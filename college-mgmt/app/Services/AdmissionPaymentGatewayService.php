<?php

namespace App\Services;

use App\Models\AdmissionPayment;
use App\Models\AdmissionPaymentGatewayEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AdmissionPaymentGatewayService
{
    public function createOrder(AdmissionPayment $payment, string $provider = 'razorpay_mock'): array
    {
        if (!$payment->gateway_order_id) {
            $payment->update([
                'provider' => $provider,
                'gateway_order_id' => 'order_' . Str::lower(Str::random(18)),
                'gateway_status' => 'created',
                'gateway_payload' => [
                    'amount' => (float) $payment->amount_paid,
                    'currency' => 'INR',
                ],
            ]);
        }

        return [
            'provider' => $payment->provider ?: $provider,
            'order_id' => $payment->gateway_order_id,
            'amount' => (float) $payment->amount_paid,
            'currency' => 'INR',
            'status' => $payment->gateway_status,
        ];
    }

    public function handleWebhook(array $payload, string $provider = 'razorpay_mock'): AdmissionPaymentGatewayEvent
    {
        $eventId = Arr::get($payload, 'event_id') ?: Arr::get($payload, 'id');
        $orderId = Arr::get($payload, 'order_id') ?: Arr::get($payload, 'payload.payment.entity.order_id');
        $paymentId = Arr::get($payload, 'payment_id') ?: Arr::get($payload, 'payload.payment.entity.id');
        $eventType = Arr::get($payload, 'event') ?: Arr::get($payload, 'status', 'payment.updated');
        $eventId = $eventId ?: sha1($provider . '|' . $eventType . '|' . $orderId . '|' . $paymentId);

        $event = AdmissionPaymentGatewayEvent::firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            [
                'gateway_order_id' => $orderId,
                'gateway_payment_id' => $paymentId,
                'event_type' => $eventType,
                'payload' => $payload,
            ]
        );

        if ($event->processed_at) {
            return $event;
        }

        $payment = AdmissionPayment::query()
            ->where('gateway_order_id', $orderId)
            ->when($paymentId, fn ($query) => $query->orWhere('gateway_payment_id', $paymentId))
            ->first();

        if ($payment) {
            $gatewayStatus = $this->gatewayStatus($payload, $eventType);
            $updates = [
                'provider' => $provider,
                'gateway_payment_id' => $paymentId ?: $payment->gateway_payment_id,
                'gateway_status' => $gatewayStatus,
                'gateway_payload' => $payload,
            ];

            if (in_array($gatewayStatus, ['captured', 'paid', 'success'], true)) {
                $updates = array_merge($updates, [
                    'status' => 'verified',
                    'transaction_reference' => $paymentId ?: $payment->transaction_reference,
                    'payment_date' => now()->toDateString(),
                    'verified_at' => $payment->verified_at ?: now(),
                    'paid_via_gateway_at' => $payment->paid_via_gateway_at ?: now(),
                ]);
            }

            if (in_array($gatewayStatus, ['failed', 'expired'], true)) {
                $updates['status'] = 'pending';
            }

            $payment->update($updates);
        }

        $event->update(['processed_at' => now()]);

        return $event;
    }

    private function gatewayStatus(array $payload, string $eventType): string
    {
        return Arr::get($payload, 'status')
            ?: Arr::get($payload, 'payload.payment.entity.status')
            ?: (str_contains($eventType, 'captured') ? 'captured' : 'updated');
    }
}
