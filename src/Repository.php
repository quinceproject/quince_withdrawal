<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal;

use Db;
use DbQuery;

/**
 * Dostęp do `quince_withdrawal` i `quince_withdrawal_consent`.
 */
final class Repository
{
    public const PER_PAGE = 20;

    /**
     * Lista oświadczeń danego sklepu, najnowsze pierwsze, jedna strona.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(int $idShop, int $page = 1): array
    {
        $offset = max(0, ($page - 1) * self::PER_PAGE);

        $query = (new DbQuery())
            ->select('w.*, o.reference AS order_reference')
            ->from('quince_withdrawal', 'w')
            ->innerJoin('orders', 'o', 'o.id_order = w.id_order')
            ->where('w.id_shop = ' . $idShop)
            ->orderBy('w.date_add DESC')
            ->limit(self::PER_PAGE, $offset);

        $rows = Db::getInstance()->executeS($query);

        return is_array($rows) ? $rows : [];
    }

    /** Liczba wszystkich oświadczeń sklepu — do wyliczenia stron. */
    public function count(int $idShop): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'quince_withdrawal WHERE id_shop = ' . $idShop
        );
    }

    /**
     * Oświadczenia jednego klienta, przez wszystkie jego zamówienia — lista
     * "Moje odstąpienia" w koncie klienta.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByCustomer(int $idCustomer): array
    {
        $query = (new DbQuery())
            ->select('w.*, o.reference AS order_reference')
            ->from('quince_withdrawal', 'w')
            ->innerJoin('orders', 'o', 'o.id_order = w.id_order')
            ->where('w.id_customer = ' . $idCustomer)
            ->orderBy('w.date_add DESC');

        $rows = Db::getInstance()->executeS($query);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Najnowsze oświadczenie dla zamówienia — do bloku na stronie zamówienia.
     * Bez filtra sklepu: zamówienie już wie, do którego sklepu należy.
     *
     * @return array<string,mixed>|null
     */
    public function getByOrder(int $idOrder): ?array
    {
        $query = (new DbQuery())
            ->select('w.*')
            ->from('quince_withdrawal', 'w')
            ->where('w.id_order = ' . $idOrder)
            ->orderBy('w.date_add DESC');

        $row = Db::getInstance()->getRow($query);

        return is_array($row) ? $row : null;
    }

    /**
     * Oświadczenie już zapisane pod tym identyfikatorem systemu zewnętrznego —
     * do idempotencji przy wpisywaniu z CEP. Drugie żądanie z tym samym
     * `externalId` ma zwrócić to, co już jest, nie założyć drugi wiersz.
     *
     * @return array<string,mixed>|null
     */
    public function findByExternalId(int $idShop, string $externalId): ?array
    {
        $query = (new DbQuery())
            ->select('w.*, o.reference AS order_reference')
            ->from('quince_withdrawal', 'w')
            ->innerJoin('orders', 'o', 'o.id_order = w.id_order')
            ->where('w.id_shop = ' . $idShop)
            ->where('w.external_id = "' . pSQL($externalId) . '"');

        $row = Db::getInstance()->getRow($query);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function get(int $idWithdrawal, int $idShop): ?array
    {
        $query = (new DbQuery())
            ->select('w.*, o.reference AS order_reference, o.date_add AS order_date')
            ->from('quince_withdrawal', 'w')
            ->innerJoin('orders', 'o', 'o.id_order = w.id_order')
            ->where('w.id_withdrawal = ' . $idWithdrawal)
            ->where('w.id_shop = ' . $idShop);

        $row = Db::getInstance()->getRow($query);

        return is_array($row) ? $row : null;
    }

    /**
     * Pozycje zamówienia, do których odnosi się oświadczenie — całe zamówienie,
     * nie wybrane pozycje (częściowe odstąpienie nie jest w zakresie v1).
     *
     * @return array<int,array{id_order_detail:int,product_name:string,product_quantity:int,withdrawal_mode:string,unit_price:float,total_price:float}>
     */
    public function orderItems(int $idOrder): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT od.id_order_detail, od.product_name, od.product_quantity,
                    od.total_price_tax_incl, od.unit_price_tax_incl,
                    COALESCE(pe.quince_withdrawal_mode, "") AS withdrawal_mode
             FROM ' . _DB_PREFIX_ . 'order_detail od
             LEFT JOIN ' . _DB_PREFIX_ . 'product_extra pe ON pe.id_product = od.product_id
             WHERE od.id_order = ' . (int) $idOrder
        );

        return array_map(
            static fn (array $r): array => [
                'id_order_detail' => (int) $r['id_order_detail'],
                'product_name' => (string) $r['product_name'],
                'product_quantity' => (int) $r['product_quantity'],
                'withdrawal_mode' => (string) $r['withdrawal_mode'],
                'unit_price' => (float) $r['unit_price_tax_incl'],
                'total_price' => (float) $r['total_price_tax_incl'],
            ],
            is_array($rows) ? $rows : []
        );
    }

    /**
     * Oświadczenia zmienione od danej chwili — do odpytywania przez Admin API
     * (CEP woła co ok. 5 min). `date_upd` musi się zmieniać przy KAŻDEJ
     * zmianie, łącznie z zapisem rozliczenia, inaczej CEP nigdy się o niej
     * nie dowie.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listUpdatedSince(int $idShop, ?string $updatedSince, int $limit, int $offset): array
    {
        $query = (new DbQuery())
            ->select('w.*, o.reference AS order_reference')
            ->from('quince_withdrawal', 'w')
            ->innerJoin('orders', 'o', 'o.id_order = w.id_order')
            ->where('w.id_shop = ' . $idShop)
            ->orderBy('w.date_upd ASC')
            ->limit($limit, $offset);

        if ($updatedSince !== null) {
            $query->where('w.date_upd >= "' . pSQL($updatedSince) . '"');
        }

        $rows = Db::getInstance()->executeS($query);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>|null */
    public function consentForOrder(int $idOrder): ?array
    {
        $query = (new DbQuery())
            ->select('mode, statement, date_add')
            ->from('quince_withdrawal_consent')
            ->where('id_order = ' . $idOrder)
            ->orderBy('date_add DESC');

        $row = Db::getInstance()->getRow($query);

        return is_array($row) ? $row : null;
    }

    /** Data pierwszego wejścia zamówienia w dany status — punkt startu terminu. */
    public function statusDate(int $idOrder, int $idOrderState): ?string
    {
        if ($idOrderState <= 0) {
            return null;
        }

        $date = Db::getInstance()->getValue(
            'SELECT MIN(date_add) FROM ' . _DB_PREFIX_ . 'order_history
             WHERE id_order = ' . (int) $idOrder . ' AND id_order_state = ' . $idOrderState
        );

        return $date !== false && $date !== null ? (string) $date : null;
    }

    public function countByStatus(int $idShop, string $status): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'quince_withdrawal
             WHERE id_shop = ' . $idShop . ' AND status = "' . pSQL($status) . '"'
        );
    }

    /**
     * Wszystkie oświadczenia sklepu w danym statusie, bez stronicowania — do
     * liczenia rzeczy po terminie, gdzie trzeba przejrzeć je wszystkie
     * niezależnie od tego, która strona listy akurat się wyświetla.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByStatus(int $idShop, string $status): array
    {
        $query = (new DbQuery())
            ->select('w.*')
            ->from('quince_withdrawal', 'w')
            ->where('w.id_shop = ' . $idShop)
            ->where('w.status = "' . pSQL($status) . '"');

        $rows = Db::getInstance()->executeS($query);

        return is_array($rows) ? $rows : [];
    }
}
