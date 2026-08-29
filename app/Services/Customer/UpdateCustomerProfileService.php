<?php

namespace App\Services\Customer;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerProfileService
{
    /**
     * Actualiza parcialmente el usuario y su perfil.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException
     */
    public function execute(
        User $user,
        array $data
    ): User {
        try {
            return DB::transaction(function () use (
                $user,
                $data
            ): User {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedCustomer = Customer::query()
                    ->where('user_id', $lockedUser->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentCustomerType =
                    CustomerType::query()
                        ->whereKey(
                            $lockedCustomer
                                ->customer_type_id
                        )
                        ->firstOrFail();

                $nextCustomerType = array_key_exists(
                    'customer_type',
                    $data
                )
                    ? CustomerType::query()
                        ->where(
                            'type_name',
                            $data['customer_type']
                        )
                        ->firstOrFail()
                    : $currentCustomerType;

                if (array_key_exists('name', $data)) {
                    $lockedUser->fill([
                        'name' => $data['name'],
                    ]);
                }

                $customerAttributes = [
                    'customer_type_id' =>
                        $nextCustomerType->id,
                ];

                if (
                    array_key_exists(
                        'identity_number',
                        $data
                    )
                ) {
                    $customerAttributes[
                        'identity_number'
                    ] = $data['identity_number'];
                }

                if (array_key_exists('phone', $data)) {
                    $customerAttributes['phone'] =
                        $data['phone'];
                }

                /*
                 * Un cliente individual no debe conservar
                 * un nombre de empresa.
                 */
                if (
                    $nextCustomerType->type_name
                    === 'INDIVIDUAL'
                ) {
                    $customerAttributes[
                        'company_name'
                    ] = null;
                } elseif (
                    array_key_exists(
                        'company_name',
                        $data
                    )
                ) {
                    $customerAttributes[
                        'company_name'
                    ] = $data['company_name'];
                }

                $lockedCustomer->fill(
                    $customerAttributes
                );

                $userChangedFields = array_keys(
                    $lockedUser->getDirty()
                );

                $customerChangedFields = array_keys(
                    $lockedCustomer->getDirty()
                );

                $changedFields = array_values(
                    array_unique(
                        array_merge(
                            $userChangedFields,
                            $customerChangedFields
                        )
                    )
                );

                sort($changedFields);

                if ($lockedUser->isDirty()) {
                    $lockedUser->save();
                }

                if ($lockedCustomer->isDirty()) {
                    $lockedCustomer->save();
                }

                /*
                 * No se genera auditoría si la solicitud no
                 * produjo ningún cambio real.
                 */
                if ($changedFields !== []) {
                    $updatedAt = now();

                    AuditLog::query()->create([
                        'performed_by_user_id' =>
                            $lockedUser->id,
                        'table_name' => 'customers',
                        'record_id' =>
                            $lockedCustomer->id,
                        'action_type' =>
                            'CUSTOMER_PROFILE_UPDATED',
                        'details' => [
                            'changed_fields' =>
                                $changedFields,
                            'customer_type' =>
                                $nextCustomerType
                                    ->type_name,
                        ],
                        'performed_at' => $updatedAt,
                    ]);
                }

                return $lockedUser->fresh([
                    'role',
                    'accountStatus',
                    'customer.customerType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The identity number has already been registered.',
                0,
                $exception
            );
        }
    }
}