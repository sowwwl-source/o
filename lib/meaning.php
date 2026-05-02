<?php
declare(strict_types=1);

function guide_paths(): array
{
    return [
        [
            'label' => 'Porte',
            'key' => 'home',
            'title' => 'Comprendre O.',
            'copy' => 'Voir le noyau, la logique des terres et la manière dont les ferries se répondent.',
            'href' => '/',
            'cta' => 'Revenir au noyau',
            'public' => true,
        ],
        [
            'label' => 'Ferry 01',
            'key' => 'signal',
            'title' => 'Signal',
            'copy' => 'Ouvrir une boîte liée à sa terre, écrire à une autre adresse du réseau et valider une identité de notification.',
            'href' => '/signal',
            'cta' => 'Ouvrir Signal',
            'public' => true,
        ],
        [
            'label' => 'Ferry 02',
            'key' => 'str3m',
            'title' => 'Str3m',
            'copy' => 'Explorer le courant public, les traces du jour et les terres qui résonnent déjà.',
            'href' => '/str3m',
            'cta' => 'Entrer dans Str3m',
            'public' => true,
        ],
        [
            'label' => 'Ferry 03',
            'key' => 'aza',
            'title' => 'aZa',
            'copy' => 'Déposer une mémoire légère, lire publiquement, puis passer en édition quand une terre est liée.',
            'href' => '/aza',
            'cta' => 'Ouvrir aZa',
            'public' => true,
        ],
        [
            'label' => 'Ferry 04',
            'key' => 'echo',
            'title' => 'Écho',
            'copy' => 'Parler directement à une autre terre. Cette porte demande une terre active.',
            'href' => '/echo',
            'cta' => 'Ouvrir Écho',
            'public' => false,
        ],
    ];
}

function guide_path(string $key): ?array
{
    foreach (guide_paths() as $path) {
        if (($path['key'] ?? '') === $key) {
            return $path;
        }
    }

    return null;
}

function guide_principles(): array
{
    return [
        [
            'label' => 'Principe',
            'title' => 'Un lieu avant un flux',
            'copy' => 'O. ne commence pas par un fil infini. Il commence par une terre, donc par une position, un nom et un rythme propre.',
        ],
        [
            'label' => 'Principe',
            'title' => 'Public quand il faut, privé quand ça compte',
            'copy' => 'Str3m se lit publiquement. L’écriture adressée, l’archive et l’identité de contact passent par une terre liée et assumée.',
        ],
        [
            'label' => 'Principe',
            'title' => 'Chaque ferry a un rôle',
            'copy' => 'Signal adresse, Str3m fait découvrir, aZa sédimente, Écho relie directement. 0wlslw0 éclaire le passage sans prendre la place du lieu.',
        ],
    ];
}

function guide_threshold_modes(): array
{
    return [
        [
            'label' => 'Seuil 01',
            'title' => 'Sentir avant de choisir',
            'copy' => 'Le visiteur peut d’abord écouter l’atmosphère du projet, regarder le courant public et comprendre les rôles avant d’entrer plus loin.',
        ],
        [
            'label' => 'Seuil 02',
            'title' => 'Nommer l’intention',
            'copy' => '0wlslw0 cherche une intention simple : comprendre, visiter, écrire, archiver, retrouver une terre. Le reste devient nettement plus fluide.',
        ],
        [
            'label' => 'Seuil 03',
            'title' => 'Passer sans rupture',
            'copy' => 'Quand le désir devient clair, le guide n’ajoute pas de théâtre inutile : il ouvre la bonne porte et s’efface doucement.',
        ],
    ];
}

function guide_language_doors(): array
{
    return [
        [
            'label' => 'fr',
            'title' => 'français',
            'copy' => 'La langue native du lieu. Les nuances du projet y sont les plus fines et les plus exactes.',
            'sample' => '« explique O. et mène-moi vers Signal »',
        ],
        [
            'label' => 'en',
            'title' => 'english',
            'copy' => 'Pour entrer depuis l’extérieur sans perdre le fil : comprendre le lieu, puis choisir une porte nette.',
            'sample' => '“explain O. and take me to Str3m”',
        ],
        [
            'label' => 'es',
            'title' => 'español',
            'copy' => 'Pour approcher le projet avec une voix plus souple, plus orale, puis glisser vers la bonne traversée.',
            'sample' => '« explíca O. y llévame a Signal »',
        ],
        [
            'label' => 'pt',
            'title' => 'português',
            'copy' => 'Pour garder la sensation du passage tout en demandant une orientation concrète et directe.',
            'sample' => '“explica O. e leva-me ao Signal”',
        ],
        [
            'label' => 'it',
            'title' => 'italiano',
            'copy' => 'Pour converser avec le seuil sans casser sa tonalité, puis être dirigé vers un usage précis.',
            'sample' => '“spiega O. e portami verso aZa”',
        ],
    ];
}

