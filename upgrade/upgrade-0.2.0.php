<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 0.2.0: oświadczenia wpisywane z zewnątrz (CEP) i rozliczenie.
 *
 * Dokłada kolumny `channel`/`entered_by`/`note`/`external_id` (endpoint
 * `POST /quince-withdrawal/statements`) oraz, defensywnie,
 * `reject_reason`/`settlement_*`, które mogły trafić do bazy ręcznym
 * `ALTER TABLE` bez wersjonowania. Instalacja startująca z 0.1.0 dochodzi
 * do tego samego schematu.
 *
 * Sprawdzane po nazwie kolumny, nie dodawane na ślepo: `ALTER TABLE ADD
 * COLUMN` na kolumnie, która już jest, wywraca się błędem.
 *
 * @param Module $module
 */
function upgrade_module_0_2_0($module): bool
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'quince_withdrawal';

    $istniejace = array_column(
        $db->executeS('SHOW COLUMNS FROM `' . $table . '`') ?: [],
        'Field'
    );

    $kolumny = [
        'reject_reason' => "TEXT NULL DEFAULT NULL",
        'settlement_status' => "ENUM('pending','refunded') NOT NULL DEFAULT 'pending'",
        'settlement_amount' => 'DECIMAL(20,6) NULL DEFAULT NULL',
        'settlement_currency' => 'VARCHAR(3) NULL DEFAULT NULL',
        'settlement_method' => 'VARCHAR(64) NULL DEFAULT NULL',
        'settlement_document' => 'VARCHAR(64) NULL DEFAULT NULL',
        'settlement_source' => 'VARCHAR(32) NULL DEFAULT NULL',
        'settled_at' => 'DATETIME NULL DEFAULT NULL',
        'channel' => "VARCHAR(16) NOT NULL DEFAULT 'shop_form'",
        'entered_by' => 'VARCHAR(255) NULL DEFAULT NULL',
        'note' => 'TEXT NULL DEFAULT NULL',
        'external_id' => 'VARCHAR(64) NULL DEFAULT NULL',
    ];

    foreach ($kolumny as $nazwa => $definicja) {
        if (in_array($nazwa, $istniejace, true)) {
            continue;
        }

        if (!$db->execute('ALTER TABLE `' . $table . '` ADD COLUMN `' . $nazwa . '` ' . $definicja)) {
            return false;
        }
    }

    $indeksy = array_column($db->executeS('SHOW INDEX FROM `' . $table . '`') ?: [], 'Key_name');

    if (!in_array('shop_external_id', $indeksy, true)) {
        if (!$db->execute('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `shop_external_id` (`id_shop`, `external_id`)')) {
            return false;
        }
    }

    return true;
}
