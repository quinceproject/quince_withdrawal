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

/**
 * "Moje odstąpienia" — lista oświadczeń klienta przez wszystkie zamówienia.
 * Ten sam wzorzec co natywna lista "Zwroty produktów" (order-follow.tpl).
 */
class Quince_WithdrawalMineModuleFrontController extends ModuleFrontController
{
    public $auth = true;

    /**
     * Arkusz konta klienta ładujemy wprost, a nie przez `theme.yml`: dobór
     * stylów w `theme.yml` idzie po `php_self`, a nadpisanie go na kontrolerze
     * modułu psuje kanoniczny adres strony (silnik przekierowuje na adres
     * zbudowany z `php_self`, którego `Link::getPageLink()` nie umie
     * rozwiązać dla kontrolera modułu).
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->registerStylesheet(
            'quince-account',
            'themes/quince/assets/css/components/account.css',
            ['media' => 'all', 'priority' => 40]
        );
    }

    public function initContent()
    {
        parent::initContent();

        $repository = new Repository();
        $statements = $repository->listByCustomer((int) $this->context->customer->id);

        $rows = array_map(function (array $statement): array {
            return [
                'reference' => (string) $statement['reference'],
                'order_reference' => (string) $statement['order_reference'],
                'status' => (string) $statement['status'],
                'date_add' => (string) $statement['date_add'],
                'detailsUrl' => $this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    ['id_order' => $statement['id_order']]
                ),
            ];
        }, $statements);

        $this->context->smarty->assign(['withdrawals' => $rows]);
        $this->setTemplate('module:quince_withdrawal/views/templates/front/mine.tpl');
    }
}
