<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\ApiPlatform;

use Quince\Withdrawal\ApiPlatform\Resources\Statement;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\DeadlineCalculator;
use Quince\Withdrawal\Settings\WithdrawalSettings;

/**
 * Wiersz `quince_withdrawal` → zasób API Platform.
 *
 * Jedno miejsce, bo i czytanie listy/szczegółu, i odpowiedź po
 * confirm/accept/reject/settlement muszą pokazać DOKŁADNIE to samo.
 */
final class StatementMapper
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings
    ) {
    }

    /** @param array<string,mixed> $row */
    public function map(array $row): Statement
    {
        $idOrder = (int) $row['id_order'];
        $idShop = (int) $row['id_shop'];
        $calculator = new DeadlineCalculator($this->repository, $this->settings);

        $items = array_map(
            static fn (array $item): array => [
                'idOrderDetail' => $item['id_order_detail'],
                'productName' => $item['product_name'],
                'quantity' => $item['product_quantity'],
                'unitPrice' => $item['unit_price'],
                'totalPrice' => $item['total_price'],
                'mode' => $item['withdrawal_mode'],
            ],
            $this->repository->orderItems($idOrder)
        );

        $startDate = $calculator->startDate($idOrder, $idShop);
        $consent = $this->repository->consentForOrder($idOrder);

        $statement = new Statement();
        $statement->id = (int) $row['id_withdrawal'];
        $statement->reference = (string) $row['reference'];
        $statement->shopId = $idShop;
        $statement->orderId = $idOrder;
        $statement->orderReference = (string) $row['order_reference'];
        $statement->customerId = (int) $row['id_customer'];
        $statement->customerName = (string) $row['customer_name'];
        $statement->customerEmail = (string) $row['customer_email'];
        $statement->status = (string) $row['status'];
        $statement->reason = (string) ($row['reason'] ?? '');
        $statement->rejectReason = $row['reject_reason'] !== null ? (string) $row['reject_reason'] : null;
        $statement->submittedAt = $this->iso8601((string) $row['date_add']);
        $statement->updatedAt = $this->iso8601((string) $row['date_upd']);
        $statement->orderReturnId = $row['id_order_return'] !== null ? (int) $row['id_order_return'] : null;
        $statement->orderSlipId = $row['id_order_slip'] !== null ? (int) $row['id_order_slip'] : null;
        $statement->items = $items;
        $statement->kind = $calculator->isGoods($idOrder) ? 'goods' : 'service_or_digital';
        $statement->deadline = $startDate !== null ? $calculator->deadline($startDate, $idShop) : null;
        $statement->earlyPerformanceConsent = $consent !== null ? [
            'mode' => $consent['mode'],
            'statement' => $consent['statement'],
            'dateAdd' => $this->iso8601((string) $consent['date_add']),
        ] : null;
        $statement->settlementStatus = (string) ($row['settlement_status'] ?? 'pending');
        $statement->settlementAmount = $row['settlement_amount'] !== null ? (float) $row['settlement_amount'] : null;
        $statement->settlementCurrency = $row['settlement_currency'] !== null ? (string) $row['settlement_currency'] : null;
        $statement->settlementMethod = $row['settlement_method'] !== null ? (string) $row['settlement_method'] : null;
        $statement->settlementDocument = $row['settlement_document'] !== null ? (string) $row['settlement_document'] : null;
        $statement->settlementSource = $row['settlement_source'] !== null ? (string) $row['settlement_source'] : null;
        $statement->settledAt = $row['settled_at'] !== null ? $this->iso8601((string) $row['settled_at']) : null;
        $statement->channel = (string) ($row['channel'] ?? 'shop_form');
        $statement->enteredBy = !empty($row['entered_by']) ? (string) $row['entered_by'] : null;
        $statement->note = !empty($row['note']) ? (string) $row['note'] : null;
        $statement->externalId = !empty($row['external_id']) ? (string) $row['external_id'] : null;

        return $statement;
    }

    /** Sklep liczy czas we własnej strefie; dla CEP dokładamy jawny offset, nie zgadujemy UTC. */
    private function iso8601(string $mysqlDateTime): string
    {
        $date = new \DateTime($mysqlDateTime, new \DateTimeZone(date_default_timezone_get()));

        return $date->format('c');
    }
}
