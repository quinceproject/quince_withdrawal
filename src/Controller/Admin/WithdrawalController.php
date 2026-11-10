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

use Language;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wspólna podstawa ekranów modułu.
 *
 * Rozszerza kontroler bazowy PrestaShopa wprost, nie kontroler silnika Quince:
 * ten moduł żyje jako natywna zakładka pod Customer Service, nie w panelu
 * Quince, więc własny szablon rozszerza natywny layout panelu
 * (`@PrestaShop/Admin/layout.html.twig`), który sam rysuje sidebar z `ps_tab`.
 */
abstract class WithdrawalController extends FrameworkBundleAdminController
{
    protected const DOMAIN = 'Modules.Quincewithdrawal.Admin';

    /**
     * @return array<int,array{label:string,route:string}>
     */
    protected function tabs(): array
    {
        return [
            ['label' => 'Statements', 'route' => 'quince_withdrawal_statements'],
            ['label' => 'Settings', 'route' => 'quince_withdrawal_settings'],
        ];
    }

    /**
     * @param array<string,mixed> $parameters
     */
    protected function renderPanel(string $template, array $parameters, string $activeRoute): Response
    {
        $tabs = array_map(
            fn (array $tab): array => ['label' => $this->trans($tab['label'], self::DOMAIN)] + $tab,
            $this->tabs()
        );

        return $this->render($template, $parameters + [
            'quinceTabs' => $tabs,
            'quinceRoute' => $activeRoute,
        ]);
    }

    /**
     * @return array<int,array{id_lang:int,name:string,iso_code:string,default:bool}>
     */
    protected function languages(): array
    {
        $out = [];

        foreach (Language::getLanguages(true) as $lang) {
            $out[] = [
                'id_lang' => (int) $lang['id_lang'],
                'name' => (string) $lang['name'],
                'iso_code' => (string) $lang['iso_code'],
                'default' => (int) $lang['id_lang'] === (int) \Configuration::get('PS_LANG_DEFAULT'),
            ];
        }

        return $out;
    }
}
