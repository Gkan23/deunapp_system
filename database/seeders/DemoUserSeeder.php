<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\DeliveryProvider;
use App\Models\ProviderType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    private const PASSWORD = 'DeUnapp123!';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $activeStatusId = AccountStatus::query()
                ->where('status_name', 'ACTIVE')
                ->firstOrFail()
                ->id;

            $administrator = $this->user(
                name: 'Administrador DeUnapp',
                email: 'administrador@deunapp.com',
                roleName: 'ADMINISTRATOR',
                activeStatusId: $activeStatusId
            );

            $customerUser = $this->user(
                name: 'Cliente DeUnapp',
                email: 'cliente@deunapp.com',
                roleName: 'CUSTOMER',
                activeStatusId: $activeStatusId
            );

            $providerUser = $this->user(
                name: 'Proveedor DeUnapp',
                email: 'proveedor@deunapp.com',
                roleName: 'DELIVERY_PROVIDER',
                activeStatusId: $activeStatusId
            );

            $courierUser = $this->user(
                name: 'Repartidor DeUnapp',
                email: 'repartidor@deunapp.com',
                roleName: 'COURIER',
                activeStatusId: $activeStatusId
            );

            $supportAgent = $this->user(
                name: 'Soporte DeUnapp',
                email: 'soporte@deunapp.com',
                roleName: 'SUPPORT_AGENT',
                activeStatusId: $activeStatusId
            );

            Customer::query()->updateOrCreate(
                [
                    'user_id' => $customerUser->id,
                ],
                [
                    'customer_type_id' => CustomerType::query()
                        ->where('type_name', 'INDIVIDUAL')
                        ->firstOrFail()
                        ->id,
                    'identity_number' => '001-050906-0001A',
                    'company_name' => null,
                    'phone' => '88880001',
                ]
            );

            $provider = DeliveryProvider::query()
                ->updateOrCreate(
                    [
                        'user_id' => $providerUser->id,
                    ],
                    [
                        'provider_type_id' => ProviderType::query()
                            ->where('type_name', 'COMPANY')
                            ->firstOrFail()
                            ->id,
                        'business_name' => 'DeUnapp Demo',
                        'identity_number' => '001-050906-0002B',
                        'phone' => '88880002',
                        'is_active' => true,
                    ]
                );

            Courier::query()->updateOrCreate(
                [
                    'user_id' => $courierUser->id,
                ],
                [
                    'delivery_provider_id' => $provider->id,
                    'license_number' => 'NIC-0001-DU',
                    'is_available' => true,
                    'is_active' => true,
                ]
            );
        });
    }

    private function user(
        string $name,
        string $email,
        string $roleName,
        int $activeStatusId
    ): User {
        $roleId = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail()
            ->id;

        $user = User::query()->updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'role_id' => $roleId,
                'account_status_id' => $activeStatusId,
            ]
        );

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}