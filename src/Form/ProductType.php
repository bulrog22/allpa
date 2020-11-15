<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom',TextType::class, [
                'label' => 'product.form.nom', 
            ])
            ->add('conditionnement',TextType::class, [
                'label' => 'product.form.conditionnement', 
            ])
            ->add('unit',ChoiceType::class, [
                'choices'  => [
                    'kg' => 'kg',
                    'L' => 'L',
                    'boite' => 'boite'
                ],
                'multiple'=>false,
                'expanded'=>false,
                'label' => 'product.form.unit', 
            ])
            ->add('prixInit',TextType::class, [
                'label' => 'product.form.prixInit', 
            ])
            ->add('prixFinal',TextType::class, [
                'label' => 'product.form.prixFinal', 
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
