<?php

namespace App\Services\Auth;

use App\Models\AccountStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RegisterCustomerService
{
    /**
     * Registra un usuario y su perfil de cliente.
     *
     * @param array<string, mixed> $data
     *
     * @throws DomainException
     */
    public function execute(array $data): User
    {
        try {
            return DB::transaction(function () use (
                $data
            ): User {
                $customerRole = Role::query()
                    ->where('role_name', 'CUSTOMER')
                    ->firstOrFail();

                $activeStatus = AccountStatus::query()
                    ->where('status_name', 'ACTIVE')
                    ->firstOrFail();

                $customerType = CustomerType::query()
                    ->where(
                        'type_name',
                        $data['customer_type']
                    )
                    ->firstOrFail();

                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role_id' => $customerRole->id,
                    'account_status_id' =>
                        $activeStatus->id,
                ]);

                $companyName = $customerType->type_name
                    === 'BUSINESS'
                        ? ($data['company_name'] ?? null)
                        : null;

                $customer = Customer::query()->create([
                    'user_id' => $user->id,
                    'customer_type_id' =>
                        $customerType->id,
                    'identity_number' =>
                        $data['identity_number'] ?? null,
                    'company_name' => $companyName,
                    'phone' => $data['phone'] ?? null,
                ]);

                $registeredAt = now();

                AuditLog::query()->create([
                    'performed_by_user_id' => $user->id,
                    'table_name' => 'customers',
                    'record_id' => $customer->id,
                    'action_type' => 'CUSTOMER_REGISTERED',
                    'details' => [
                        'user_id' => $user->id,
                        'customer_type' =>
                            $customerType->type_name,
                    ],
                    'performed_at' => $registeredAt,
                ]);

                return $user->load([
                    'role',
                    'accountStatus',
                    'customer.customerType',
                ]);
            }, attempts: 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            throw new DomainException(
                'The email or identity number has already been registered.',
                0,
                $exception
            );
        }
    }
}