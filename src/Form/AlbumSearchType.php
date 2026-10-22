<?php

namespace App\Form;

use App\Entity\Genre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlbumSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', TextType::class, [
                'required' => false,
                'label' => 'Keyword',
                'attr' => [
                    'placeholder' => 'Search by title or artist',
                ],
            ])
            ->add('genre', EntityType::class, [
                'class' => Genre::class,
                'choice_label' => 'name',
                'placeholder' => 'All genres',
                'required' => false,
                'label' => 'Genre',
            ])
            ->add('yearFrom', IntegerType::class, [
                'required' => false,
                'label' => 'From year',
            ])
            ->add('yearTo', IntegerType::class, [
                'required' => false,
                'label' => 'To year',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
