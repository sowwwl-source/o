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
            'copy' => 'O. ne commence pas par un fil infini. Il commence par une terre, donc par une position, un nom et un rythme.',
        ],
        [
            'label' => 'Principe',
            'title' => 'Public quand il faut, privé quand ça compte',
            'copy' => 'Str3m se lit publiquement. L’écriture adressée, l’archive et l’identité de contact passent par une terre liée.',
        ],
        [
            'label' => 'Principe',
            'title' => 'Chaque ferry a un rôle',
            'copy' => 'Signal adresse, Str3m fait découvrir, aZa sédimente, Écho résonne encore en direct. 0wlslw0 explique sans remplacer.',
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
            'copy' => 'Le nom devient le repère visible de la terre. Il vaut mieux court, lisible et durable.',
        ],
        [
            'label' => '02',
            'title' => 'Fixer un secret',
            'copy' => 'Le secret protège la terre. Il sert à la retrouver plus tard et à ouvrir l’édition.',
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
    ];
}

function guide_prompt_seeds(): array
{
    return [
        'Je veux comprendre O. sans créer de compte.',
        'Aide-moi à choisir entre Signal, Str3m et aZa.',
        'Explique-moi comment poser une terre en trois étapes.',
        'Je visite publiquement. Que puis-je déjà lire ?',
    ];
}
