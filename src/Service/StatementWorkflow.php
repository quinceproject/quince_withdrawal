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
use OrderReturn;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\Exception\InvalidTransitionException;
use Quince\Withdrawal\Service\Exception\StatementNotFoundException;
use Quince\Withdrawal\Settings\WithdrawalSettings;

/**
 * Przejścia statusu oświadczenia: potwierdź, przyjmij zwrot, odrzuć.
 *
 * Jedno miejsce dla panelu i Admin API: oba mają wołać dokładnie ten sam
 * kod, żeby mail do klienta, natywny zwrot towaru i reguły przejść nie
 * rozjechały się w dwóch kopiach.
 */
final class StatementWorkflow
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings
    ) {
    }

    /** @return array<string,mixed> zaktualizowane oświadczenie */
    public function confirm(int $id, int $idShop): array
    {
        $statement = $this->require($id, $idShop);

        if ($statement['status'] !== 'submitted') {
            throw new InvalidTransitionException('Only a submitted statement can be confirmed.');
        }

        $idOrder = (int) $statement['id_order'];
        $calculator = new DeadlineCalculator($this->repository, $this->settings);

        if ($calculator->isGoods($idOrder)) {
            $return = new OrderReturn();
            $return->id_customer = (int) $statement['id_customer'];
            $return->id_order = $idOrder;
            // Stan 2 = "Waiting for package": sklep już potwierdził przez to
            // oświadczenie, więc pomijamy stan 1 (Waiting for confirmation).
            $return->state = 2;
            $return->question = sprintf(
                'Automatically created from withdrawal statement %s.',
                $statement['reference']
            );
            $return->add();

            $items = $this->repository->orderItems($idOrder);
            $return->addReturnDetail(
                array_column($items, 'id_order_detail'),
                array_column($items, 'product_quantity')
            );

            Db::getInstance()->update(
                'quince_withdrawal',
                ['status' => 'acknowledged', 'id_order_return' => (int) $return->id, 'date_upd' => date('Y-m-d H:i:s')],
                'id_withdrawal = ' . $id
            );
        } else {
            // Usługa/treść cyfrowa: nie ma czego odsyłać, od razu gotowe do zwrotu środków.
            Db::getInstance()->update(
                'quince_withdrawal',
                ['status' => 'acknowledged', 'date_upd' => date('Y-m-d H:i:s')],
                'id_withdrawal = ' . $id
            );
        }

        $statement = $this->require($id, $idShop);
        $this->dispatchEvent('withdrawal.acknowledged', $statement);

        return $statement;
    }

    /** @return array<string,mixed> zaktualizowane oświadczenie */
    public function accept(int $id, int $idShop): array
    {
        $statement = $this->require($id, $idShop);

        if ($statement['status'] !== 'acknowledged') {
            throw new InvalidTransitionException('Only an acknowledged statement can be accepted.');
        }

        // Zwrot środków: wystawienie korekty idzie przez natywny ekran Credit
        // Slips tego zamówienia (albo przez CEP przy ustawieniu "cep"). Tu
        // tylko status: automatyczne wystawianie korekty wymagałoby
        // przeliczenia kwot zamówienia.
        Db::getInstance()->update(
            'quince_withdrawal',
            ['status' => 'accepted', 'date_upd' => date('Y-m-d H:i:s')],
            'id_withdrawal = ' . $id
        );

        $statement = $this->require($id, $idShop);
        $this->dispatchEvent('withdrawal.accepted', $statement);

        return $statement;
    }

    /** @return array<string,mixed> zaktualizowane oświadczenie */
    public function reject(int $id, int $idShop, string $reason): array
    {
        $statement = $this->require($id, $idShop);

        if (in_array($statement['status'], ['accepted', 'rejected'], true)) {
            throw new InvalidTransitionException('An accepted or already rejected statement cannot be rejected.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidTransitionException('A reason for rejection is required.');
        }

        Db::getInstance()->update(
            'quince_withdrawal',
            ['status' => 'rejected', 'reject_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')],
            'id_withdrawal = ' . $id
        );

        $statement = $this->require($id, $idShop);
        $this->dispatchEvent('withdrawal.rejected', $statement);

        return $statement;
    }

    /** @return array<string,mixed> */
    private function require(int $id, int $idShop): array
    {
        $statement = $this->repository->get($id, $idShop);

        if ($statement === null) {
            throw new StatementNotFoundException('That statement does not exist.');
        }

        return $statement;
    }

    /**
     * Zdarzenie wychodzące ze sklepu.
     *
     * Odbiorcę wskazuje sklep w module `quince_events`, w jego ekranie
     * ustawień. Ten moduł nie zna ani adresu, ani tego, co stoi po drugiej
     * stronie. Bez zainstalowanego `quince_events` nie dzieje się nic,
     * a przejście statusu i tak jest zapisane.
     *
     * @param array<string,mixed> $statement
     */
    private function dispatchEvent(string $type, array $statement): void
    {
        $zdarzenia = Module::getInstanceByName('quince_events');

        if (!$zdarzenia || !class_exists(\Quince\Events\Event\EventDispatcher::class)) {
            return;
        }

        $data = [
            'id_withdrawal' => (int) $statement['id_withdrawal'],
            'reference' => (string) $statement['reference'],
            'id_order' => (int) $statement['id_order'],
            'status' => (string) $statement['status'],
            'customer' => [
                'id_customer' => (int) $statement['id_customer'],
                'email' => (string) $statement['customer_email'],
                'name' => (string) $statement['customer_name'],
            ],
        ];

        if (!empty($statement['reject_reason'])) {
            $data['reject_reason'] = (string) $statement['reject_reason'];
        }

        (new \Quince\Events\Event\EventDispatcher(new \Quince\Events\Event\EventOutbox()))->dispatch(
            new \Quince\Events\Event\ShopEvent(type: $type, data: $data)
        );
    }
}
