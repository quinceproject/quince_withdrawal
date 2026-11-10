<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Quince\\Withdrawal\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType;
use Quince\Withdrawal\Form\WithdrawalModeChoiceType;

/**
 * Quince Withdrawal — odstąpienie od umowy na odległość.
 *
 * Dyrektywa 2023/2673 (nowy art. 11a dyrektywy 2011/83/UE) wymaga formularza
 * odstąpienia dostępnego online oraz niezwłocznego potwierdzenia jego przyjęcia
 * na trwałym nośniku. Moduł prowadzi rejestr oświadczeń, liczy termin od
 * właściwego zdarzenia (dostawa dla towaru, zawarcie umowy dla usługi) i
 * rozpoznaje towary/usługi wyłączone z prawa odstąpienia (art. 16 dyrektywy
 * 2011/83/UE) przez pole dodatkowe produktu `withdrawal_mode`.
 *
 * Multisklepowy: każde oświadczenie i każde ustawienie należy do sklepu,
 * w którym zamówienie padło.
 */
class Quince_Withdrawal extends Module
{
    /** Nazwa pola dodatkowego na produkcie — rdzeń zrobi z niej kolumnę `quince_withdrawal_mode`. */
    public const FIELD_MODE = 'mode';

    private const DOMAIN_ADMIN = 'Modules.Quincewithdrawal.Admin';

