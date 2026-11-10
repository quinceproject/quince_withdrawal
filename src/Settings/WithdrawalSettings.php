<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\Settings;

use Configuration;
use Quince\Engine\Settings\ConfigStore;

/**
 * Ustawienia prawa odstąpienia.
 *
 * Trzy rzeczy do rozstrzygnięcia przez sklep: kto ma prawo odstąpienia
 * (klient czy też firma), od jakiego zdarzenia liczy się termin, i jak długi
 * ten termin jest.
 */
final class WithdrawalSettings
{
    private const PREFIX = 'QUINCE_WITHDRAWAL_';

    /** Prawo dotyczy każdego klienta — najbezpieczniejszy wybór, gdy sklep nie rozdziela B2B/B2C. */
    public const QUALIFICATION_ALL = 'all';

    /** Zamówienie z nazwą firmy w adresie faktury traktujemy jako B2B — prawo nie dotyczy. */
    public const QUALIFICATION_COMPANY_FIELD = 'company_field';

    /** Rozpoznanie po grupie klienta — sklep sam decyduje, która grupa jest firmowa. */
    public const QUALIFICATION_CUSTOMER_GROUP = 'customer_group';

    public const TERM_DAYS_MIN = 14;
    public const TERM_DAYS_MAX = 30;

    /** @var array<string,string> klucz => wartość domyślna */
    private const SCHEMA = [
        // --- kto ma prawo odstąpienia ---
        'QUALIFICATION_MODE' => self::QUALIFICATION_ALL,
        'EXCLUDED_GROUP' => '0',

        // --- od czego liczy się termin ---
        // Dostawa jest ustawowym startem terminu dla towaru — ale "dostawa"
        // w PrestaShopie to status, który każdy sklep nazywa inaczej, więc
        // admin wskazuje, który z jego statusów naprawdę nią jest.
        'DELIVERY_STATUS' => '0',
        // Dla usługi i treści cyfrowej termin liczy się od zawarcia umowy —
        // domyślnie status płatności przyjętej, z możliwością zmiany.
        'SERVICE_START_STATUS' => '0',
        // Status, od którego treść cyfrową uznajemy za dostarczoną (decyduje
        // o tym, czy wyjątek z Art. 16(m) już zaszedł). Puste = ten sam status
        // co dostawa towaru.
        'DIGITAL_DELIVERED_STATUS' => '0',

        // --- jak długi jest termin ---
        'TERM_DAYS' => '14',

        // --- jak rozliczany jest zwrot pieniędzy ---
        // Domyślnie przez natywne Credit Slips PrestaShopa — sklep, który
        // fakturuje przez system zewnętrzny (gdzie korekta musi poprawiać
        // TĘ konkretną fakturę), wyłącza to i obsługa dostaje neutralną
        // instrukcję zamiast odesłania do ekranu, którego i tak nie używa.
        'NATIVE_CREDIT_SLIP' => '1',
    ];

    /**
     * Odczyt jest zawsze w kontekście KONKRETNEGO sklepu, nie bieżącego widoku
     * panelu: ekran oświadczenia otwarty z poziomu „wszystkie sklepy" ma liczyć
     * termin według reguł sklepu, do którego należy zamówienie, a nie według
     * tego, co panel akurat ma ustawione w przełączniku u góry. Bez jawnego
     * `$idShop` `Configuration::get()` w widoku „wszystkie sklepy" nie trafia
     * w żaden wiersz i cicho spada na wartość domyślną.
     *
     * @return array<string,string>
     */
    public function all(?int $idShop = null): array
    {
        $out = [];

        foreach (self::SCHEMA as $key => $default) {
            $value = Configuration::get(self::PREFIX . $key, null, null, $idShop);
            $out[$key] = ($value === false || $value === null) ? $default : (string) $value;
        }

        return $out;
    }

    /** @return array<string,string> */
    public function defaults(): array
    {
        return self::SCHEMA;
    }

    public function qualificationMode(?int $idShop = null): string
    {
        $mode = $this->all($idShop)['QUALIFICATION_MODE'];

        return in_array($mode, [self::QUALIFICATION_ALL, self::QUALIFICATION_COMPANY_FIELD, self::QUALIFICATION_CUSTOMER_GROUP], true)
            ? $mode
            : self::QUALIFICATION_ALL;
    }

    public function excludedGroup(?int $idShop = null): int
    {
        return (int) $this->all($idShop)['EXCLUDED_GROUP'];
    }

    public function deliveryStatus(?int $idShop = null): int
    {
        return (int) $this->all($idShop)['DELIVERY_STATUS'];
    }

    public function serviceStartStatus(?int $idShop = null): int
    {
        return (int) $this->all($idShop)['SERVICE_START_STATUS'];
    }

    /** Status dostarczenia treści cyfrowej — status dostawy towaru, gdy nic innego nie wybrano. */
    public function digitalDeliveredStatus(?int $idShop = null): int
    {
        $own = (int) $this->all($idShop)['DIGITAL_DELIVERED_STATUS'];

        return $own > 0 ? $own : $this->deliveryStatus($idShop);
    }

    public function termDays(?int $idShop = null): int
    {
        return max(self::TERM_DAYS_MIN, min(self::TERM_DAYS_MAX, (int) $this->all($idShop)['TERM_DAYS']));
    }

    public function useNativeCreditSlip(?int $idShop = null): bool
    {
        return $this->all($idShop)['NATIVE_CREDIT_SLIP'] === '1';
    }

    /**
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        foreach (self::SCHEMA as $key => $default) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            ConfigStore::set(self::PREFIX . $key, $this->sanitize($key, (string) $values[$key]));
        }
    }

    private function sanitize(string $key, string $value): string
    {
        switch ($key) {
            case 'QUALIFICATION_MODE':
                return in_array($value, [self::QUALIFICATION_ALL, self::QUALIFICATION_COMPANY_FIELD, self::QUALIFICATION_CUSTOMER_GROUP], true)
                    ? $value
                    : self::QUALIFICATION_ALL;

            case 'TERM_DAYS':
                return (string) max(self::TERM_DAYS_MIN, min(self::TERM_DAYS_MAX, (int) $value));

            case 'EXCLUDED_GROUP':
            case 'DELIVERY_STATUS':
            case 'SERVICE_START_STATUS':
            case 'DIGITAL_DELIVERED_STATUS':
                return (string) max(0, (int) $value);

            case 'NATIVE_CREDIT_SLIP':
                return $value === '1' ? '1' : '0';

            default:
                return $value;
        }
    }
}
