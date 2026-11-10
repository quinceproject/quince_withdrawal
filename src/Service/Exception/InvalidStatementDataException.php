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

/** Dane oświadczenia wpisywanego z zewnątrz nie przechodzą walidacji. */
final class InvalidStatementDataException extends RuntimeException
{
}
