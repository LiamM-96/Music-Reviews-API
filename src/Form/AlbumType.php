<?php

namespace App\Form;

use App\Entity\Album;
use App\Entity\Genre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlbumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
            ])
            ->add('artist', TextType::class, [
                'label' => 'Artist',
            ])
            ->add('releaseYear', IntegerType::class, [
                'label' => 'Release year',
                'required' => false,
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Cover image',
                'mapped' => false,   // not a field on the entity
                'required' => false,
            ])
            ->add('genre', EntityType::class, [
                'class' => Genre::class,
                'choice_label' => 'name',
                'placeholder' => 'Choose a genre',
                'required' => false,
            ])
        ;
        //   NOTE:
        //   createdAt, updatedAt and createdBy are NOT in the form.
        //   They are set in the controller.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Album::class,
        ]);
    }
}
