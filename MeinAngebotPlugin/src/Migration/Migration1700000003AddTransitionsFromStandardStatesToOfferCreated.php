<?php declare(strict_types=1);

namespace MeinVendor\MeinAngebotPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1700000003AddTransitionsFromStandardStatesToOfferCreated extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000003;
    }

    public function update(Connection $connection): void
    {
        // 1. State Machine ID für order.state ermitteln
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :name',
            ['name' => 'order.state']
        );

        if (!$stateMachineId) {
            return;
        }

        // 2. Zielstatus "offer_created" ermitteln
        $offerStateId = $this->getStateId($connection, $stateMachineId, 'offer_created');
        if (!$offerStateId) {
            return;
        }

        // 3. Bekannte Standard-Status als Quellen
        $sourceStates = ['open', 'in_progress', 'cancelled', 'completed'];

        foreach ($sourceStates as $sourceName) {
            $sourceId = $this->getStateId($connection, $stateMachineId, $sourceName);
            if (!$sourceId) {
                continue;
            }

            // 4. Prüfen, ob Transition bereits existiert (Idempotenz)
            $exists = $connection->fetchOne(
                'SELECT id FROM state_machine_transition
                 WHERE state_machine_id = :smid
                   AND from_state_id = :from
                   AND to_state_id = :to',
                [
                    'smid' => $stateMachineId,
                    'from' => $sourceId,
                    'to' => $offerStateId,
                ]
            );

            if ($exists) {
                continue;
            }

            $connection->insert('state_machine_transition', [
                'id' => Uuid::randomBytes(),
                'state_machine_id' => $stateMachineId,
                'action_name' => 'create_offer',
                'from_state_id' => $sourceId,
                'to_state_id' => $offerStateId,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getStateId(Connection $connection, string $stateMachineId, string $technicalName): ?string
    {
        $id = $connection->fetchOne(
            'SELECT id FROM state_machine_state
             WHERE technical_name = :name AND state_machine_id = :smid',
            ['name' => $technicalName, 'smid' => $stateMachineId]
        );

        return $id !== false ? $id : null;
    }
}
