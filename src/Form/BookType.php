<?php

namespace App\Form;

use App\Entity\Book;
use App\Entity\BookCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Titre du livre'
                ]
            ])
            ->add('author', TextType::class, [
                'label' => 'Auteur',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Nom de l\'auteur'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3,
                    'placeholder' => 'Description courte du livre'
                ]
            ])
            ->add('longDescription', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 6,
                    'placeholder' => 'Description détaillée du livre'
                ]
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Image de couverture',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, WebP)',
                        'maxSizeMessage' => 'L\'image ne peut pas dépasser {{ limit }}{{ suffix }}'
                    ])
                ],
                'help' => 'Formats acceptés: JPEG, PNG, WebP. Taille maximum: 2MB',
                'attr' => [
                    'class' => 'form-input',
                    'accept' => 'image/*'
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => BookCategory::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
                'attr' => ['class' => 'form-select']
            ])
            ->add('rating', NumberType::class, [
                'label' => 'Note (0-5)',
                'required' => false,
                'scale' => 1,
                'attr' => [
                    'class' => 'form-input',
                    'min' => 0,
                    'max' => 5,
                    'step' => 0.1,
                    'placeholder' => '4.5'
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'required' => false,
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '15.90'
                ]
            ])
            ->add('publicationDate', DateType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input']
            ])
            ->add('pages', IntegerType::class, [
                'label' => 'Nombre de pages',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '250'
                ]
            ])
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '978-2070360024'
                ]
            ])
            ->add('publisher', TextType::class, [
                'label' => 'Éditeur',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Gallimard'
                ]
            ])
            ->add('language', TextType::class, [
                'label' => 'Langue',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Français'
                ]
            ])
            ->add('available', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
                'attr' => ['class' => 'form-checkbox']
            ])

            // Sections de la sidebar
            ->add('isWeeklySelection', CheckboxType::class, [
                'label' => 'Livre de la semaine',
                'required' => false,
                'help' => 'Afficher dans la section "Livres de la semaine"',
                'attr' => ['class' => 'form-checkbox']
            ])
            ->add('isNewsletter', CheckboxType::class, [
                'label' => 'Newsletter',
                'required' => false,
                'help' => 'Afficher dans la section "Historique Newsletter"',
                'attr' => ['class' => 'form-checkbox']
            ])
            ->add('isSuggestion', CheckboxType::class, [
                'label' => 'Suggestion',
                'required' => false,
                'help' => 'Afficher dans la section "Suggestions personnalisées"',
                'attr' => ['class' => 'form-checkbox']
            ])
            ->add('isNewRelease', CheckboxType::class, [
                'label' => 'Nouvelle sortie',
                'required' => false,
                'help' => 'Afficher dans la section "Dernières sorties"',
                'attr' => ['class' => 'form-checkbox']
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'required' => false,
                'help' => 'Plus le nombre est petit, plus le livre apparaîtra en premier',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '0'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}