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

use Quince\Withdrawal\Repository;
use Quince\Withdrawal\Service\DeadlineCalculator;
use Quince\Withdrawal\Service\Exception\InvalidTransitionException;
use Quince\Withdrawal\Service\Exception\StatementNotFoundException;
use Quince\Withdrawal\Service\PdfGenerator;
use Quince\Withdrawal\Service\StatementWorkflow;
use Quince\Withdrawal\Settings\WithdrawalSettings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Szczegół jednego oświadczenia: termin, produkty, przebieg (łańcuch
 * zamówienie → oświadczenie → zwrot → zwrot środków) i akcje.
 */
class StatementController extends WithdrawalController
{
    public function __construct(
        private readonly Repository $repository,
        private readonly WithdrawalSettings $settings
    ) {
    }

    public function indexAction(int $id): Response
    {
        $idShop = $this->getContextShopId();
        $statement = $this->repository->get($id, $idShop);

        if ($statement === null) {
            $this->addFlash('error', $this->trans('That statement does not exist.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_statements');
        }

        $idOrder = (int) $statement['id_order'];
        $idOrderShop = (int) $statement['id_shop'];
        $calculator = new DeadlineCalculator($this->repository, $this->settings);
        $isGoods = $calculator->isGoods($idOrder);

        $startDate = $calculator->startDate($idOrder, $idOrderShop);
        $deadline = $startDate !== null ? $calculator->deadline($startDate, $idOrderShop) : null;

        return $this->renderPanel(
            '@Modules/quince_withdrawal/views/templates/admin/statement.html.twig',
            [
                'statement' => $statement,
                'items' => $this->repository->orderItems($idOrder),
                'isGoods' => $isGoods,
                'startDate' => $startDate,
                'deadline' => $deadline,
                'useNativeCreditSlip' => $this->settings->useNativeCreditSlip($idOrderShop),
            ],
            'quince_withdrawal_statements'
        );
    }

    public function confirmAction(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_withdrawal_statement_confirm', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
        }

        $idShop = $this->getContextShopId();

        try {
            (new StatementWorkflow($this->repository, $this->settings))->confirm($id, $idShop);
            $this->addFlash('success', $this->trans('Statement confirmed.', self::DOMAIN));
        } catch (StatementNotFoundException|InvalidTransitionException $e) {
            // Cicho wracamy na szczegół: to te same strażniki co przy pierwszym
            // kliknięciu — drugie żądanie (podwójny klik, cofnięcie w przeglądarce)
            // ma po prostu nic nie zmienić, a nie pokazywać błąd.
        }

        return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
    }

    public function acceptAction(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_withdrawal_statement_accept', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
        }

        $idShop = $this->getContextShopId();

        try {
            (new StatementWorkflow($this->repository, $this->settings))->accept($id, $idShop);

            $message = $this->settings->useNativeCreditSlip($idShop)
                ? 'Marked as accepted. Issue the credit slip for this order in Credit Slips as usual.'
                : 'Marked as accepted. Process the refund for this order through your accounting system.';
            $this->addFlash('success', $this->trans($message, self::DOMAIN));
        } catch (StatementNotFoundException|InvalidTransitionException $e) {
        }

        return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
    }

    public function rejectAction(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_withdrawal_statement_reject', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
        }

        $idShop = $this->getContextShopId();
        $reason = (string) $request->request->get('reason', '');

        try {
            (new StatementWorkflow($this->repository, $this->settings))->reject($id, $idShop, $reason);
            $this->addFlash('success', $this->trans('Statement rejected.', self::DOMAIN));
        } catch (StatementNotFoundException $e) {
        } catch (InvalidTransitionException $e) {
            if (trim($reason) === '') {
                $this->addFlash('error', $this->trans('A reason for rejection is required.', self::DOMAIN));
            }
        }

        return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
    }

    public function pdfAction(int $id): Response
    {
        $idShop = $this->getContextShopId();
        $statement = $this->repository->get($id, $idShop);
        $filename = $statement['pdf_filename'] ?? null;

        if ($statement === null || !$filename) {
            $this->addFlash('error', $this->trans('That statement does not exist.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_statements');
        }

        $path = PdfGenerator::directory() . $filename;

        if (!is_file($path)) {
            $this->addFlash('error', $this->trans('That statement does not exist.', self::DOMAIN));

            return $this->redirectToRoute('quince_withdrawal_statement', ['id' => $id]);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, (string) $filename);

        return $response;
    }
}