function guide_glossary(): array
{
    return [
        [
            'term' => 'Terre',
            'meaning' => 'Le point d’ancrage personnel. Une terre garde un nom, un fuseau, une signature et l’accès à l’édition.',
        ],
        [
            'term' => 'Ferry',
            'meaning' => 'Une porte fonctionnelle. Chaque ferry traite un geste précis du projet au lieu d’un usage vague.',
        ],
        [
            'term' => 'Signal',
            'meaning' => 'La boîte aux lettres d’une terre : adresse virtuelle, validation d’identité et conversation située.',
        ],
        [
            'term' => 'Str3m',
            'meaning' => 'Le courant public et l’archipel des terres visibles.',
        ],
        [
            'term' => 'aZa',
            'meaning' => 'La mémoire légère. On y dépose des archives et on y lit des strates.',
        ],
        [
            'term' => 'Écho',
            'meaning' => 'La liaison directe entre deux terres.',
        ],
        [
            'term' => '0wlslw0',
            'meaning' => 'Le guide d’entrée. Il prépare, clarifie et oriente avant la création.',
        ],
    ];
}

function guide_creation_steps(): array
{
    return [
        [
            'label' => '01',
            'title' => 'Choisir un nom',
            'copy' => 'Le nom devient le repère visible de la terre. Il vaut mieux court, lisible et capable de durer sans effort.',
        ],
        [
            'label' => '02',
            'title' => 'Fixer un secret',
            'copy' => 'Le secret protège la terre. Il sert à la retrouver plus tard et à rouvrir l’édition sans bruit.',
        ],
        [
            'label' => '03',
            'title' => 'Entrer par le bon ferry',
            'copy' => 'Signal pour écrire à une autre terre, Str3m pour explorer, aZa pour sédimenter, Écho pour une liaison directe.',
        ],
    ];
}

function guide_faq_items(): array
{
    return [
        [
            'question' => 'Est-ce que je dois créer un compte pour comprendre le projet ?',
            'answer' => 'Non. 0wlslw0 sert justement à faire visiter O. sans forcer l’entrée. Str3m et certaines terres restent lisibles publiquement.',
        ],
        [
            'question' => 'Qu’est-ce qu’une terre ?',
            'answer' => 'Une terre est un point d’ancrage personnel. Elle garde un nom, un fuseau, une signature visuelle et l’accès à tes ferries.',
        ],
        [
            'question' => 'À quoi sert aZa ici ?',
            'answer' => 'aZa est la mémoire légère. On peut y lire publiquement ce qui est partagé, puis déposer des archives quand la terre est liée.',
        ],
        [
            'question' => 'À quoi sert 0wlslw0 si un formulaire existe déjà ?',
            'answer' => 'Le formulaire crée une terre. 0wlslw0 prépare le visiteur : il explique le projet, clarifie les choix et réduit la friction avant l’inscription.',
        ],
        [
            'question' => 'Puis-je parler dans une autre langue que le français ?',
            'answer' => 'Oui. Le lieu pense en français, mais 0wlslw0 peut déjà reconnaître plusieurs approches et répondre plus simplement en anglais, espagnol, portugais ou italien quand l’intention est claire.',
        ],
    ];
}

function guide_prompt_seeds(): array
{
    return [
        'Je veux comprendre O. sans créer de compte.',
        'Aide-moi à choisir entre Signal, Str3m et aZa.',
        'Explique-moi comment poser une terre en trois étapes.',
        'Je visite publiquement. Que puis-je déjà lire ?',
        'Explain O. and guide me to the public entry.',
        'Explíca el proyecto y llévame hacia Signal.',
    ];
}
