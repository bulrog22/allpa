<?php

namespace App\Form;

use App\Entity\LigneCommande;
use App\Entity\Product;
use App\Repository\JourDistribRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;

class LigneCommandeType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => function (Product $product = null) {
                    return $product->getNom() . " - " . $product->getConditionnement() . " kg" . " - " . $product->getPrixInit() . " €";
                },
                'choice_value' => 'id',
                'choices' => $options['products'],
                'label' => false,
                'required' => true,
            ])
            ->add('quantite', IntegerType::class,[
                'required' => true,
                'attr' => array('min' => 1),
                'data' => 1,
            ])


        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => LigneCommande::class,
            'products' => Products::class,
        ]);
    }
}