    public function __construct()
    {
        $this->name = 'quince_withdrawal';
        $this->tab = 'administration';
        $this->version = '0.2.2';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.2.0', 'max' => '9.99.99'];
        $this->dependencies = ['quince_engine'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Quince Withdrawal', [], self::DOMAIN_ADMIN);
        $this->description = $this->trans(
            'The right of withdrawal from a distance contract: an online statement form, an acknowledgement on a durable medium, and the deadlines and exceptions the law sets for it.',
            [],
            self::DOMAIN_ADMIN
        );
        $this->confirmUninstall = $this->trans(
            'Uninstalling deletes every withdrawal statement on record. Export them first if you need to keep a copy.',
            [],
            self::DOMAIN_ADMIN
        );
    }

    // =========================================================================
    // INSTALL / UNINSTALL
    // =========================================================================

    public function install(): bool
    {
        $shops = Shop::getShops(true, null, true);

        return parent::install()
            && $this->installDb()
            && $this->registerHook($this->hooks(), $shops)
            && $this->installForShops($shops)
            && $this->installModuleGroups()
            && $this->installFields()
            && $this->installTabs();
    }

    public function uninstall(): bool
    {
        $this->uninstallTabs();
        $this->removeFields(true);
        $this->uninstallDb();

        foreach (array_keys((new \Quince\Withdrawal\Settings\WithdrawalSettings())->defaults()) as $key) {
            Configuration::deleteByName('QUINCE_WITHDRAWAL_' . $key);
        }

        return parent::uninstall();
    }

    /** @return string[] */
    private function hooks(): array
    {
        return [
            'actionFrontControllerSetMedia',
            'displayAdminOrderMain',
            'displayOrderDetail',
            'displayCustomerAccount',
            'moduleRoutes',
            'termsAndConditions',
            'actionValidateOrder',
        ];
    }

    /**
     * Wpisy w `ps_module_shop` dla każdego sklepu instalacji, nie tylko tego
     * z kontekstu — instalacja z konsoli stoi w kontekście sklepu domyślnego.
     *
     * @param array<int,int> $shops
     */
    private function installForShops(array $shops): bool
    {
        foreach ($shops as $idShop) {
            Db::getInstance()->insert(
                'module_shop',
                ['id_module' => (int) $this->id, 'id_shop' => (int) $idShop, 'enable_device' => 7],
                false,
                true,
                Db::INSERT_IGNORE
            );
        }

        return true;
    }

    /**
     * Wpisy w `ps_module_group` — bez nich żaden hook frontendowy się nie odpala.
     */
    private function installModuleGroups(): bool
    {
        $groups = Group::getGroups((int) Context::getContext()->language->id, true);

        foreach (Shop::getShops(true, null, true) as $idShop) {
            foreach ($groups as $group) {
                Db::getInstance()->insert(
                    'module_group',
                    [
                        'id_module' => (int) $this->id,
                        'id_shop' => (int) $idShop,
                        'id_group' => (int) $group['id_group'],
                    ],
                    false,
                    true,
                    Db::INSERT_IGNORE
                );
            }
        }

        return true;
    }

    // =========================================================================
    // BAZA DANYCH
    // =========================================================================

    private function installDb(): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql = [];

        // Oświadczenie o odstąpieniu. Jedno na zamówienie — wycofanie częściowe
        // (tylko niektórych pozycji) nie jest w zakresie pierwszej wersji.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_withdrawal` (
            `id_withdrawal` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            `id_order` INT UNSIGNED NOT NULL,
            `id_customer` INT UNSIGNED NOT NULL,
            `reference` VARCHAR(32) NOT NULL DEFAULT '',
            `status` ENUM('submitted','acknowledged','accepted','rejected') NOT NULL DEFAULT 'submitted',
            `reason` TEXT NULL DEFAULT NULL,
            `customer_name` VARCHAR(255) NOT NULL DEFAULT '',
            `customer_email` VARCHAR(255) NOT NULL DEFAULT '',
            `pdf_filename` VARCHAR(255) NULL DEFAULT NULL,
            `id_order_return` INT UNSIGNED NULL DEFAULT NULL,
            `id_order_slip` INT UNSIGNED NULL DEFAULT NULL,
            `reject_reason` TEXT NULL DEFAULT NULL,
            `settlement_status` ENUM('pending','refunded') NOT NULL DEFAULT 'pending',
            `settlement_amount` DECIMAL(20,6) NULL DEFAULT NULL,
            `settlement_currency` VARCHAR(3) NULL DEFAULT NULL,
            `settlement_method` VARCHAR(64) NULL DEFAULT NULL,
            `settlement_document` VARCHAR(64) NULL DEFAULT NULL,
            `settlement_source` VARCHAR(32) NULL DEFAULT NULL,
            `settled_at` DATETIME NULL DEFAULT NULL,
            `channel` VARCHAR(16) NOT NULL DEFAULT 'shop_form',
            `entered_by` VARCHAR(255) NULL DEFAULT NULL,
            `note` TEXT NULL DEFAULT NULL,
            `external_id` VARCHAR(64) NULL DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_withdrawal`),
            UNIQUE KEY `reference` (`reference`),
            UNIQUE KEY `shop_external_id` (`id_shop`, `external_id`),
            KEY `shop_order` (`id_shop`, `id_order`),
            KEY `id_customer` (`id_customer`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        // Zgoda na wczesne wykonanie usługi / dostarczenie treści cyfrowej,
        // zebrana PRZY KONKRETNYM ZAMÓWIENIU (nie przez moduł zgód) — decyzja
        // sklepu: ta zgoda jest zdarzeniem handlowym, nie zgodą marketingową.
        // Tekst oświadczenia jest zapisany razem ze zgodą, bo jego treść może
        // się zmienić w ustawieniach, a dowód musi pokazywać to, co klient
        // naprawdę zaakceptował w chwili zamówienia.
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}quince_withdrawal_consent` (
            `id_consent` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` INT UNSIGNED NOT NULL,
            `id_order` INT UNSIGNED NOT NULL,
            `id_order_detail` INT UNSIGNED NOT NULL,
            `mode` VARCHAR(32) NOT NULL,
            `statement` TEXT NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_consent`),
            UNIQUE KEY `order_detail` (`id_order_detail`),
            KEY `id_order` (`id_order`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        foreach ($sql as $query) {
            if (!$db->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb(): bool
    {
        $db = Db::getInstance();
        $p = _DB_PREFIX_;

        foreach (['quince_withdrawal_consent', 'quince_withdrawal'] as $table) {
            if (!$db->execute("DROP TABLE IF EXISTS `{$p}{$table}`")) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // ZAKŁADKA — natywna, pod Customer Service, siostra "Merchandise Returns".
    // Odstąpienie to proces obsługi zamówień, nie wygląd ani dane produktu,
    // więc NIE wchodzi do panelu Quince (zob. ModuleCatalogue w quince_engine).
    // =========================================================================

    private function installTabs(): bool
    {
        if (Tab::getIdFromClassName('AdminQuinceWithdrawal')) {
            return true;
        }

        $idParent = (int) Tab::getIdFromClassName('AdminParentCustomerThreads');

        $tab = new Tab();
        $tab->class_name = 'AdminQuinceWithdrawal';
        $tab->module = $this->name;
        $tab->id_parent = $idParent > 0 ? $idParent : -1;
        $tab->route_name = 'quince_withdrawal_statements';
        $tab->active = 1;
        $tab->wording = 'Withdrawal';
        $tab->wording_domain = self::DOMAIN_ADMIN;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Withdrawal';
        }

        return (bool) $tab->add();
    }

    private function uninstallTabs(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('AdminQuinceWithdrawal');
        if (!$idTab) {
            return true;
        }

        return (bool) (new Tab($idTab))->delete();
    }

    // =========================================================================
    // POLE DODATKOWE PRODUKTU — wyjątek z prawa odstąpienia
    // =========================================================================

    private function installFields(): bool
    {
        foreach ($this->fieldDefinitions() as $definition) {
            if ($this->registerExtraProperty($definition) === false) {
                return false;
            }
        }

        return true;
    }

    private function removeFields(bool $dropData): void
    {
        foreach ($this->fieldDefinitions() as $definition) {
            $this->unregisterExtraProperty($definition, $dropData);
        }
    }

    /**
     * Admin-only: pole o wyjątku NIE jest treścią dla klienta — to, co klient
     * ma przeczytać, pisze front modułu własnym tekstem prawnym, nie wartością
     * tego pola. `displayFront: false` trzyma je wyłącznie w panelu.
     *
     * @return ExtraPropertyDefinition[]
     */
    private function fieldDefinitions(): array
    {
        return [
            new ExtraPropertyDefinition(
                entityName: 'product',
                propertyName: self::FIELD_MODE,
                type: ExtraPropertyType::STRING,
                scope: ExtraPropertyScope::COMMON,
                size: 32,
                displayFront: false,
                associatedForms: ['product'],
                associatedApis: ['/products', '/products/{productId}'],
                formType: WithdrawalModeChoiceType::class,
                labelWording: 'Right of withdrawal',
                labelDomain: self::DOMAIN_ADMIN,
                descriptionWording: 'How this product is treated under the right of withdrawal. Leave at the default unless the law excludes it.',
                descriptionDomain: self::DOMAIN_ADMIN
            ),
        ];
    }

    // =========================================================================
    // TRASA CZYSTYCH ADRESÓW
    // =========================================================================

    public function hookModuleRoutes(): array
    {
        return [
            'module-quince_withdrawal-request' => [
                'controller' => 'request',
                'rule' => 'withdrawal',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => 'quince_withdrawal', 'controller' => 'request'],
            ],
            'module-quince_withdrawal-mine' => [
                'controller' => 'mine',
                'rule' => 'withdrawals',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => 'quince_withdrawal', 'controller' => 'mine'],
            ],
        ];
    }

    public function hookActionFrontControllerSetMedia(): void
    {
    }

    // =========================================================================
    // KONTO KLIENTA — wyzwalacz formularza albo status istniejącego
    // oświadczenia na stronie szczegółu zamówienia.
    // =========================================================================

    /** @param array{order?:\Order} $params */
    public function hookDisplayOrderDetail(array $params): string
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof \Order) {
            return '';
        }

        $repository = new \Quince\Withdrawal\Repository();
        $statement = $repository->getByOrder((int) $order->id);
        $settings = new \Quince\Withdrawal\Settings\WithdrawalSettings();

        $data = [
            'statement' => null,
            'eligible' => false,
            'requestUrl' => '',
            'termDays' => $settings->termDays((int) $order->id_shop),
        ];

        if ($statement !== null) {
            $data['statement'] = $statement;
        } else {
            $calculator = new \Quince\Withdrawal\Service\DeadlineCalculator($repository, $settings);

            if ($calculator->isEligible($order)) {
                $data['eligible'] = true;
                $data['requestUrl'] = $this->context->link->getModuleLink(
                    'quince_withdrawal',
                    'request',
                    ['id_order' => $order->id]
                );
            }
        }

        $this->context->smarty->assign($data);

        return $this->fetch('module:quince_withdrawal/views/templates/hook/order_detail.tpl');
    }

    // =========================================================================
    // BLOK NA STRONIE ZAMÓWIENIA — tylko gdy dla tego zamówienia istnieje
    // oświadczenie; puste zamówienia nie dostają pustej karty.
    // =========================================================================

    /** @param array{id_order?:int} $params */
    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return '';
        }

