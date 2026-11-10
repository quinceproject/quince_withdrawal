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

use Db;
use Group;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends WithdrawalController
{
    public function __construct(private readonly WithdrawalSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        $idLang = (int) $this->getContext()->language->id;

        return $this->renderPanel(
            '@Modules/quince_withdrawal/views/templates/admin/settings.html.twig',
            [
                'settings' => $this->settings->all(),
                'orderStates' => $this->orderStates($idLang),
                'customerGroups' => $this->customerGroups($idLang),
                'limits' => [
                    'termDaysMin' => WithdrawalSettings::TERM_DAYS_MIN,
                    'termDaysMax' => WithdrawalSettings::TERM_DAYS_MAX,
                ],
            ],
            'quince_withdrawal_settings'
        );
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_withdrawal_settings_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_settings');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', self::DOMAIN));

        return $this->redirectToRoute('quince_withdrawal_settings');
    }

    /** @return array<int,array{id:int,name:string}> */
    private function orderStates(int $idLang): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT osl.id_order_state, osl.name
             FROM ' . _DB_PREFIX_ . 'order_state_lang osl
             WHERE osl.id_lang = ' . $idLang . '
             ORDER BY osl.name ASC'
        );

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id_order_state'], 'name' => (string) $row['name']],
            is_array($rows) ? $rows : []
        );
    }

    /** @return array<int,array{id:int,name:string}> */
    private function customerGroups(int $idLang): array
    {
        return array_map(
            static fn (array $group): array => ['id' => (int) $group['id_group'], 'name' => (string) $group['name']],
            Group::getGroups($idLang, true)
        );
    }
}
