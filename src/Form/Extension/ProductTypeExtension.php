<?php

declare(strict_types=1);

namespace Altmenorg\SyliusSharedStockPlugin\Form\Extension;

use Altmenorg\SyliusSharedStockPlugin\Admin\PendingSharedStockChanges;
use Altmenorg\SyliusSharedStockPlugin\Admin\SharedStockChange;
use Altmenorg\SyliusSharedStockPlugin\Model\SharedStockProductInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Shared stock" block of the admin product form: on/off, base unit, stock on hand.
 *
 * The stock on hand is not mapped: the form never writes it to the entity. What the admin asks
 * for is turned into a SharedStockChange (switch on, switch off, or a correction from the value
 * shown when the form was opened to the value typed), applied after the save by one checked
 * UPDATE. A sale made while the form was open is therefore kept, not overwritten.
 */
final class ProductTypeExtension extends AbstractTypeExtension
{
    private const GROUPS = ['sylius', 'Default'];

    public function __construct(
        private readonly PendingSharedStockChanges $pending,
        private readonly TranslatorInterface $translator,
        private readonly bool $allowNegative = false,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $product = $event->getData();
            if (!$product instanceof SharedStockProductInterface) {
                return;
            }
            // a simple product has a single variant: nothing to share, unless it is already shared
            if ($product instanceof ProductInterface && $product->isSimple() && !$product->isSharedStockEnabled()) {
                return;
            }
            $enabled = $product->isSharedStockEnabled();
            $event->getForm()
                ->add('sharedStockEnabled', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'data' => $enabled,
                    'label' => 'altmenorg_sylius_shared_stock.form.product.enabled',
                    'help' => 'altmenorg_sylius_shared_stock.form.product.enabled_help',
                ])
                ->add('sharedStockUnit', TextType::class, [
                    'required' => false,
                    'label' => 'altmenorg_sylius_shared_stock.form.product.unit',
                    'help' => 'altmenorg_sylius_shared_stock.form.product.unit_help',
                    'constraints' => [new Length(max: 16, groups: self::GROUPS)],
                ])
                ->add('sharedStockOnHand', IntegerType::class, [
                    'mapped' => false,
                    'required' => false,
                    'data' => $product->getSharedStockOnHand(),
                    'label' => 'altmenorg_sylius_shared_stock.form.product.on_hand',
                    'help' => 'altmenorg_sylius_shared_stock.form.product.on_hand_help',
                ])
                // what the form showed when it was opened ("on:<stock>" or "off"); empty if the block was not rendered
                ->add('sharedStockOriginal', HiddenType::class, [
                    'mapped' => false,
                    'data' => $enabled ? 'on:' . $product->getSharedStockOnHand() : 'off',
                ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $product = $form->getData();
            if (!$product instanceof SharedStockProductInterface || !$form->has('sharedStockOriginal')) {
                return;
            }
            $this->pending->forget($product);
            $original = $form->get('sharedStockOriginal')->getData();
            if (!is_string($original) || ($original !== 'off' && !preg_match('/^on:-?\d+$/', $original))) {
                return; // block not rendered or tampered with: leave the stock alone
            }
            $wasEnabled = $original !== 'off';
            $from = $wasEnabled ? (int) substr($original, 3) : null;
            $enable = (bool) $form->get('sharedStockEnabled')->getData();
            $to = $form->get('sharedStockOnHand')->getData();

            if (!$enable) {
                if ($wasEnabled && $product->getSharedStockOnHold() > 0) {
                    $form->get('sharedStockEnabled')->addError(new FormError($this->translator->trans(
                        'altmenorg_sylius_shared_stock.on_hold_cannot_disable', ['%on_hold%' => trim($product->getSharedStockOnHold() . ' ' . $product->getSharedStockUnit())], 'validators')));

                    return;
                }
                if ($wasEnabled) {
                    $this->pending->set($product, SharedStockChange::disable());
                }

                return;
            }
            if ($to === null) {
                $form->get('sharedStockOnHand')->addError(new FormError(
                    $this->translator->trans('altmenorg_sylius_shared_stock.on_hand_required', [], 'validators')));

                return;
            }
            if ($to < 0 && !$this->allowNegative) {
                $form->get('sharedStockOnHand')->addError(new FormError(
                    $this->translator->trans('altmenorg_sylius_shared_stock.on_hand_negative', [], 'validators')));

                return;
            }
            if (!$wasEnabled) {
                $this->pending->set($product, SharedStockChange::enable((int) $to));
            } elseif ((int) $to !== $from) {
                $this->pending->set($product, SharedStockChange::correct((int) $from, (int) $to));
            }
        });
    }
}
