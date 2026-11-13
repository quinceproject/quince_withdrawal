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
use PrestaShopException;
use Quince\Withdrawal\ApiPlatform\Resources\Statement;
use Quince\Withdrawal\ApiPlatform\StatementMapper;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\Exception\StatementNotFoundException;
use Quince\Withdrawal\Service\StatementWorkflow;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * **Zapisywalne jest wyłącznie przejście statusu.** `id` bierzemy wyłącznie
 * z adresu, a `shopId` wyłącznie z zapytania. Nigdy z ciała, które mogłoby
 * nadpisać odczytany wcześniej zasób (ten sam wzorzec co w każdym innym
 * endpoincie tego modułu).
 *
 * @implements ProcessorInterface<Statement, Statement>
 */
final class AcceptProcessor implements ProcessorInterface
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
            throw new PrestaShopException('Unexpected payload for accepting a withdrawal statement.');
        }

        $id = (int) ($uriVariables['id'] ?? 0);
        $idShop = $this->idShop();

        if ($id <= 0 || $idShop === null) {
            throw new StatementNotFoundException('That statement does not exist.');
        }

        $updated = (new StatementWorkflow($this->repository, $this->settings))->accept($id, $idShop);

        return (new StatementMapper($this->repository, $this->settings))->map($updated);
    }

    private function idShop(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $value = $request !== null ? $request->query->get('shopId') : null;

        return $value !== null && $value !== '' ? (int) $value : null;
    }
}
