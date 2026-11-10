<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Quince\Withdrawal\ApiPlatform\Resources\Statement;
use Quince\Withdrawal\ApiPlatform\StatementMapper;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Oświadczenia czytane przez Admin API — te same wiersze i ta sama logika
 * terminu co panel (`DeadlineCalculator`), żeby CEP nie liczył niczego
 * po swojemu z ryzykiem rozjazdu.
 *
 * @implements ProviderInterface<Statement>
 */
final class StatementProvider implements ProviderInterface
{
    private const PER_PAGE = 50;
    private const MAX_PER_PAGE = 200;

    private readonly StatementMapper $mapper;

    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings,
        private readonly RequestStack $requestStack
    ) {
        $this->mapper = new StatementMapper($repository, $settings);
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     *
     * @return array<int,Statement>|Statement|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Statement|null
    {
        if (isset($uriVariables['id'])) {
            $idShop = $this->queryShopId();
            $row = $idShop !== null ? $this->repository->get((int) $uriVariables['id'], $idShop) : null;

            return $row !== null ? $this->mapper->map($row) : null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $query = $request !== null ? $request->query->all() : [];

        $idShop = $this->queryShopId();

        if ($idShop === null) {
            return [];
        }

        $updatedSince = isset($query['updatedSince']) && $query['updatedSince'] !== ''
            ? date('Y-m-d H:i:s', strtotime((string) $query['updatedSince']) ?: 0)
            : null;

        $limit = min(self::MAX_PER_PAGE, max(1, (int) ($query['limit'] ?? self::PER_PAGE)));
        $page = max(1, (int) ($query['page'] ?? 1));

        $rows = $this->repository->listUpdatedSince($idShop, $updatedSince, $limit, ($page - 1) * $limit);

        return array_map(fn (array $row): Statement => $this->mapper->map($row), $rows);
    }

    private function queryShopId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $shopId = $request !== null ? $request->query->get('shopId') : null;

        return $shopId !== null && $shopId !== '' ? (int) $shopId : null;
    }
}
