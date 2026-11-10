<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use PrestaShopException;
use Quince\Withdrawal\ApiPlatform\Processor\AcceptProcessor;
use Quince\Withdrawal\ApiPlatform\Processor\ConfirmProcessor;
use Quince\Withdrawal\ApiPlatform\Processor\CreateProcessor;
use Quince\Withdrawal\ApiPlatform\Processor\RejectProcessor;
use Quince\Withdrawal\ApiPlatform\Processor\SettlementProcessor;
use Quince\Withdrawal\ApiPlatform\Provider\StatementProvider;
use Quince\Withdrawal\Service\Exception\DuplicateStatementException;
use Quince\Withdrawal\Service\Exception\InvalidStatementDataException;
use Quince\Withdrawal\Service\Exception\InvalidTransitionException;
use Quince\Withdrawal\Service\Exception\OrderNotFoundException;
use Quince\Withdrawal\Service\Exception\StatementNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oświadczenie o odstąpieniu w Admin API.
 *
 * Confirm/accept/reject wołają DOKŁADNIE ten sam serwis
 * (`Quince\Withdrawal\Service\StatementWorkflow`) co przyciski panelu — ten
 * sam mail do klienta, ten sam natywny zwrot towaru, te same reguły przejść.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/quince-withdrawal/statements',
            provider: StatementProvider::class,
            extraProperties: ['scopes' => ['quince_withdrawal_read']],
            openapiContext: [
                'summary' => 'Withdrawal statements changed since a given moment',
                'description' => 'Filter with `updatedSince` (ISO 8601) and `shopId`. Page with `page` and `limit` '
                    . '(50 by default, 200 at most), ordered oldest-changed first. Take the `updatedAt` of the '
                    . 'last item on the page as `updatedSince` for the next call.',
            ],
        ),
        new Get(
            uriTemplate: '/quince-withdrawal/statements/{id}',
            provider: StatementProvider::class,
            extraProperties: ['scopes' => ['quince_withdrawal_read']],
            openapiContext: ['summary' => 'One statement by its id'],
        ),
        new Post(
            uriTemplate: '/quince-withdrawal/statements/{id}/confirm',
            provider: StatementProvider::class,
            processor: ConfirmProcessor::class,
            extraProperties: ['scopes' => ['quince_withdrawal_write']],
            openapiContext: ['summary' => 'Confirm a submitted statement (creates the native merchandise return for goods)'],
        ),
        new Post(
            uriTemplate: '/quince-withdrawal/statements/{id}/accept',
            provider: StatementProvider::class,
            processor: AcceptProcessor::class,
            extraProperties: ['scopes' => ['quince_withdrawal_write']],
            openapiContext: ['summary' => 'Accept the return of an acknowledged statement'],
        ),
        new Post(
            uriTemplate: '/quince-withdrawal/statements/{id}/reject',
            provider: StatementProvider::class,
            processor: RejectProcessor::class,
            extraProperties: ['scopes' => ['quince_withdrawal_write']],
            openapiContext: [
                'summary' => 'Reject a statement',
                'description' => 'Body: `{"rejectReason": "..."}`. A reason is required.',
            ],
        ),
        new Patch(
            uriTemplate: '/quince-withdrawal/statements/{id}/settlement',
            provider: StatementProvider::class,
            processor: SettlementProcessor::class,
            extraProperties: ['scopes' => ['quince_withdrawal_write']],
            openapiContext: [
                'summary' => 'Record how and when this withdrawal was refunded',
                'description' => '`settlementStatus` is `pending` or `refunded`. `settlementDocument` is the credit '
                    . 'note number from the external accounting system, if any.',
            ],
        ),
        new Post(
            uriTemplate: '/quince-withdrawal/statements',
            processor: CreateProcessor::class,
            extraProperties: ['scopes' => ['quince_withdrawal_write']],
            openapiContext: [
                'summary' => 'Record a statement submitted outside the shop\'s own form',
                'description' => 'For a withdrawal the customer sent by e-mail, post or another unambiguous '
                    . 'channel, never for one made through the shop\'s own form, which always goes through '
                    . '`actionValidateOrder`/the front controller, not this endpoint. Always covers the whole '
                    . 'order; the module has no concept of a partial withdrawal. One order can carry at most '
                    . 'one statement, from either channel: a second `POST` for the same order is rejected '
                    . 'unless `externalId` matches an existing statement, in which case that existing statement '
                    . 'is returned unchanged. Required: `orderId`, `submittedAt` (ISO 8601 with offset, not in '
                    . 'the future, not before the order itself), `channel` (`email`, `post` or `other`; '
                    . '`shop_form` is reserved), `enteredBy`, `externalId`. Optional: `reason`, `note` (internal, '
                    . 'never shown to the customer or printed on the PDF), `sendConfirmation` (default `true`). '
                    . 'A statement entered past the legal deadline is still recorded, not auto-rejected: the '
                    . 'response\'s own `deadline` field already says whether it has passed; staff decide through '
                    . '`reject`.',
            ],
        ),
    ],
    exceptionToStatus: [
        StatementNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        DuplicateStatementException::class => Response::HTTP_CONFLICT,
        InvalidTransitionException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidStatementDataException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        PrestaShopException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Statement
{
    #[ApiProperty(identifier: true)]
    public int $id = 0;

    public string $reference = '';

    public int $shopId = 0;

    public int $orderId = 0;

    public string $orderReference = '';

    public int $customerId = 0;

    public string $customerName = '';

    public string $customerEmail = '';

    /** `submitted`, `acknowledged`, `accepted` or `rejected`. */
    public string $status = '';

    /** Treść od klienta — prawo nie wymaga podania przyczyny, więc często puste. */
    public string $reason = '';

    public ?string $rejectReason = null;

    /** ISO 8601 z sekundami i strefą — od tego biegnie termin na zwrot pieniędzy. */
    public string $submittedAt = '';

    public string $updatedAt = '';

    public ?int $orderReturnId = null;

    public ?int $orderSlipId = null;

    /**
     * Pozycje zamówienia, których dotyczy oświadczenie (całość, nie wybrane
     * pozycje). Każda: `idOrderDetail`, `productName`, `quantity`,
     * `unitPrice`, `totalPrice`, `mode` (standard/custom_made/
     * sealed_hygiene/service/digital).
     *
     * @var array<int,array<string,mixed>>
     */
    public array $items = [];

    public string $kind = '';

    public ?string $deadline = null;

    /**
     * Zgoda na wczesne wykonanie, gdy istnieje: `mode`, `statement`, `dateAdd`.
     * `null`, gdy zamówienie nie zawiera usługi/treści cyfrowej albo klient
     * jej nie złożył.
     *
     * @var array<string,mixed>|null
     */
    public ?array $earlyPerformanceConsent = null;

    public string $settlementStatus = 'pending';

    public ?float $settlementAmount = null;

    public ?string $settlementCurrency = null;

    public ?string $settlementMethod = null;

    public ?string $settlementDocument = null;

    public ?string $settlementSource = null;

    public ?string $settledAt = null;

    /** `shop_form` (domyślnie — formularz klienta) albo `email`/`post`/`other` przy wpisie z zewnątrz. */
    public string $channel = 'shop_form';

    /** Kto wpisał oświadczenie spoza formularza (pracownik systemu zewnętrznego). Puste dla `shop_form`. */
    public ?string $enteredBy = null;

    /** Notatka wewnętrzna obsługi — nie trafia do klienta ani do PDF. */
    public ?string $note = null;

    /** Identyfikator po stronie systemu, który wpisał oświadczenie — do idempotencji zapisu. */
    public ?string $externalId = null;

    /**
     * Wyłącznie przy zapisie (`POST`): czy wysłać klientowi standardowy mail
     * z PDF potwierdzenia. Domyślnie `true`. Pole zapisowe, nie odczytowe —
     * odczyt nie mówi, czy mail faktycznie wyszedł.
     */
    public ?bool $sendConfirmation = null;
}
