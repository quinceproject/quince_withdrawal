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
use DateTimeImmutable;
use Exception;
use Order;
use Quince\Withdrawal\ApiPlatform\Resources\Statement;
use Quince\Withdrawal\ApiPlatform\StatementMapper;
use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\Exception\DuplicateStatementException;
use Quince\Withdrawal\Service\Exception\InvalidStatementDataException;
use Quince\Withdrawal\Service\Exception\OrderNotFoundException;
use Quince\Withdrawal\Service\StatementService;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\RequestStack;
use Validate;

/**
 * Zapisuje oświadczenie, które klient złożył POZA formularzem sklepu
 * (mailem, listem albo innym jednoznacznym sposobem), wpisane przez obsługę
 * w CEP. Woła ten sam `StatementService::submit()`, co formularz klienta:
 * ten sam numer, PDF, mail, zdarzenie `withdrawal.submitted`, tylko
 * z datą złożenia podaną w żądaniu zamiast chwili zapisu.
 *
 * @implements ProcessorInterface<Statement, Statement>
 */
final class CreateProcessor implements ProcessorInterface
{
    private const CHANNELS = ['email', 'post', 'other'];

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
            throw new InvalidStatementDataException('Unexpected payload for a new withdrawal statement.');
        }

        $idShop = $this->idShop();

        if ($idShop === null) {
            throw new InvalidStatementDataException('shopId is required.');
        }

        if ($data->orderId <= 0) {
            throw new InvalidStatementDataException('orderId is required.');
        }

        if (!in_array($data->channel, self::CHANNELS, true)) {
            throw new InvalidStatementDataException(
                'channel must be one of: ' . implode(', ', self::CHANNELS) . '.'
            );
        }

        if ($data->enteredBy === null || trim($data->enteredBy) === '') {
            throw new InvalidStatementDataException('enteredBy is required.');
        }

        // Limity kolumn (`varchar(255)`/`varchar(64)`/`text`). Inaczej
        // przekroczenie wywala surowy błąd SQL w trybie strict, nie czysty
        // 422 z nazwą pola.
        if (mb_strlen($data->enteredBy) > 255) {
            throw new InvalidStatementDataException('enteredBy must be at most 255 characters.');
        }

        if ($data->externalId === null || trim($data->externalId) === '') {
            throw new InvalidStatementDataException('externalId is required.');
        }

        if (mb_strlen($data->externalId) > 64) {
            throw new InvalidStatementDataException('externalId must be at most 64 characters.');
        }

        if ($data->note !== null && strlen($data->note) > 65535) {
            throw new InvalidStatementDataException('note must be at most 65535 bytes.');
        }

        $submittedAt = $this->parseSubmittedAt($data->submittedAt);

        $order = new Order($data->orderId);

        if (!Validate::isLoadedObject($order) || (int) $order->id_shop !== $idShop) {
            throw new OrderNotFoundException('That order does not exist in this shop.');
        }

        if ($submittedAt > new DateTimeImmutable()) {
            throw new InvalidStatementDataException('submittedAt cannot be in the future.');
        }

        if ($submittedAt < new DateTimeImmutable((string) $order->date_add)) {
            throw new InvalidStatementDataException('submittedAt cannot be before the order itself.');
        }

        $mapper = new StatementMapper($this->repository, $this->settings);

        // Idempotencja: drugie żądanie z tym samym externalId zwraca to, co
        // już jest, zamiast zakładać drugi wiersz.
        $istniejace = $this->repository->findByExternalId($idShop, $data->externalId);

        if ($istniejace !== null) {
            return $mapper->map($istniejace);
        }

        // Jedno zamówienie, jedno oświadczenie: ta sama reguła co formularz
        // klienta (`request.php`, `Repository::getByOrder()`). CEP ma się
        // podpiąć do istniejącego, więc błąd niesie jego id i referencję,
        // nie tylko informację, że zapis się nie udał.
        $juzIstnieje = $this->repository->getByOrder((int) $order->id);

        if ($juzIstnieje !== null) {
            throw new DuplicateStatementException(sprintf(
                'This order already has a withdrawal statement: id=%d, reference=%s.',
                (int) $juzIstnieje['id_withdrawal'],
                (string) $juzIstnieje['reference']
            ));
        }

        $statement = (new StatementService($this->repository, $this->settings))->submit(
            $order,
            trim((string) $data->reason),
            $submittedAt,
            $data->channel,
            $data->enteredBy,
            $data->note,
            $data->externalId,
            $data->sendConfirmation ?? true
        );

        if ($statement === null) {
            throw new InvalidStatementDataException('Could not save the statement.');
        }

        return $mapper->map($statement);
    }

    private function parseSubmittedAt(string $value): DateTimeImmutable
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidStatementDataException('submittedAt is required.');
        }

        // `DateTimeImmutable` sam przyjąłby też "now", "+1 day" czy gołą
        // datę bez strefy. Kontrakt wymaga konkretnie ISO 8601 z offsetem,
        // więc sprawdzamy to jawnie, zamiast ufać wszystkiemu, co sparsuje.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new InvalidStatementDataException(
                'submittedAt must be an ISO 8601 date and time with an offset, e.g. 2026-10-16T10:00:00+02:00.'
            );
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidStatementDataException('submittedAt is not a readable date.');
        }
    }

    private function idShop(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $wartosc = $request !== null ? $request->query->get('shopId') : null;

        return $wartosc !== null && $wartosc !== '' ? (int) $wartosc : null;
    }
}
