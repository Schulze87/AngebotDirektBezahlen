<?php declare(strict_types=1);

namespace MeinVendor\MeinAngebotPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1700000000AddOfferCreatedState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000000;
    }

    public function update(Connection $connection): void
    {
        // 1. State Machine ID für order.state ermitteln
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :name',
            ['name' => 'order.state']
        );

        if (!$stateMachineId) {
            throw new \RuntimeException('State machine "order.state" not found.');
        }

        // 2. Status "offer_created" anlegen (falls nicht vorhanden)
        $offerStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :smid',
            ['name' => 'offer_created', 'smid' => $stateMachineId]
        );

        if (!$offerStateId) {
            $offerStateId = Uuid::randomHex();

            $connection->insert('state_machine_state', [
                'id' => Uuid::fromHexToBytes($offerStateId),
                'technical_name' => 'offer_created',
                'state_machine_id' => $stateMachineId,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // Übersetzung für Deutsch
            $languageId = $connection->fetchOne(
                "SELECT lg.id FROM language lg 
                 INNER JOIN locale lc ON lc.id = lg.locale_id 
                 WHERE lc.code = 'de-DE' LIMIT 1"
            );

            if ($languageId) {
                $connection->insert('state_machine_state_translation', [
                    'language_id' => $languageId,
                    'state_machine_state_id' => Uuid::fromHexToBytes($offerStateId),
                    'name' => 'Angebot erstellt',
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
            }
        }

        // 3. IDs der Zielstatus ermitteln
        $cancelledStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :smid',
            ['name' => 'cancelled', 'smid' => $stateMachineId]
        );

        $inProgressStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :smid',
            ['name' => 'in_progress', 'smid' => $stateMachineId]
        );

        // 4. Transitionen anlegen: offer_created -> cancelled / in_progress
        $transitions = [
            [$offerStateId, $cancelledStateId, 'cancel'],
            [$offerStateId, $inProgressStateId, 'process'],
        ];

        foreach ($transitions as [$fromId, $toId, $actionName]) {
            if (!$fromId || !$toId) {
                continue; // Zielstatus nicht gefunden, überspringen
            }

            $exists = $connection->fetchOne(
                'SELECT id FROM state_machine_transition 
                 WHERE state_machine_id = :smid 
                   AND from_state_id = :from 
                   AND to_state_id = :to',
                [
                    'smid' => $stateMachineId,
                    'from' => Uuid::fromHexToBytes($fromId),
                    'to' => Uuid::fromHexToBytes($toId),
                ]
            );

            if (!$exists) {
                $connection->insert('state_machine_transition', [
                    'id' => Uuid::randomBytes(),
                    'state_machine_id' => $stateMachineId,
                    'action_name' => $actionName,
                    'from_state_id' => Uuid::fromHexToBytes($fromId),
                    'to_state_id' => Uuid::fromHexToBytes($toId),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Bewusst leer: Status und Transitionen bei Deinstallation nicht löschen,
        // da Bestellungen den Status referenzieren könnten.
    }
}
