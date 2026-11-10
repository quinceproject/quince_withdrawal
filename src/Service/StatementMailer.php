<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\Service;

use Configuration;
use Context;
use Mail;
use Order;

/**
 * Mail potwierdzający przyjęcie oświadczenia, z PDF-em jako załącznikiem —
 * to on jest trwałym nośnikiem, nie ekran w przeglądarce.
 */
final class StatementMailer
{
    /** @param array<string,mixed> $statement */
    public function send(array $statement, Order $order): bool
    {
        $idLang = (int) $order->id_lang;
        $shopName = (string) Configuration::get('PS_SHOP_NAME');

        $vars = [
            '{shop_name}' => $shopName,
            '{shop_url}' => Context::getContext()->shop->getBaseURL(true),
            '{customer_name}' => (string) $statement['customer_name'],
            '{reference}' => (string) $statement['reference'],
            '{order_reference}' => (string) $order->reference,
        ];

        $subject = Context::getContext()->getTranslator()->trans(
            'Withdrawal statement received',
            [],
            'Modules.Quincewithdrawal.Shop',
            \Language::getLocaleById($idLang) ?: null
        ) . ': ' . $statement['reference'];

        return (bool) Mail::Send(
            $idLang,
            'quince_withdrawal',
            $subject,
            $vars,
            (string) $statement['customer_email'],
            (string) $statement['customer_name'],
            null,
            null,
            $this->attachment($statement),
            null,
            _PS_MODULE_DIR_ . 'quince_withdrawal/mails/',
            false,
            (int) $statement['id_shop']
        );
    }

    /** @param array<string,mixed> $statement */
    private function attachment(array $statement): ?array
    {
        $file = (string) ($statement['pdf_filename'] ?? '');

        if ($file === '') {
            return null;
        }

        $path = PdfGenerator::directory() . basename($file);

        if (!is_file($path)) {
            return null;
        }

        return [
            'content' => (string) file_get_contents($path),
            'name' => str_replace('/', '-', (string) $statement['reference']) . '.pdf',
            'mime' => 'application/pdf',
        ];
    }
}
