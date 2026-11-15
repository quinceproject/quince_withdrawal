<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\DeadlineCalculator;
use Quince\Withdrawal\Service\StatementService;
use Quince\Withdrawal\Settings\WithdrawalSettings;

/**
 * Złożenie oświadczenia o odstąpieniu. Formularz stoi w karcie na samej
 * stronie zamówienia (`hookDisplayOrderDetail`), ten kontroler tylko
 * przyjmuje POST i wraca na tę samą stronę. Status w karcie sam zmienia się
 * z formularza na potwierdzenie, tak samo jak przy natywnym "Zwrot towaru".
 */
class Quince_WithdrawalRequestModuleFrontController extends ModuleFrontController
{
    public $auth = true;

    public function init()
    {
        parent::init();

        $idOrder = (int) Tools::getValue('id_order');
        $order = new Order($idOrder);

        if (!Validate::isLoadedObject($order) || (int) $order->id_customer !== (int) $this->context->customer->id) {
            Tools::redirect($this->context->link->getPageLink('history'));
        }

        // Oświadczenie jest prawnie wiążące, więc przyjmujemy je tylko z formularza
        // tego sklepu: metoda POST i token strony. Bez tego link na obcej stronie
        // składałby oświadczenie w imieniu zalogowanego klienta.
        $zFormularza = Tools::isSubmit('submitWithdrawal')
            && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && hash_equals(Tools::getToken(false), (string) Tools::getValue('token'));

        if (Tools::isSubmit('submitWithdrawal') && !$zFormularza) {
            $this->errors[] = $this->trans('We could not accept this form. Please reload the page and try again.', [], 'Modules.Quincewithdrawal.Shop');
        }

        if ($zFormularza) {
            $repository = new Repository();
            $settings = new WithdrawalSettings();

            if ($repository->getByOrder((int) $order->id) === null) {
                $calculator = new DeadlineCalculator($repository, $settings);

                if ($calculator->isEligible($order)) {
                    $service = new StatementService($repository, $settings);
                    $service->submit($order, trim((string) Tools::getValue('reason')));
                    $this->success[] = $this->trans('Your withdrawal request has been submitted.', [], 'Modules.Quincewithdrawal.Shop');
                } else {
                    $this->errors[] = $this->trans('The right of withdrawal no longer applies to this order.', [], 'Modules.Quincewithdrawal.Shop');
                }
            }
        }

        $this->redirectWithNotifications(
            $this->context->link->getPageLink('order-detail', null, null, ['id_order' => $order->id])
        );
    }
}
