<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use App\Models\CommissionRule;
use App\Models\CustomerType;
use App\Models\IncidentStatus;
use App\Models\IncidentType;
use App\Models\Municipality;
use App\Models\NotificationType;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\ProviderType;
use App\Models\RechargePackage;
use App\Models\Role;
use App\Models\RouteStatus;
use App\Models\ServiceType;
use App\Models\ShipmentStatus;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TripType;
use App\Models\VehicleStatus;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsert(Role::class, 'role_name', [
            ['role_name' => 'ADMINISTRATOR'],
            ['role_name' => 'CUSTOMER'],
            ['role_name' => 'DELIVERY_PROVIDER'],
            ['role_name' => 'COURIER'],
            ['role_name' => 'SUPPORT_AGENT'],
        ]);

        $this->upsert(AccountStatus::class, 'status_name', [
            ['status_name' => 'PENDING'],
            ['status_name' => 'ACTIVE'],
            ['status_name' => 'SUSPENDED'],
            ['status_name' => 'BLOCKED'],
        ]);

        $this->upsert(CustomerType::class, 'type_name', [
            ['type_name' => 'INDIVIDUAL'],
            ['type_name' => 'BUSINESS'],
        ]);

        $this->upsert(ProviderType::class, 'type_name', [
            ['type_name' => 'INDEPENDENT'],
            ['type_name' => 'COMPANY'],
        ]);

        $this->upsert(VehicleType::class, 'type_name', [
            ['type_name' => 'MOTORCYCLE'],
            ['type_name' => 'CAR'],
            ['type_name' => 'VAN'],
            ['type_name' => 'TRUCK'],
            ['type_name' => 'BICYCLE'],
        ]);

        $this->upsert(VehicleStatus::class, 'status_name', [
            ['status_name' => 'AVAILABLE'],
            ['status_name' => 'IN_USE'],
            ['status_name' => 'MAINTENANCE'],
            ['status_name' => 'INACTIVE'],
        ]);

        $this->seedMunicipalities();

        $this->upsert(ServiceType::class, 'service_name', [
            [
                'service_name' => 'STANDARD',
                'description' => 'Entrega regular.',
                'estimated_minutes' => 180,
                'is_active' => true,
            ],
            [
                'service_name' => 'EXPRESS',
                'description' => 'Entrega prioritaria.',
                'estimated_minutes' => 60,
                'is_active' => true,
            ],
            [
                'service_name' => 'SCHEDULED',
                'description' => 'Entrega programada para una fecha y hora.',
                'estimated_minutes' => null,
                'is_active' => true,
            ],
        ]);

        $this->upsert(TripType::class, 'type_name', [
            ['type_name' => 'LOCAL', 'description' => 'Origen y destino en el mismo municipio.'],
            ['type_name' => 'INTERMUNICIPAL', 'description' => 'Origen y destino en municipios diferentes.'],
        ]);

        $this->seedRechargeConfiguration();

        $this->upsert(ShipmentStatus::class, 'status_name', [
            ['status_name' => 'REQUESTED', 'description' => 'Envío solicitado.'],
            ['status_name' => 'PICKED_UP', 'description' => 'Paquete recogido.'],
            ['status_name' => 'IN_TRANSIT', 'description' => 'Paquete en tránsito.'],
            ['status_name' => 'OUT_FOR_DELIVERY', 'description' => 'Paquete en ruta de entrega.'],
            ['status_name' => 'DELIVERED', 'description' => 'Paquete entregado.'],
            ['status_name' => 'CANCELLED', 'description' => 'Envío cancelado.'],
        ]);

        $this->upsert(RouteStatus::class, 'status_name', [
            ['status_name' => 'PLANNED'],
            ['status_name' => 'ACTIVE'],
            ['status_name' => 'COMPLETED'],
            ['status_name' => 'CANCELLED'],
        ]);

        $this->upsert(IncidentType::class, 'type_name', [
            ['type_name' => 'DELAY', 'description' => 'Retraso en el servicio.'],
            ['type_name' => 'DAMAGED_PACKAGE', 'description' => 'Paquete dañado.'],
            ['type_name' => 'LOST_PACKAGE', 'description' => 'Paquete extraviado.'],
            ['type_name' => 'WRONG_ADDRESS', 'description' => 'Dirección incorrecta.'],
            ['type_name' => 'DELIVERY_FAILED', 'description' => 'No fue posible completar la entrega.'],
            ['type_name' => 'RECIPIENT_ABSENT', 'description' => 'Destinatario ausente.'],
            ['type_name' => 'VEHICLE_PROBLEM', 'description' => 'Problema con el vehículo.'],
            ['type_name' => 'CONTACT_FAILED', 'description' => 'No fue posible contactar al destinatario.'],
            ['type_name' => 'CANCELLATION', 'description' => 'Servicio o envío cancelado.'],
        ]);

        $this->upsert(IncidentStatus::class, 'status_name', [
            ['status_name' => 'OPEN'],
            ['status_name' => 'IN_REVIEW'],
            ['status_name' => 'RESOLVED'],
            ['status_name' => 'CLOSED'],
        ]);

        $this->upsert(NotificationType::class, 'type_name', [
            ['type_name' => 'SERVICE_REQUESTED'],
            ['type_name' => 'SERVICE_ASSIGNED'],
            ['type_name' => 'SHIPMENT_STATUS_CHANGED'],
            ['type_name' => 'PAYMENT_CONFIRMED'],
            ['type_name' => 'SUPPORT_UPDATE'],
        ]);

        $this->upsert(TicketCategory::class, 'category_name', [
            ['category_name' => 'DELIVERY'],
            ['category_name' => 'PAYMENT'],
            ['category_name' => 'ACCOUNT'],
            ['category_name' => 'TECHNICAL'],
            ['category_name' => 'OTHER'],
        ]);

        $this->upsert(TicketStatus::class, 'status_name', [
            ['status_name' => 'OPEN'],
            ['status_name' => 'IN_PROGRESS'],
            ['status_name' => 'WAITING_CUSTOMER'],
            ['status_name' => 'RESOLVED'],
            ['status_name' => 'CLOSED'],
        ]);

        $this->upsert(TicketPriority::class, 'priority_name', [
            ['priority_name' => 'LOW'],
            ['priority_name' => 'MEDIUM'],
            ['priority_name' => 'HIGH'],
            ['priority_name' => 'URGENT'],
        ]);

        $this->upsert(PaymentMethod::class, 'method_name', [
            ['method_name' => 'CASH'],
            ['method_name' => 'CARD'],
            ['method_name' => 'BANK_TRANSFER'],
            ['method_name' => 'MOBILE_WALLET'],
        ]);

        $this->upsert(PaymentStatus::class, 'status_name', [
            ['status_name' => 'PENDING'],
            ['status_name' => 'PAID'],
            ['status_name' => 'FAILED'],
            ['status_name' => 'REFUNDED'],
        ]);
    }

    private function seedMunicipalities(): void
    {
        // Catálogo piloto para Estelí. Sustituir o ampliar con el catálogo nacional oficial.
        foreach (['Estelí', 'Condega', 'Pueblo Nuevo', 'San Juan de Limay', 'La Trinidad', 'San Nicolás'] as $name) {
            Municipality::query()->updateOrCreate(
                ['department_name' => 'Estelí', 'municipality_name' => $name],
                ['is_active' => true]
            );
        }
    }

    private function seedRechargeConfiguration(): void
    {
        $local = TripType::query()->where('type_name', 'LOCAL')->firstOrFail();
        $intermunicipal = TripType::query()->where('type_name', 'INTERMUNICIPAL')->firstOrFail();

        $localRule = CommissionRule::query()->firstOrCreate(
            ['trip_type_id' => $local->id, 'valid_until' => null],
            [
                'commission_amount' => 15.00,
                'commission_percentage' => null,
                'valid_from' => now()->startOfDay(),
                'is_active' => true,
            ]
        );

        $intermunicipalRule = CommissionRule::query()->firstOrCreate(
            ['trip_type_id' => $intermunicipal->id, 'valid_until' => null],
            [
                'commission_amount' => 25.00,
                'commission_percentage' => null,
                'valid_from' => now()->startOfDay(),
                'is_active' => true,
            ]
        );

        $packages = [
            ['commission_rule_id' => $localRule->id, 'package_name' => 'LOCAL_10', 'trip_quantity' => 10, 'price' => 150.00],
            ['commission_rule_id' => $localRule->id, 'package_name' => 'LOCAL_20', 'trip_quantity' => 20, 'price' => 280.00],
            ['commission_rule_id' => $intermunicipalRule->id, 'package_name' => 'INTERMUNICIPAL_10', 'trip_quantity' => 10, 'price' => 250.00],
        ];

        foreach ($packages as $package) {
            RechargePackage::query()->updateOrCreate(
                ['package_name' => $package['package_name']],
                $package + ['is_active' => true]
            );
        }
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsert(string $model, string $key, array $rows): void
    {
        foreach ($rows as $row) {
            $model::query()->updateOrCreate([$key => $row[$key]], $row);
        }
    }
}
