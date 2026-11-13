<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Withdrawal\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lista wyboru wyjątku z prawa odstąpienia (art. 16 dyrektywy 2011/83/UE).
 *
 * Pięć wartości stałych, nie tabela słownikowa: BEZ konstruktora, tak jak
 * wymaga tego rdzeń. Przy instalacji modułu rdzeń sprawdza definicję pola
 * dodatkowego budując przykładowy formularz TYM typem, zanim kontener usług
 * wie o instalowanym module. Wstrzyknięcie czegokolwiek przez konstruktor
 * kończyłoby się w tym momencie błędem i odrzuceniem całej definicji pola.
 */
final class WithdrawalModeChoiceType extends AbstractType
{
    public const STANDARD = 'standard';
    public const CUSTOM_MADE = 'custom_made';
    public const SEALED_HYGIENE = 'sealed_hygiene';
    public const SERVICE = 'service';
    public const DIGITAL = 'digital';

    /** @return array<string,string> etykieta => wartość, w kolejności wyświetlania */
    public static function choices(): array
    {
        return [
            'Standard: the right applies in full' => self::STANDARD,
            'Made to the customer\'s specification: excluded (Art. 16(c))' => self::CUSTOM_MADE,
            'Sealed for health or hygiene reasons, excluded once unsealed (Art. 16(e))' => self::SEALED_HYGIENE,
            'Service, excluded once fully performed with the customer\'s consent (Art. 16(a))' => self::SERVICE,
            'Digital content not on a tangible medium, excluded once delivered with the customer\'s consent (Art. 16(m))' => self::DIGITAL,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => self::choices(),
            'choice_translation_domain' => 'Modules.Quincewithdrawal.Admin',
            'required' => false,
            'placeholder' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
