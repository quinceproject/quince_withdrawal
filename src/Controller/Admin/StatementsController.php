<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\Controller\Admin;

use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\DeadlineCalculator;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kolejka oświadczeń o odstąpieniu: ekran domyślny zakładki.
 */
class StatementsController extends WithdrawalController
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $idShop = $this->getContextShopId();
        $calculator = new DeadlineCalculator($this->repository, $this->settings);
        $today = date('Y-m-d');

        $total = $this->repository->count($idShop);
        $pages = max(1, (int) ceil($total / Repository::PER_PAGE));
        $page = min($pages, max(1, (int) $request->query->get('page', 1)));

        // Niezależnie od strony listy: liczba spraw, którym termin mija dziś
        // albo już minął, musi objąć WSZYSTKIE zgłoszone oświadczenia, nie
        // tylko te na bieżącej stronie.
        $urgent = 0;
        foreach ($this->repository->listByStatus($idShop, 'submitted') as $statement) {
            $idOrder = (int) $statement['id_order'];
            $idOrderShop = (int) $statement['id_shop'];
            $startDate = $calculator->startDate($idOrder, $idOrderShop);
            $deadline = $startDate !== null ? $calculator->deadline($startDate, $idOrderShop) : null;

            if ($deadline !== null && $deadline <= $today) {
                ++$urgent;
            }
        }

        $statements = array_map(function (array $statement) use ($calculator): array {
            $idOrder = (int) $statement['id_order'];
            $idOrderShop = (int) $statement['id_shop'];
            $startDate = $calculator->startDate($idOrder, $idOrderShop);

            $statement['is_goods'] = $calculator->isGoods($idOrder);
            $statement['deadline'] = $startDate !== null ? $calculator->deadline($startDate, $idOrderShop) : null;
            $statement['native_credit_slip'] = $this->settings->useNativeCreditSlip($idOrderShop);

            return $statement;
        }, $this->repository->list($idShop, $page));

        return $this->renderPanel(
            '@Modules/quince_withdrawal/views/templates/admin/statements.html.twig',
            [
                'statements' => $statements,
                'counts' => [
                    'submitted' => $this->repository->countByStatus($idShop, 'submitted'),
                    'acknowledged' => $this->repository->countByStatus($idShop, 'acknowledged'),
                    'urgent' => $urgent,
                ],
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
            ],
            'quince_withdrawal_statements'
        );
    }
}