        $statement = (new \Quince\Withdrawal\Repository())->getByOrder($idOrder);
        if ($statement === null) {
            return '';
        }

        $twig = $this->get('twig');
        if ($twig === null) {
            return '';
        }

        return $twig->render('@Modules/quince_withdrawal/views/templates/admin/order_block.html.twig', [
            'statement' => $statement,
            'useNativeCreditSlip' => (new \Quince\Withdrawal\Settings\WithdrawalSettings())->useNativeCreditSlip((int) $statement['id_shop']),
        ]);
    }

    // =========================================================================
    // ZGODA NA WCZESNE WYKONANIE — checkbox na kasie, TYLKO gdy koszyk zawiera
    // usługę albo treść cyfrową. Wpięty przez natywny hook `termsAndConditions`,
    // nie własny szablon: dzięki temu dziedziczy TĘ SAMĄ walidację (required,
    // blokadę złożenia zamówienia), co "akceptuję regulamin" — bez pisania
    // własnej, ryzykownej logiki blokującej kasę.
    // =========================================================================

    /** @return \PrestaShop\PrestaShop\Core\Checkout\TermsAndConditions|null */
    public function hookTermsAndConditions(array $params)
    {
        if (!$this->cartHasServiceOrDigital()) {
            return null;
        }

        return (new \PrestaShop\PrestaShop\Core\Checkout\TermsAndConditions())
            ->setIdentifier('quince-withdrawal-consent')
            ->setText($this->consentText());
    }

    private function cartHasServiceOrDigital(): bool
    {
        $cart = $this->context->cart;
        if (!$cart instanceof \Cart) {
            return false;
        }

        foreach ($cart->getProducts() as $product) {
            $mode = Db::getInstance()->getValue(
                'SELECT quince_withdrawal_mode FROM ' . _DB_PREFIX_ . 'product_extra
                 WHERE id_product = ' . (int) $product['id_product']
            );

            if (in_array($mode, ['service', 'digital'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zgoda jest zapisywana PRZY ZAMÓWIENIU (nie przez moduł zgód) — decyzja
     * sklepu: to zdarzenie handlowe, nie zgoda marketingowa.
     */
    public function hookActionValidateOrder(array $params): void
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof \Order) {
            return;
        }

        $repository = new \Quince\Withdrawal\Repository();
        $text = $this->consentText();

        foreach ($repository->orderItems((int) $order->id) as $item) {
            if (!in_array($item['withdrawal_mode'], ['service', 'digital'], true)) {
                continue;
            }

            Db::getInstance()->insert(
                'quince_withdrawal_consent',
                [
                    'id_shop' => (int) $order->id_shop,
                    'id_order' => (int) $order->id,
                    'id_order_detail' => (int) $item['id_order_detail'],
                    'mode' => pSQL($item['withdrawal_mode']),
                    'statement' => pSQL($text, true),
                    'date_add' => date('Y-m-d H:i:s'),
                ],
                false,
                true,
                \Db::INSERT_IGNORE
            );
        }
    }

    // =========================================================================
    // SIDEBAR KONTA — link do listy "Moje odstąpienia", tym samym hookiem co
    // lista życzeń i RODO (zob. komentarz w account-menu.tpl: moduły dokładają
    // tu surowe <a>, motyw sam je ujednolica).
    // =========================================================================

    public function hookDisplayCustomerAccount(): string
    {
        $url = $this->context->link->getModuleLink('quince_withdrawal', 'mine');
        // Ta sama ikona co "Moje pokwitowania - korekty płatności" w tym samym
        // sidebarze (account-menu.tpl) — oświadczenie jest tym samym gatunkiem
        // rzeczy co korekta: dokumentem przypisanym do zamówienia. Pierwsza
        // próba (własna ścieżka w konwencji stroke) nie pasowała do reszty —
        // sidebar rysuje ikony jako wypełnione kształty, nie obrysy.
        $icon = '<svg class="q-account-nav__icon" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
            . '<path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/>'
            . '<path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/>'
            . '</svg>';

        // Pozycje z tego haka dzielą jedno <li> na wszystkie moduły naraz
        // (account-menu.tpl), więc aktywność nie może siedzieć na <li> jak
        // u natywnych wpisów — zaznacza ją własny <a> tego modułu.
        $isActive = $this->context->controller instanceof Quince_WithdrawalMineModuleFrontController;
        $class = $isActive ? ' class="is-active"' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';

        return '<a href="' . $url . '"' . $class . $ariaCurrent . '>' . $icon . $this->trans('Withdrawals', [], 'Modules.Quincewithdrawal.Shop') . '</a>';
    }

    private function consentText(): string
    {
        return $this->trans(
            'I agree that performance of the service, or delivery of the digital content in this order, '
            . 'starts immediately, and I acknowledge that I lose my right of withdrawal once it has been '
            . 'fully performed or delivered.',
            [],
            'Modules.Quincewithdrawal.Shop'
        );
    }
}
