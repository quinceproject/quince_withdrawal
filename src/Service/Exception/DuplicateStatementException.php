<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\Service\Exception;

use RuntimeException;

/**
 * Zamówienie, dla którego próbujemy zapisać oświadczenie z zewnątrz, już je
 * ma, pod innym `externalId`. Osobna klasa od {@see InvalidStatementDataException},
 * bo mapuje się na `409`, nie `422`: to nie błąd danych żądania, tylko
 * konflikt ze stanem, który już istnieje.
 */
final class DuplicateStatementException extends RuntimeException
{
}
