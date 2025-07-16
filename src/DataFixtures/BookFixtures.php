<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\BookCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BookFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création des catégories de livres
        $categories = [
            'Classique' => ['color' => 'bg-amber-500', 'icon' => 'book'],
            'Science-Fiction' => ['color' => 'bg-blue-500', 'icon' => 'rocket'],
            'Roman' => ['color' => 'bg-green-500', 'icon' => 'heart'],
            'Essai' => ['color' => 'bg-purple-500', 'icon' => 'academic-cap'],
            'Conte' => ['color' => 'bg-pink-500', 'icon' => 'star'],
            'Thriller' => ['color' => 'bg-red-500', 'icon' => 'lightning-bolt'],
            'Fantasy' => ['color' => 'bg-indigo-500', 'icon' => 'sparkles'],
            'Biographie' => ['color' => 'bg-teal-500', 'icon' => 'user'],
        ];

        $categoryEntities = [];
        $sortOrder = 1;
        foreach ($categories as $name => $config) {
            $category = new BookCategory();
            $category->setName($name);
            $category->setSlug(strtolower(str_replace([' ', '-'], '_', $name)));
            $category->setColor($config['color']);
            $category->setIcon($config['icon']);
            $category->setDescription("Catégorie {$name}");
            $category->setActive(true);
            $category->setSortOrder($sortOrder++);

            $manager->persist($category);
            $categoryEntities[$name] = $category;
        }

        // Création des livres avec des noms de fichiers d'images locales
        $books = [
            // LIVRES DE LA SEMAINE
            [
                'title' => 'L\'Étranger',
                'author' => 'Albert Camus',
                'description' => 'Un chef-d\'œuvre de la littérature française qui explore l\'absurdité de l\'existence.',
                'longDescription' => 'Publié en 1942, "L\'Étranger" est le premier roman d\'Albert Camus et l\'une des œuvres les plus importantes de la littérature du XXe siècle. À travers le personnage de Meursault, Camus explore les thèmes de l\'absurde, de l\'aliénation et de la condition humaine moderne.',
                'coverFilename' => 'etranger-camus.jpg',
                'rating' => 4.5,
                'price' => 8.90,
                'category' => 'Classique',
                'publicationDate' => new \DateTime('1942-05-01'),
                'pages' => 159,
                'isbn' => '978-2070360024',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isWeeklySelection' => true,
                'sortOrder' => 1
            ],
            [
                'title' => 'Le Petit Prince',
                'author' => 'Antoine de Saint-Exupéry',
                'description' => 'Un conte poétique et philosophique intemporel.',
                'longDescription' => 'Le Petit Prince est une œuvre de littérature d\'enfance et d\'adolescence la plus lue et la plus traduite au monde. C\'est un conte poétique et philosophique qui séduit autant les enfants que les adultes.',
                'coverFilename' => 'petit-prince.jpg',
                'rating' => 4.7,
                'price' => 6.90,
                'category' => 'Conte',
                'publicationDate' => new \DateTime('1943-04-06'),
                'pages' => 96,
                'isbn' => '978-2070408504',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isWeeklySelection' => true,
                'sortOrder' => 2
            ],
            [
                'title' => '1984',
                'author' => 'George Orwell',
                'description' => 'Un roman dystopique visionnaire sur la surveillance et le totalitarisme.',
                'longDescription' => '1984 est un roman d\'anticipation de George Orwell, publié en 1949. Cette œuvre décrit une Grande-Bretagne trente ans après une guerre nucléaire entre l\'Est et l\'Ouest censée avoir eu lieu dans les années 1950.',
                'coverFilename' => '1984-orwell.jpg',
                'rating' => 4.6,
                'price' => 9.50,
                'category' => 'Science-Fiction',
                'publicationDate' => new \DateTime('1949-06-08'),
                'pages' => 376,
                'isbn' => '978-2070368228',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isWeeklySelection' => true,
                'sortOrder' => 3
            ],

            // SUGGESTIONS PERSONNALISÉES
            [
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'description' => 'Une épopée de science-fiction dans un univers désertique fascinant.',
                'longDescription' => 'Dune est un roman de science-fiction de Frank Herbert, publié aux États-Unis en 1965. Il s\'agit du premier tome du cycle de Dune. L\'histoire se déroule dans un futur lointain où l\'humanité s\'est répandue à travers la galaxie.',
                'coverFilename' => 'dune-herbert.jpg',
                'rating' => 4.8,
                'price' => 12.50,
                'category' => 'Science-Fiction',
                'publicationDate' => new \DateTime('1965-08-01'),
                'pages' => 688,
                'isbn' => '978-2266320352',
                'publisher' => 'Pocket',
                'language' => 'Français',
                'isSuggestion' => true,
                'sortOrder' => 1
            ],
            [
                'title' => 'Le Seigneur des Anneaux',
                'author' => 'J.R.R. Tolkien',
                'description' => 'L\'épopée fantasy la plus célèbre de tous les temps.',
                'longDescription' => 'Le Seigneur des anneaux est un roman de high fantasy en trois volumes de J. R. R. Tolkien. Œuvre mondialement connue, elle a été traduite en de nombreuses langues et adaptée plusieurs fois au cinéma.',
                'coverFilename' => 'seigneur-anneaux.jpg',
                'rating' => 4.9,
                'price' => 25.90,
                'category' => 'Fantasy',
                'publicationDate' => new \DateTime('1954-07-29'),
                'pages' => 1216,
                'isbn' => '978-2070612888',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isSuggestion' => true,
                'sortOrder' => 2
            ],
            [
                'title' => 'Educated',
                'author' => 'Tara Westover',
                'description' => 'Un mémoire bouleversant sur l\'éducation et l\'émancipation.',
                'longDescription' => 'Tara Westover a grandi dans une famille mormone fondamentaliste dans l\'Idaho. Elle n\'a jamais mis les pieds dans une école et pourtant, à force de détermination, elle finira par décrocher un doctorat à Cambridge.',
                'coverFilename' => 'educated-westover.jpg',
                'rating' => 4.7,
                'price' => 14.50,
                'category' => 'Biographie',
                'publicationDate' => new \DateTime('2018-02-20'),
                'pages' => 432,
                'isbn' => '978-2290169643',
                'publisher' => 'J\'ai lu',
                'language' => 'Français',
                'isSuggestion' => true,
                'sortOrder' => 3
            ],

            // DERNIÈRES SORTIES
            [
                'title' => 'Sapiens',
                'author' => 'Yuval Noah Harari',
                'description' => 'Une brève histoire de l\'humanité qui bouleverse notre vision du monde.',
                'longDescription' => 'Dans ce livre audacieux, Yuval Noah Harari explore comment l\'espèce humaine a réussi à dominer la planète. De la révolution cognitive à la révolution agricole, de l\'unification de l\'humanité à la révolution scientifique.',
                'coverFilename' => 'sapiens-harari.jpg',
                'rating' => 4.6,
                'price' => 15.90,
                'category' => 'Essai',
                'publicationDate' => new \DateTime('2024-03-05'),
                'pages' => 512,
                'isbn' => '978-2226257017',
                'publisher' => 'Albin Michel',
                'language' => 'Français',
                'isNewRelease' => true,
                'sortOrder' => 1
            ],
            [
                'title' => 'Klara et le Soleil',
                'author' => 'Kazuo Ishiguro',
                'description' => 'Un roman touchant sur l\'intelligence artificielle et l\'humanité.',
                'longDescription' => 'Klara est une Amie Artificielle dotée d\'une grande capacité d\'observation. Depuis sa place dans le magasin, elle observe le comportement des clients et espère qu\'un jour quelqu\'un la choisira.',
                'coverFilename' => 'klara-soleil.jpg',
                'rating' => 4.4,
                'price' => 13.90,
                'category' => 'Science-Fiction',
                'publicationDate' => new \DateTime('2024-01-15'),
                'pages' => 303,
                'isbn' => '978-2072956894',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isNewRelease' => true,
                'sortOrder' => 2
            ],
            [
                'title' => 'Project Hail Mary',
                'author' => 'Andy Weir',
                'description' => 'Un thriller scientifique passionnant sur la survie de l\'humanité.',
                'longDescription' => 'Ryland Grace se réveille dans un vaisseau spatial sans aucun souvenir de qui il est ou comment il est arrivé là. Sa mission, qu\'il découvre peu à peu, pourrait sauver l\'humanité.',
                'coverFilename' => 'project-hail-mary.jpg',
                'rating' => 4.8,
                'price' => 16.90,
                'category' => 'Science-Fiction',
                'publicationDate' => new \DateTime('2024-02-10'),
                'pages' => 496,
                'isbn' => '978-2352049999',
                'publisher' => 'Sonatine',
                'language' => 'Français',
                'isNewRelease' => true,
                'sortOrder' => 3
            ],

            // HISTOIRES NEWSLETTER
            [
                'title' => 'L\'Anomalie',
                'author' => 'Hervé Le Tellier',
                'description' => 'Prix Goncourt 2020, un roman vertigineux et captivant.',
                'longDescription' => 'En juin 2021, un événement impossible se produit. En mars, un vol Paris-New York a eu lieu. En juin, le même vol, avec les mêmes passagers à bord, atterrit de nouveau. Même avion, même date, mêmes passagers... mais avec trois mois de décalage.',
                'coverFilename' => 'anomalie-letellier.jpg',
                'rating' => 4.3,
                'price' => 11.90,
                'category' => 'Roman',
                'publicationDate' => new \DateTime('2020-08-20'),
                'pages' => 334,
                'isbn' => '978-2072896897',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isNewsletter' => true,
                'sortOrder' => 1
            ],
            [
                'title' => 'Gone Girl',
                'author' => 'Gillian Flynn',
                'description' => 'Un thriller psychologique glaçant et addictif.',
                'longDescription' => 'Amy disparaît le jour de ses cinq ans de mariage. Tout accuse Nick, son mari. Mais est-il vraiment coupable ? Un thriller psychologique qui tient en haleine jusqu\'à la dernière page.',
                'coverFilename' => 'gone-girl.jpg',
                'rating' => 4.2,
                'price' => 9.90,
                'category' => 'Thriller',
                'publicationDate' => new \DateTime('2012-06-05'),
                'pages' => 538,
                'isbn' => '978-2352049791',
                'publisher' => 'Sonatine',
                'language' => 'Français',
                'isNewsletter' => true,
                'sortOrder' => 2
            ],
            [
                'title' => 'Les Misérables',
                'author' => 'Victor Hugo',
                'description' => 'Le chef-d\'œuvre incontournable de la littérature française.',
                'longDescription' => 'Les Misérables est un roman de Victor Hugo paru en 1862. Il décrit la vie de misérables dans Paris et la France provinciale du XIXe siècle et s\'attache plus particulièrement au destin du bagnard Jean Valjean.',
                'coverFilename' => 'miserables-hugo.jpg',
                'rating' => 4.5,
                'price' => 12.90,
                'category' => 'Classique',
                'publicationDate' => new \DateTime('1862-03-30'),
                'pages' => 1648,
                'isbn' => '978-2070409228',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'isNewsletter' => true,
                'sortOrder' => 3
            ],

            // LIVRES SUPPLÉMENTAIRES POUR ENRICHIR LA BASE
            [
                'title' => 'Harry Potter à l\'école des sorciers',
                'author' => 'J.K. Rowling',
                'description' => 'Le premier tome de la saga magique qui a conquis le monde.',
                'longDescription' => 'Harry Potter, un jeune orphelin, découvre à ses 11 ans qu\'il est un sorcier et entre à Poudlard, une école de magie. Il découvre alors un monde merveilleux mais aussi dangereux.',
                'coverFilename' => 'harry-potter-1.jpg',
                'rating' => 4.8,
                'price' => 8.90,
                'category' => 'Fantasy',
                'publicationDate' => new \DateTime('1997-06-26'),
                'pages' => 320,
                'isbn' => '978-2070584628',
                'publisher' => 'Gallimard Jeunesse',
                'language' => 'Français',
                'available' => true,
                'sortOrder' => 0
            ],
            [
                'title' => 'Steve Jobs',
                'author' => 'Walter Isaacson',
                'description' => 'La biographie autorisée du cofondateur d\'Apple.',
                'longDescription' => 'Basée sur plus de quarante entretiens avec Jobs effectués durant les deux dernières années de sa vie, ainsi que sur des entretiens avec plus de cent personnes de sa famille, ses amis, ses adversaires, ses concurrents et ses collègues.',
                'coverFilename' => 'steve-jobs-bio.jpg',
                'rating' => 4.4,
                'price' => 17.90,
                'category' => 'Biographie',
                'publicationDate' => new \DateTime('2011-10-24'),
                'pages' => 656,
                'isbn' => '978-2709638326',
                'publisher' => 'JC Lattès',
                'language' => 'Français',
                'available' => true,
                'sortOrder' => 0
            ],
            [
                'title' => 'Le Comte de Monte-Cristo',
                'author' => 'Alexandre Dumas',
                'description' => 'Un classique de la vengeance et de la rédemption.',
                'longDescription' => 'Edmond Dantès, un jeune marin, est emprisonné au château d\'If à la suite d\'une dénonciation. Il s\'évade au bout de quatorze ans et, devenu riche, se venge de ceux qui l\'ont trahi sous l\'identité du comte de Monte-Cristo.',
                'coverFilename' => 'monte-cristo.jpg',
                'rating' => 4.6,
                'price' => 11.90,
                'category' => 'Classique',
                'publicationDate' => new \DateTime('1844-08-28'),
                'pages' => 1276,
                'isbn' => '978-2070409167',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'available' => true,
                'sortOrder' => 0
            ],
            [
                'title' => 'Atomic Habits',
                'author' => 'James Clear',
                'description' => 'Un guide pratique pour transformer vos habitudes et votre vie.',
                'longDescription' => 'James Clear nous explique comment de minuscules changements peuvent transformer votre vie. Découvrez comment construire de bonnes habitudes, briser les mauvaises et maîtriser les comportements qui mènent à des résultats remarquables.',
                'coverFilename' => 'atomic-habits.jpg',
                'rating' => 4.7,
                'price' => 18.90,
                'category' => 'Essai',
                'publicationDate' => new \DateTime('2018-10-16'),
                'pages' => 320,
                'isbn' => '978-0735211292',
                'publisher' => 'Avery',
                'language' => 'Français',
                'available' => true,
                'sortOrder' => 0
            ],
            [
                'title' => 'The Girl with the Dragon Tattoo',
                'author' => 'Stieg Larsson',
                'description' => 'Un thriller suédois captivant qui a révolutionné le genre.',
                'longDescription' => 'Mikael Blomkvist, journaliste d\'investigation, s\'associe à Lisbeth Salander, une hackeuse asociale, pour résoudre la disparition d\'une jeune femme de la famille Vanger, disparue depuis quarante ans.',
                'coverFilename' => 'girl-dragon-tattoo.jpg',
                'rating' => 4.4,
                'price' => 13.50,
                'category' => 'Thriller',
                'publicationDate' => new \DateTime('2005-08-01'),
                'pages' => 656,
                'isbn' => '978-2330026554',
                'publisher' => 'Actes Sud',
                'language' => 'Français',
                'available' => true,
                'sortOrder' => 0
            ]
        ];

        foreach ($books as $bookData) {
            $book = new Book();
            $book->setTitle($bookData['title']);
            $book->setAuthor($bookData['author']);
            $book->setDescription($bookData['description']);
            $book->setLongDescription($bookData['longDescription']);
            $book->setCoverFilename($bookData['coverFilename']);
            $book->setRating($bookData['rating']);
            $book->setPrice($bookData['price']);
            $book->setCategory($categoryEntities[$bookData['category']]);
            $book->setPublicationDate($bookData['publicationDate']);
            $book->setPages($bookData['pages']);
            $book->setIsbn($bookData['isbn']);
            $book->setPublisher($bookData['publisher']);
            $book->setLanguage($bookData['language']);
            $book->setAvailable($bookData['available'] ?? true);

            // Configuration des sections
            $book->setIsWeeklySelection($bookData['isWeeklySelection'] ?? false);
            $book->setIsNewsletter($bookData['isNewsletter'] ?? false);
            $book->setIsSuggestion($bookData['isSuggestion'] ?? false);
            $book->setIsNewRelease($bookData['isNewRelease'] ?? false);
            $book->setSortOrder($bookData['sortOrder'] ?? 0);

            $manager->persist($book);
        }

        $manager->flush();
    }
}