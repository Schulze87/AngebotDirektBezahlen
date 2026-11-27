<?php declare(strict_types=1);

namespace MeinVendor\MeinAngebotPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1700000001AddOfferCreatedTransitions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000001;
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

        // 2. Status-IDs ermitteln (binär, wie in der DB gespeichert)
        $offerStateId = $this->getStateId($connection, $stateMachineId, 'offer_created');
        if (!$offerStateId) {
            return; // Status existiert nicht -> nichts zu tun
        }

        $cancelledStateId = $this->getStateId($connection, $stateMachineId, 'cancelled');
        $inProgressStateId = $this->getStateId($connection, $stateMachineId, 'in_progress');

        // 3. Gewünschte Übergänge: [from, to, actionName]
        $transitions = [
            [$offerStateId, $cancelledStateId, 'cancel'],
            [$offerStateId, $inProgressStateId, 'process'],
        ];

        foreach ($transitions as [$fromId, $toId, $actionName]) {
            if (!$fromId || !$toId) {
                continue; // Zielstatus fehlt -> überspringen
            }

            // 4. Prüfen, ob Transition bereits existiert (Idempotenz)
            $exists = $connection->fetchOne(
                'SELECT id FROM state_machine_transition
                 WHERE state_machine_id = :smid
                   AND from_state_id = :from
                   AND to_state_id = :to',
                [
                    'smid' => $stateMachineId,
                    'from' => $fromId,
                    'to' => $toId,
                ]
            );

            if ($exists) {
                continue;
            }

            $connection->insert('state_machine_transition', [
                'id' => Uuid::randomBytes(),
                'state_machine_id' => $stateMachineId,
                'action_name' => $actionName,
                'from_state_id' => $fromId,
                'to_state_id' => $toId,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Bewusst leer: Status und Transitionen bei Deinstallation nicht löschen,
        // da Bestellungen den Status referenzieren könnten.
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
