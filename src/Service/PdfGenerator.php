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

use Address;
use Configuration;
use Context;
use Order;

/**
 * Potwierdzenie przyjęcia oświadczenia: dokument na trwałym nośniku.
 *
 * Strona WWW nie jest trwałym nośnikiem w rozumieniu prawa (sklep mógłby ją
 * zmienić). Klient ma dostać coś, co zostaje poza kontrolą sklepu. Plik ląduje
 * w `var/`, poza katalogiem serwowanym wprost, i wychodzi wyłącznie jako
 * załącznik maila.
 */
final class PdfGenerator
{
    private const INK = '#1a1a1a';

    /**
     * @param array<string,mixed> $statement
     */
    public function build(array $statement, Order $order): string
    {
        $dir = self::directory();

        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return '';
        }

        require_once __DIR__ . '/../../libs/autoload.php';

        $filename = 'withdrawal-' . preg_replace('/[^0-9a-zA-Z]+/', '-', (string) $statement['reference']) . '.pdf';

        try {
            $pdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 18,
                'margin_right' => 18,
                'margin_top' => 16,
                'margin_bottom' => 18,
                'tempDir' => $dir . 'tmp',
                'default_font' => 'dejavusanscondensed',
            ]);

            $pdf->SetTitle((string) $statement['reference']);
            $pdf->WriteHTML($this->html($statement, $order));
            $pdf->Output($dir . $filename, \Mpdf\Output\Destination::FILE);
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('Quince Withdrawal: ' . $e->getMessage(), 3);

            return '';
        }

        return $filename;
    }

    public static function directory(): string
    {
        return _PS_ROOT_DIR_ . '/var/quince_withdrawal/';
    }

    /** @param array<string,mixed> $statement */
    private function html(array $statement, Order $order): string
    {
        $idLang = (int) $order->id_lang;
        $address = new Address((int) $order->id_address_invoice);

        $style = '<style>'
            . 'body{font-family:dejavusanscondensed;font-size:10pt;color:' . self::INK . ';line-height:1.6}'
            . '.meta{font-size:9pt;color:#6b7280;line-height:1.8}'
            . '.band{background:#f7f7f8;padding:3mm 4mm;font-size:11pt;font-weight:bold;margin:8mm 0 3mm}'
            . '.sep{border:none;border-top:0.2mm solid #e5e7eb;margin:7mm 0}'
            . '</style>';

        $header = '<table width="100%"><tr>'
            . '<td width="50%" valign="middle">' . $this->logo() . '</td>'
            . '<td width="50%" valign="top" align="right">'
            . '<div style="font-size:15pt;font-weight:bold">' . $this->t('Acknowledgement of withdrawal', $idLang) . '</div>'
            . '<div class="meta" style="margin-top:3mm">'
            . $this->t('Reference', $idLang) . ': <b>' . $this->e((string) $statement['reference']) . '</b><br>'
            . $this->t('Submitted', $idLang) . ': ' . $this->date((string) $statement['date_add']) . '<br>'
            . $this->t('Order', $idLang) . ': ' . $this->e((string) $order->reference)
            . '</div></td></tr></table><hr class="sep">';

        $intro = '<p>' . $this->t(
            'We confirm receipt of your statement of withdrawal from the contract for order %order%, submitted on %date%.',
            $idLang
        ) . '</p>';
        $intro = str_replace(
            ['%order%', '%date%'],
            [$this->e((string) $order->reference), $this->date((string) $statement['date_add'])],
            $intro
        );

        $customer = '<div class="band">' . $this->t('Customer', $idLang) . '</div>'
            . '<p>' . $this->e((string) $statement['customer_name']) . '<br>'
            . $this->e((string) $statement['customer_email']) . '<br>'
            . $this->e(trim($address->address1 . ' ' . $address->address2)) . '<br>'
            . $this->e(trim($address->postcode . ' ' . $address->city))
            . '</p>';

        $reasonBlock = '';
        $reason = trim((string) ($statement['reason'] ?? ''));
        if ($reason !== '') {
            $reasonBlock = '<div class="band">' . $this->t('Reason given', $idLang) . '</div>'
                . '<p>' . nl2br($this->e($reason)) . '</p>';
        }

        $legal = '<div class="band">' . $this->t('What happens next', $idLang) . '</div>'
            . '<p>' . $this->t(
                'No reason is required for withdrawal. The shop will confirm acceptance separately. '
                . 'If the order contains goods, please return them without undue delay. '
                . 'Any amount already paid will be refunded once the shop has received the goods back, '
                . 'or proof that they have been sent, whichever is earlier.',
                $idLang
            ) . '</p>';

        $footer = '<hr class="sep"><div class="meta">'
            . '<b>' . $this->e((string) Configuration::get('PS_SHOP_NAME')) . '</b><br>'
            . $this->e((string) Configuration::get('PS_SHOP_EMAIL'))
            . '</div>';

        return $style . $header . $intro . $customer . $reasonBlock . $legal . $footer;
    }

    private function logo(): string
    {
        $path = _PS_IMG_DIR_ . (string) Configuration::get('PS_LOGO');

        return is_file($path)
            ? '<img src="' . $this->e($path) . '" style="max-height:16mm;max-width:60mm">'
            : '<div style="font-size:13pt;font-weight:bold">' . $this->e((string) Configuration::get('PS_SHOP_NAME')) . '</div>';
    }

    private function t(string $text, int $idLang): string
    {
        return Context::getContext()->getTranslator()->trans(
            $text,
            [],
            'Modules.Quincewithdrawal.Shop',
            \Language::getLocaleById($idLang) ?: null
        );
    }

    private function date(string $value): string
    {
        return date('d.m.Y', strtotime($value) ?: time());
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
