<?php

namespace App\Form;

use App\Entity\JourDistrib;
use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class JourDistribType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('closed', CheckboxType::class, [
            'label'    => 'Fermer la vente',
            'required' => false,
        ]);
        if ($options['edit']){
            $builder
                ->add('products', EntityType::class, [
                    'class' => Product::class,
                    'choice_label' => function (Product $product = null) {
                        return $product->getNom() . " - " . $product->getConditionnement() . "kg";
                    },
                    'choice_value' => 'id',
                    'multiple' => true,
                    'expanded' => true,
                ]);
        }
        else {
            $builder
                ->add('products', EntityType::class, [
                    'class' => Product::class,
                    'choice_label' => function (Product $product = null) {
                        return $product->getNom() . " - " . $product->getConditionnement() . "kg";
                    },
                    'choice_value' => 'id',
                    'multiple' => true,
                    'expanded' => true,
                    'choice_attr' => function($val, $key, $index) {
                        return array('checked' => true);
                    },
                ]);
        }
        $builder
            ->add('date', DateType::class, [
                'label' => 'Date limite de commande',
                'widget' => 'single_text',
                // 'attr' => ['class' => 'ui-datepicker'],
                // 'format' => 'dd/MM/yyyy',
                // 'html5' => false,
                // 'model_timezone' => 'Europe/Paris',
            ])
            ->add('datelivraison', DateType::class, [
                'label' => 'Date de livraison',
                'widget' => 'single_text',
                'required' => false,
                // 'attr' => ['class' => 'ui-datepicker'],
                // 'format' => 'dd/MM/yyyy',
                // 'html5' => false,
                // 'model_timezone' => 'Europe/Paris',
            ])
            ->add('total', NumberType::class,[
                'label' => 'Poids total de la commande (en kg) (0 pas de limite)'
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => JourDistrib::class,
            'edit' => false,
        ]);
    }
}
