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

use Db;
use Module;
use Order;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Settings\WithdrawalSettings;

/**
 * Złożenie oświadczenia: zapis, numer, PDF i mail potwierdzający — jedno
 * miejsce, bo wszystkie trzy muszą zajść razem albo wcale.
 */
final class StatementService
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings,
        private readonly PdfGenerator $pdf = new PdfGenerator(),
        private readonly StatementMailer $mailer = new StatementMailer()
    ) {
    }

    /**
     * @param \DateTimeInterface|null $submittedAt moment złożenia poza formularzem sklepu
     *        (np. mail, list); `null` = teraz, czyli zawsze dla formularza klienta
     * @param string $channel `shop_form` (domyślny, formularz klienta), `email`, `post`, `other`
     * @param string|null $enteredBy kto wpisał oświadczenie spoza formularza (pracownik CEP)
     * @param string|null $note notatka wewnętrzna — nie trafia do klienta ani do PDF
     * @param string|null $externalId identyfikator po stronie systemu, który wpisał oświadczenie
     *
     * @return array<string,mixed>|null oświadczenie zapisane w bazie, albo null przy błędzie
     */
    public function submit(
        Order $order,
        string $reason,
        ?\DateTimeInterface $submittedAt = null,
        string $channel = 'shop_form',
        ?string $enteredBy = null,
        ?string $note = null,
        ?string $externalId = null,
        bool $sendConfirmation = true
    ): ?array {
        $customer = new \Customer((int) $order->id_customer);

        if (!\Validate::isLoadedObject($customer)) {
            return null;
        }

        $idShop = (int) $order->id_shop;
        $teraz = date('Y-m-d H:i:s');
        $dataZlozenia = $submittedAt !== null ? $submittedAt->format('Y-m-d H:i:s') : $teraz;

        $inserted = Db::getInstance()->insert('quince_withdrawal', [
            'id_shop' => $idShop,
            'id_order' => (int) $order->id,
            'id_customer' => (int) $customer->id,
            // Placeholder unikalny na czas między insertem a nadaniem numeru —
            // UNIQUE KEY na `reference` wymaga czegoś już teraz.
            'reference' => 'TMP-' . bin2hex(random_bytes(8)),
            'status' => 'submitted',
            'reason' => $reason !== '' ? $reason : null,
            'customer_name' => $customer->firstname . ' ' . $customer->lastname,
            'customer_email' => (string) $customer->email,
            'channel' => $channel,
            'entered_by' => $enteredBy !== null && $enteredBy !== '' ? $enteredBy : null,
            'note' => $note !== null && $note !== '' ? $note : null,
            'external_id' => $externalId !== null && $externalId !== '' ? $externalId : null,
            // Data złożenia (prawnie wiążąca — od niej liczy się termin) i data
            // ostatniej zmiany wiersza (zawsze "teraz") to dwie różne rzeczy:
            // oświadczenie wpisane z opóźnieniem ma `date_add` z przeszłości,
            // ale `date_upd` musi pokazywać, kiedy wiersz realnie powstał.
            'date_add' => $dataZlozenia,
            'date_upd' => $teraz,
        ]);

        if (!$inserted) {
            return null;
        }

        $id = (int) Db::getInstance()->Insert_ID();
        $reference = $this->reference($id);

        Db::getInstance()->update(
            'quince_withdrawal',
            ['reference' => $reference],
            'id_withdrawal = ' . $id
        );

        $statement = $this->repository->get($id, $idShop);
        if ($statement === null) {
            return null;
        }

        $pdfFile = $this->pdf->build($statement, $order);

        if ($pdfFile !== '') {
            Db::getInstance()->update(
                'quince_withdrawal',
                ['pdf_filename' => $pdfFile],
                'id_withdrawal = ' . $id
            );
            $statement['pdf_filename'] = $pdfFile;
        }

        if ($sendConfirmation) {
            $this->mailer->send($statement, $order);
        }

        $this->dispatchEvent('withdrawal.submitted', $statement);

        return $statement;
    }

    /** `ODST/{rok}/{numer na 6 cyfr}` — rok z daty złożenia, numer z klucza wiersza. */
    private function reference(int $id): string
    {
        return sprintf('ODST/%s/%06d', date('Y'), $id);
    }

    /**
     * Zdarzenie wychodzące ze sklepu.
     *
     * Odbiorcę wskazuje sklep w module `quince_events`, w jego ekranie
     * ustawień — ten moduł nie zna ani adresu, ani tego, co stoi po drugiej
     * stronie. Bez zainstalowanego `quince_events` nie dzieje się nic,
     * a oświadczenie i tak jest zapisane.
     *
     * @param array<string,mixed> $statement
     */
    private function dispatchEvent(string $type, array $statement): void
    {
        $zdarzenia = Module::getInstanceByName('quince_events');

        if (!$zdarzenia || !class_exists(\Quince\Events\Event\EventDispatcher::class)) {
            return;
        }

        (new \Quince\Events\Event\EventDispatcher(new \Quince\Events\Event\EventOutbox()))->dispatch(
            new \Quince\Events\Event\ShopEvent(
                type: $type,
                data: [
                    'id_withdrawal' => (int) $statement['id_withdrawal'],
                    'reference' => (string) $statement['reference'],
                    'id_order' => (int) $statement['id_order'],
                    'status' => (string) $statement['status'],
                    'reason' => $statement['reason'] !== null ? (string) $statement['reason'] : null,
                    'channel' => (string) ($statement['channel'] ?? 'shop_form'),
                    'entered_by' => !empty($statement['entered_by']) ? (string) $statement['entered_by'] : null,
                    'external_id' => !empty($statement['external_id']) ? (string) $statement['external_id'] : null,
                    'customer' => [
                        'id_customer' => (int) $statement['id_customer'],
                        'email' => (string) $statement['customer_email'],
                        'name' => (string) $statement['customer_name'],
                    ],
                ]
            )
        );
    }
}
