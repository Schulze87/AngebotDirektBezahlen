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
        // 1. Prüfen, ob Status bereits existiert (Idempotenz)
        $existingStateId = $connection->fetchOne(
            'SELECT id FROM state_machine_state WHERE technical_name = :name',
            ['name' => 'offer_created']
        );

        if ($existingStateId) {
            return;
        }

        // 2. State Machine ID für order.state ermitteln
        $stateMachineId = $connection->fetchOne(
            'SELECT id FROM state_machine WHERE technical_name = :name',
            ['name' => 'order.state']
        );

        if (!$stateMachineId) {
            throw new \RuntimeException('State machine "order.state" not found.');
        }

        // 3. Status einfügen
        $stateId = Uuid::randomHex();
        $connection->insert('state_machine_state', [
            'id' => Uuid::fromHexToBytes($stateId),
            'technical_name' => 'offer_created',
            'state_machine_id' => $stateMachineId,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        // 4. Übersetzung für Deutsch anlegen
        $languageId = $connection->fetchOne(
            "SELECT lg.id FROM language lg 
             INNER JOIN locale lc ON lc.id = lg.locale_id 
             WHERE lc.code = 'de-DE' LIMIT 1"
        );

        if ($languageId) {
            $connection->insert('state_machine_state_translation', [
                'language_id' => $languageId,
                'state_machine_state_id' => Uuid::fromHexToBytes($stateId),
                'name' => 'Angebot erstellt',
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }

        // 5. Transition von "open" nach "offer_created" anlegen
        $openStateId = $connection->fetchOne(
            "SELECT id FROM state_machine_state 
             WHERE technical_name = 'open' AND state_machine_id = :smid",
            ['smid' => $stateMachineId]
        );

        if ($openStateId) {
            $connection->insert('state_machine_transition', [
                'id' => Uuid::randomBytes(),
                'state_machine_id' => $stateMachineId,
                'action_name' => 'create_offer',
                'from_state_id' => $openStateId,
                'to_state_id' => Uuid::fromHexToBytes($stateId),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Bewusst leer: Status nicht bei Deinstallation löschen,
        // da Bestellungen ihn referenzieren könnten.
    }
}
