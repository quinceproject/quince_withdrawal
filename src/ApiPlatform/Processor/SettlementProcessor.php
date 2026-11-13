<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Db;
use Module;
use PrestaShopException;
use Quince\Withdrawal\ApiPlatform\Resources\Statement;
use Quince\Withdrawal\ApiPlatform\StatementMapper;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Zapis rozliczenia odesłany przez CEP (albo dowolny inny system
 * zewnętrzny): kiedy i jak pieniądze faktycznie wróciły do klienta.
 *
 * Status `accepted` sam w sobie nie oznacza zwrotu pieniędzy, to tylko
 * zgoda sklepu. To jedyne miejsce, które zapisuje, że zwrot faktycznie
 * się odbył.
 *
 * **`id_shop` nie wchodzi do WHERE przy `UPDATE` poniżej.** `shopId`
 * jest jedynym filtrem sklepu w tej operacji, więc musi pochodzić z
 * zapytania, nigdy z ciała: ciało mogłoby nadpisać odczytany wcześniej
 * zasób i wskazać zapis do wiersza innego sklepu.
 *
 * @implements ProcessorInterface<Statement, Statement>
 */
final class SettlementProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Statement
    {
        if (!$data instanceof Statement) {
            throw new PrestaShopException('Unexpected payload for the withdrawal settlement.');
        }

        $id = (int) ($uriVariables['id'] ?? 0);
        $idShop = $this->idShop();

        if ($id <= 0 || $idShop === null) {
            throw new PrestaShopException('That statement does not exist.');
        }

        $row = $this->repository->get($id, $idShop);

        if ($row === null) {
            throw new PrestaShopException('That statement does not exist.');
        }

        if (!in_array($data->settlementStatus, ['pending', 'refunded'], true)) {
            throw new PrestaShopException('settlementStatus must be "pending" or "refunded".');
        }

        Db::getInstance()->update(
            'quince_withdrawal',
            [
                'settlement_status' => pSQL($data->settlementStatus),
                'settlement_amount' => $data->settlementAmount,
                'settlement_currency' => $data->settlementCurrency !== null ? pSQL($data->settlementCurrency) : null,
                'settlement_method' => $data->settlementMethod !== null ? pSQL($data->settlementMethod) : null,
                'settlement_document' => $data->settlementDocument !== null ? pSQL($data->settlementDocument) : null,
                'settlement_source' => $data->settlementSource !== null ? pSQL($data->settlementSource) : null,
                'settled_at' => $data->settledAt !== null ? date('Y-m-d H:i:s', strtotime($data->settledAt) ?: time()) : null,
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            'id_withdrawal = ' . $id,
            0,
            true // $null_values: PHP `null` ma zapisać SQL NULL, nie pusty string.
        );

        $updated = $this->repository->get($id, $idShop);

        if ($updated === null) {
            throw new PrestaShopException('That statement does not exist.');
        }

        if ($data->settlementStatus === 'refunded') {
            $this->dispatchEvent($updated);
        }

        return (new StatementMapper($this->repository, $this->settings))->map($updated);
    }

    private function idShop(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $value = $request !== null ? $request->query->get('shopId') : null;

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * Zdarzenie wychodzące ze sklepu.
     *
     * Odbiorcę wskazuje sklep w module `quince_events`, w jego ekranie
     * ustawień. Ten moduł nie zna ani adresu, ani tego, co stoi po drugiej
     * stronie. Bez zainstalowanego `quince_events` nie dzieje się nic,
     * a rozliczenie i tak jest zapisane.
     *
     * @param array<string,mixed> $statement
     */
    private function dispatchEvent(array $statement): void
    {
        $zdarzenia = Module::getInstanceByName('quince_events');

        if (!$zdarzenia || !class_exists(\Quince\Events\Event\EventDispatcher::class)) {
            return;
        }

        (new \Quince\Events\Event\EventDispatcher(new \Quince\Events\Event\EventOutbox()))->dispatch(
            new \Quince\Events\Event\ShopEvent(
                type: 'withdrawal.settled',
                data: [
                    'id_withdrawal' => (int) $statement['id_withdrawal'],
                    'reference' => (string) $statement['reference'],
                    'id_order' => (int) $statement['id_order'],
                    'settlement_amount' => $statement['settlement_amount'] !== null ? (float) $statement['settlement_amount'] : null,
                    'settlement_currency' => $statement['settlement_currency'] !== null ? (string) $statement['settlement_currency'] : null,
                    'settlement_method' => $statement['settlement_method'] !== null ? (string) $statement['settlement_method'] : null,
                    'settlement_document' => $statement['settlement_document'] !== null ? (string) $statement['settlement_document'] : null,
                    'settlement_source' => $statement['settlement_source'] !== null ? (string) $statement['settlement_source'] : null,
                    'settled_at' => $statement['settled_at'] !== null ? (string) $statement['settled_at'] : null,
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
