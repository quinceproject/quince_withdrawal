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
use Customer;
use DateInterval;
use DateTime;
use Order;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Validate;

/**
 * Termin i kwalifikacja "towar czy usługa" dla zamówienia. Jedno miejsce,
 * bo panel i front muszą liczyć je identycznie.
 */
final class DeadlineCalculator
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings
    ) {
    }

    /** Zamówienie bez pozycji usługowej/cyfrowej liczy termin od dostawy, nie od zawarcia umowy. */
    public function isGoods(int $idOrder): bool
    {
        return $this->kind($idOrder) === 'goods';
    }

    /** Data zdarzenia startującego termin, albo null, gdy jeszcze nie zaszło. */
    public function startDate(int $idOrder, int $idShop): ?string
    {
        $status = match ($this->kind($idOrder)) {
            'digital' => $this->settings->digitalDeliveredStatus($idShop),
            'service' => $this->settings->serviceStartStatus($idShop),
            default => $this->settings->deliveryStatus($idShop),
        };

        return $this->repository->statusDate($idOrder, $status);
    }

    /**
     * Rodzaj zamówienia dla liczenia terminu: towar liczy od dostawy, usługa
     * od zawarcia umowy, treść cyfrowa od własnego statusu „dostarczono"
     * (Art. 16(m): osobny wyjątek, nie to samo zdarzenie co usługa).
     * Zamówienie mieszane bierze najwęższy przypadek: treść cyfrowa przed
     * usługą, usługa przed towarem.
     */
    private function kind(int $idOrder): string
    {
        $hasService = false;

        foreach ($this->repository->orderItems($idOrder) as $item) {
            if ($item['withdrawal_mode'] === 'digital') {
                return 'digital';
            }

            if ($item['withdrawal_mode'] === 'service') {
                $hasService = true;
            }
        }

        return $hasService ? 'service' : 'goods';
    }

    public function deadline(string $startDate, int $idShop): string
    {
        $date = new DateTime($startDate);
        $date->add(new DateInterval('P' . $this->settings->termDays($idShop) . 'D'));

        return $date->format('Y-m-d');
    }

    /**
     * Czy dziś jest jeszcze w terminie. Zdarzenie, które jeszcze nie zaszło
     * (towar w drodze), nie blokuje: termin po prostu jeszcze się nie zaczął,
     * a prawo odstąpienia działa już od zawarcia umowy.
     */
    public function isWithinDeadline(int $idOrder, int $idShop): bool
    {
        $start = $this->startDate($idOrder, $idShop);

        if ($start === null) {
            return true;
        }

        return date('Y-m-d') <= $this->deadline($start, $idShop);
    }

    /**
     * Czy zamówieniu w ogóle przysługuje prawo odstąpienia, niezależnie od
     * terminu. Ustawienie „Kto ma prawo odstąpienia" wyklucza zamówienia
     * firmowe, gdy sklep tak skonfigurował.
     */
    public function isEligibleCustomer(Order $order): bool
    {
        $idShop = (int) $order->id_shop;

        switch ($this->settings->qualificationMode($idShop)) {
            case WithdrawalSettings::QUALIFICATION_COMPANY_FIELD:
                $address = new Address((int) $order->id_address_invoice);

                return !Validate::isLoadedObject($address) || trim((string) $address->company) === '';

            case WithdrawalSettings::QUALIFICATION_CUSTOMER_GROUP:
                $excludedGroup = $this->settings->excludedGroup($idShop);

                if ($excludedGroup <= 0) {
                    return true;
                }

                $customer = new Customer((int) $order->id_customer);

                return !Validate::isLoadedObject($customer) || !in_array($excludedGroup, $customer->getGroups(), true);

            default:
                return true;
        }
    }

    /** Jedno miejsce łączące oba warunki: kwalifikację klienta i termin. Tego samego używa hak na stronie zamówienia i zapis formularza. */
    public function isEligible(Order $order): bool
    {
        return $this->isEligibleCustomer($order) && $this->isWithinDeadline((int) $order->id, (int) $order->id_shop);
    }
}
