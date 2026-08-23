<?php

namespace App\Services\Recharge;

use App\Models\CommissionRule;
use App\Models\DeliveryProvider;
use App\Models\Recharge;
use App\Models\RechargePackage;
use App\Models\Trip;
use App\Models\TripTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmRechargeService
{
    public function handle(
        DeliveryProvider $deliveryProvider,
        RechargePackage $rechargePackage,
        string $paymentReference
    ): Recharge {
        $paymentReference = trim($paymentReference);

        $this->validatePaymentReference($paymentReference);

        return DB::transaction(function () use (
            $deliveryProvider,
            $rechargePackage,
            $paymentReference
        ): Recharge {
            $provider = DeliveryProvider::query()
                ->lockForUpdate()
                ->findOrFail($deliveryProvider->getKey());

            $package = RechargePackage::query()
                ->lockForUpdate()
                ->findOrFail($rechargePackage->getKey());

            $commissionRule = CommissionRule::query()
                ->lockForUpdate()
                ->findOrFail($package->commission_rule_id);

            $this->validateProvider($provider);
            $this->validatePackage($package, $commissionRule);

            if (
                Recharge::query()
                    ->where('payment_reference', $paymentReference)
                    ->exists()
            ) {
                throw new DomainException(
                    'The payment reference has already been used.'
                );
            }

            $now = now();

            $recharge = Recharge::query()->create([
                'delivery_provider_id' => $provider->id,
                'recharge_package_id' => $package->id,
                'trip_type_id' => $commissionRule->trip_type_id,
                'trip_quantity' => $package->trip_quantity,
                'commission_amount' => $commissionRule->commission_amount,
                'amount' => $package->price,
                'payment_reference' => $paymentReference,
                'recharged_at' => $now,
            ]);

            $tripRows = array_fill(0, $package->trip_quantity, [
                'delivery_provider_id' => $provider->id,
                'trip_type_id' => $commissionRule->trip_type_id,
                'status' => 'AVAILABLE',
                'used_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Trip::query()->insert($tripRows);

            TripTransaction::query()->create([
                'delivery_provider_id' => $provider->id,
                'recharge_id' => $recharge->id,
                'trip_id' => null,
                'transaction_type' => 'CREDIT',
                'quantity' => $package->trip_quantity,
                'description' => 'Crédito generado por la recarga confirmada.',
                'transaction_at' => $now,
            ]);

            return $recharge->load([
                'deliveryProvider',
                'rechargePackage',
                'tripType',
                'tripTransactions',
            ]);
        }, attempts: 3);
    }

    private function validatePaymentReference(
        string $paymentReference
    ): void {
        if ($paymentReference === '') {
            throw new DomainException(
                'The payment reference is required.'
            );
        }

        if (mb_strlen($paymentReference) > 100) {
            throw new DomainException(
                'The payment reference may not exceed 100 characters.'
            );
        }
    }

    private function validateProvider(
        DeliveryProvider $provider
    ): void {
        if (! $provider->is_active) {
            throw new DomainException(
                'The delivery provider is inactive.'
            );
        }
    }

    private function validatePackage(
        RechargePackage $package,
        CommissionRule $commissionRule
    ): void {
        if (! $package->is_active) {
            throw new DomainException(
                'The recharge package is inactive.'
            );
        }

        if ($package->trip_quantity < 1) {
            throw new DomainException(
                'The recharge package must contain at least one trip.'
            );
        }

        $today = today();

        $outsideValidityPeriod =
            $commissionRule->valid_from->gt($today)
            || (
                $commissionRule->valid_until !== null
                && $commissionRule->valid_until->lt($today)
            );

        if (
            ! $commissionRule->is_active
            || $outsideValidityPeriod
        ) {
            throw new DomainException(
                'The commission rule is not currently valid.'
            );
        }
    }
}
