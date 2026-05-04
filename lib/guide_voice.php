<?php
declare(strict_types=1);

function guide_voice_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $endpoint = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_ENDPOINT') ?: ''));
    $authHeader = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_AUTH_HEADER') ?: 'Authorization'));
    $authScheme = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_AUTH_SCHEME') ?: 'Bearer'));
    $agentKey = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_KEY') ?: ''));
    $requestMode = strtolower(trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_MODE') ?: 'chat')));
    $inputField = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_INPUT_FIELD') ?: 'message'));
    $extraHeadersRaw = trim((string) (getenv('SOWWWL_0WLSLW0_AGENT_EXTRA_HEADERS_JSON') ?: ''));
    $timeout = (int) (getenv('SOWWWL_0WLSLW0_AGENT_TIMEOUT_SECONDS') ?: 18);
    $timeout = max(5, min($timeout, 45));

    if (!in_array($requestMode, ['chat', 'message'], true)) {
        $requestMode = 'chat';
    }

    $config = [
        'api_path' => '/0wlslw0/voice',
        'chat_url' => trim((string) ((getenv('SOWWWL_0WLSLW0_CHAT_URL') ?: getenv('SOWWWL_0WLSLW0_AGENT_URL')) ?: '')),
        'endpoint' => $endpoint,
        'agent_key' => $agentKey,
        'auth_header' => $authHeader !== '' ? $authHeader : 'Authorization',
        'auth_scheme' => $authScheme !== '' ? $authScheme : 'Bearer',
        'request_mode' => $requestMode,
        'input_field' => $inputField !== '' ? $inputField : 'message',
        'extra_headers' => guide_voice_decode_headers($extraHeadersRaw),
        'timeout' => $timeout,
    ];

    return $config;
}

function guide_voice_upstream_configured(): bool
{
    $config = guide_voice_config();
    return $config['endpoint'] !== '';
}

function guide_voice_mode_label(): string
{
    if (guide_voice_upstream_configured()) {
        return 'guide vocal relaye';
    }

    return 'guide vocal local';
}

function guide_voice_browser_state(?array $authenticatedLand = null): array
{
    $config = guide_voice_config();
    $landSlug = trim((string) ($authenticatedLand['slug'] ?? ''));
    $profile = $authenticatedLand ? land_visual_profile($authenticatedLand) : land_collective_profile('calm');

    return [
        'api_path' => (string) $config['api_path'],
        'csrf_token' => csrf_token(),
        'greeting' => guide_voice_default_greeting($authenticatedLand),
        'upstream_configured' => guide_voice_upstream_configured(),
        'chat_url' => (string) $config['chat_url'],
        'land_slug' => $landSlug,
        'land_program' => (string) ($profile['program'] ?? 'collective'),
        'land_label' => (string) ($profile['label'] ?? 'collectif'),
        'land_lambda' => (int) ($profile['lambda_nm'] ?? 548),
        'land_tone' => (string) ($profile['tone'] ?? 'str3m public'),
        'starter_prompts' => guide_voice_starter_prompts($authenticatedLand),
    ];
}

function guide_voice_starter_prompts(?array $authenticatedLand = null): array
{
    $hasLand = trim((string) ($authenticatedLand['slug'] ?? '')) !== '';

    $prompts = $hasLand
        ? [
            'Rouvre ma terre.',
            'Guide-moi vers Signal.',
            'Explique-moi la différence entre Signal et aZa.',
        ]
        : array_slice(guide_prompt_seeds(), 0, 3);

    return guide_voice_format_suggestions($prompts);
}

function guide_voice_format_suggestions(array $prompts): array
{
    $formatted = [];
    foreach ($prompts as $prompt) {
        $utterance = guide_voice_compact_reply_text((string) $prompt);
        if ($utterance === '') {
            continue;
        }

        $formatted[] = [
            'utterance' => $utterance,
            'label' => $utterance,
        ];
    }

    return array_slice($formatted, 0, 4);
}

function guide_voice_default_greeting(?array $authenticatedLand = null): string
{
    return "Je suis Owl O et serai votre guide pour rejoindre le peuple de l'O.";
}

function guide_voice_detect_language(string $text): string
{
    if ($text === '') {
        return 'fr';
    }

    $markers = [
        'en' => [
            'hello', 'hi', 'please', 'i want', 'take me', 'guide me', 'explain', 'what is',
            'public entry', 'archive', 'what next', 'how do i', 'where should i', 'publicly',
            'my land', 'signal or', 'or aza', 'or signal', 'or str3m', 'or echo',
        ],
        'es' => ['hola', 'quiero', 'explica', 'llevame', 'llévame', 'ayuda', 'publico', 'público', 'archivo', 'tierra'],
        'pt' => ['ola', 'olá', 'quero', 'explica', 'leva-me', 'leva me', 'ajuda', 'publico', 'público', 'terra', 'mensagem'],
        'it' => ['ciao', 'voglio', 'spiega', 'portami', 'aiuto', 'pubblico', 'terra', 'archivio', 'messaggio'],
    ];

    $scores = [
        'fr' => 0,
        'en' => 0,
        'es' => 0,
        'pt' => 0,
        'it' => 0,
    ];

    foreach ($markers as $language => $needles) {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, guide_voice_normalize_text($needle))) {
                $scores[$language]++;
            }
        }
    }

    arsort($scores);
    $language = (string) array_key_first($scores);
    if (($scores[$language] ?? 0) <= 0) {
        return 'fr';
    }

    return $language;
}

function guide_voice_message(string $key, string $language = 'fr'): string
{
    static $messages = [
        'fr' => [
            'not_heard' => 'Je n’ai pas bien entendu. Dis par exemple comprendre le projet, visiter publiquement, ou poser une terre.',
            'goodbye' => 'Je reste au passage si tu veux reprendre plus tard. Tu peux revenir quand tu veux.',
            'greeting' => 'Bonjour. On peut faire très simple : comprendre O., visiter sans compte, ou choisir la bonne porte.',
            'confused' => 'Respire, on va simple. O. fonctionne par terres et par portes. Si tu veux juste regarder sans t’engager, commence par Str3m.',
            'compare' => 'En très court : Str3m fait découvrir publiquement, Signal sert à écrire et recevoir, aZa garde les traces, et Echo relie deux terres directement. Si tu hésites encore, commence par Str3m.',
            'signal_reply' => 'Signal est la boîte située d’une terre. Tu y passes pour écrire, recevoir, et valider une identité légère.',
            'str3m_reply' => 'Str3m te laisse sentir le projet publiquement, sans poser de terre tout de suite. C’est la bonne porte pour regarder avant de t’engager.',
            'aza_reply' => 'aZa est la couche de mémoire et d’archives. Tu peux y lire publiquement ce qui a déjà été déposé, puis déposer à ton tour avec une terre liée.',
            'echo_reply' => 'Echo sert aux résonances directes entre terres. Ce n’est pas un mur public, mais un passage plus adressé.',
            'create_reply' => 'Pour entrer vraiment, il faut poser une terre. Tu choisis un nom, un fuseau, puis tu reçois ton ancrage dans O.',
            'reopen_reply_auth' => 'Ta terre est déjà liée ici. Je peux te renvoyer directement vers ton espace.',
            'reopen_reply_guest' => 'Je ne vois pas de terre liée dans cette session. Repars du noyau pour te reconnecter ou relancer la création.',
            'overview' => 'O. n’est pas un fil social classique. C’est un ensemble de terres et de portes. Je suis là pour clarifier le projet, puis t’emmener vers la bonne page.',
            'unknown' => 'Je peux t’aider à comprendre O., visiter publiquement, poser une terre, ou choisir entre Signal, Str3m, aZa et Echo. Dis-moi simplement ce que tu veux faire.',
            'route_signal' => 'Aller vers Signal',
            'route_str3m' => 'Aller vers Str3m',
            'route_aza' => 'Lire aZa',
            'route_echo' => 'Aller vers Echo',
            'route_create' => 'Poser une terre',
            'route_reopen' => 'Ouvrir ma terre',
            'route_home' => 'Retour au noyau',
        ],
        'en' => [
            'not_heard' => 'I did not catch that clearly. Try saying understand the project, visit publicly, or create a land.',
            'goodbye' => 'I will stay by the threshold if you want to return later.',
            'greeting' => 'Hello. We can keep this simple: understand O., visit without an account, or choose the right door.',
            'confused' => 'Take a breath, we can make this simple. O. works through lands and doors. If you only want to look around, start with Str3m.',
            'compare' => 'Very briefly: Str3m lets you discover publicly, Signal is for writing and receiving, aZa keeps traces, and Echo links two lands directly. If you still hesitate, start with Str3m.',
            'signal_reply' => 'Signal is the situated mailbox of a land. You use it to write, receive, and hold a light contact identity.',
            'str3m_reply' => 'Str3m lets you feel the project publicly before creating a land. It is the right door for looking first.',
            'aza_reply' => 'aZa is the layer of memory and archives. You can read what has already been deposited, then add your own traces once a land is linked.',
            'echo_reply' => 'Echo is for direct resonance between lands. It is not a public wall, but a more addressed passage.',
            'create_reply' => 'To enter fully, you need to create a land. You choose a name, a timezone, then receive your anchor inside O.',
            'reopen_reply_auth' => 'Your land is already linked here. I can send you back to it directly.',
            'reopen_reply_guest' => 'I cannot see a linked land in this session. Return to the core to reconnect or start again.',
            'overview' => 'O. is not a conventional social feed. It is a set of lands and doors. I am here to clarify the place, then guide you to the right page.',
            'unknown' => 'I can help you understand O., visit publicly, create a land, or choose between Signal, Str3m, aZa, and Echo. Just tell me what you want to do.',
            'route_signal' => 'Go to Signal',
            'route_str3m' => 'Go to Str3m',
            'route_aza' => 'Read aZa',
            'route_echo' => 'Go to Echo',
            'route_create' => 'Create a land',
            'route_reopen' => 'Open my land',
            'route_home' => 'Return to core',
        ],
        'es' => [
            'not_heard' => 'No te escuché bien. Puedes decir comprender el proyecto, visitar en público o crear una tierra.',
            'goodbye' => 'Me quedo en el umbral si quieres volver más tarde.',
            'greeting' => 'Hola. Podemos hacerlo simple: comprender O., visitar sin cuenta o elegir la puerta correcta.',
            'confused' => 'Respira, vamos simple. O. funciona por tierras y puertas. Si solo quieres mirar, empieza por Str3m.',
            'compare' => 'Muy breve: Str3m sirve para descubrir en público, Signal para escribir y recibir, aZa guarda rastros y Echo enlaza dos tierras directamente. Si dudas, empieza por Str3m.',
            'signal_reply' => 'Signal es el buzón situado de una tierra. Sirve para escribir, recibir y mantener una identidad ligera de contacto.',
            'str3m_reply' => 'Str3m te deja sentir el proyecto en público antes de crear una tierra. Es la puerta correcta para mirar primero.',
            'aza_reply' => 'aZa es la capa de memoria y archivo. Puedes leer lo ya depositado y luego añadir tus propias trazas cuando una tierra esté vinculada.',
            'echo_reply' => 'Echo sirve para resonancias directas entre tierras. No es un muro público, sino un pasaje más dirigido.',
            'create_reply' => 'Para entrar de verdad, necesitas crear una tierra. Eliges un nombre, una zona horaria y recibes tu anclaje en O.',
            'reopen_reply_auth' => 'Tu tierra ya está vinculada aquí. Puedo llevarte directamente a ella.',
            'reopen_reply_guest' => 'No veo una tierra vinculada en esta sesión. Vuelve al núcleo para reconectar o empezar otra vez.',
            'overview' => 'O. no es un flujo social clásico. Es un conjunto de tierras y puertas. Estoy aquí para aclarar el lugar y llevarte a la página correcta.',
            'unknown' => 'Puedo ayudarte a comprender O., visitar en público, crear una tierra o elegir entre Signal, Str3m, aZa y Echo. Dime qué quieres hacer.',
            'route_signal' => 'Ir a Signal',
            'route_str3m' => 'Ir a Str3m',
            'route_aza' => 'Leer aZa',
            'route_echo' => 'Ir a Echo',
            'route_create' => 'Crear una tierra',
            'route_reopen' => 'Abrir mi tierra',
            'route_home' => 'Volver al núcleo',
        ],
        'pt' => [
            'not_heard' => 'Não ouvi bem. Podes dizer compreender o projeto, visitar em público ou criar uma terra.',
            'goodbye' => 'Fico neste limiar se quiseres voltar mais tarde.',
            'greeting' => 'Olá. Podemos manter isto simples: compreender O., visitar sem conta ou escolher a porta certa.',
            'confused' => 'Respira, vamos simplificar. O. funciona por terras e portas. Se queres apenas observar, começa por Str3m.',
            'compare' => 'Muito brevemente: Str3m deixa-te descobrir em público, Signal serve para escrever e receber, aZa guarda vestígios e Echo liga duas terras diretamente. Se ainda hesitas, começa por Str3m.',
            'signal_reply' => 'Signal é a caixa situada de uma terra. Serve para escrever, receber e manter uma identidade leve de contacto.',
            'str3m_reply' => 'Str3m deixa-te sentir o projeto publicamente antes de criar uma terra. É a porta certa para olhar primeiro.',
            'aza_reply' => 'aZa é a camada de memória e arquivo. Podes ler o que já foi depositado e depois acrescentar os teus próprios vestígios quando uma terra estiver ligada.',
            'echo_reply' => 'Echo serve para ressonâncias diretas entre terras. Não é um muro público, mas uma passagem mais endereçada.',
            'create_reply' => 'Para entrar de verdade, precisas de criar uma terra. Escolhes um nome, um fuso horário e recebes a tua âncora em O.',
            'reopen_reply_auth' => 'A tua terra já está ligada aqui. Posso levar-te de volta diretamente.',
            'reopen_reply_guest' => 'Não vejo uma terra ligada nesta sessão. Volta ao núcleo para te reconectares ou recomeçar.',
            'overview' => 'O. não é uma rede social clássica. É um conjunto de terras e portas. Estou aqui para clarificar o lugar e guiar-te para a página certa.',
            'unknown' => 'Posso ajudar-te a compreender O., visitar publicamente, criar uma terra ou escolher entre Signal, Str3m, aZa e Echo. Diz-me apenas o que queres fazer.',
            'route_signal' => 'Ir para Signal',
            'route_str3m' => 'Ir para Str3m',
            'route_aza' => 'Ler aZa',
            'route_echo' => 'Ir para Echo',
            'route_create' => 'Criar uma terra',
            'route_reopen' => 'Abrir a minha terra',
            'route_home' => 'Voltar ao núcleo',
        ],
        'it' => [
            'not_heard' => 'Non ho capito bene. Puoi dire comprendere il progetto, visitare in pubblico o creare una terra.',
            'goodbye' => 'Resto sulla soglia se vuoi tornare più tardi.',
            'greeting' => 'Ciao. Possiamo tenerla semplice: capire O., visitare senza account o scegliere la porta giusta.',
            'confused' => 'Respira, semplifichiamo. O. funziona tramite terre e porte. Se vuoi solo guardare, inizia da Str3m.',
            'compare' => 'Molto in breve: Str3m fa scoprire in pubblico, Signal serve per scrivere e ricevere, aZa conserva le tracce, ed Echo collega due terre direttamente. Se esiti ancora, inizia da Str3m.',
            'signal_reply' => 'Signal è la casella situata di una terra. Serve per scrivere, ricevere e mantenere un’identità leggera di contatto.',
            'str3m_reply' => 'Str3m ti permette di sentire il progetto pubblicamente prima di creare una terra. È la porta giusta per guardare per prima.',
            'aza_reply' => 'aZa è lo strato della memoria e degli archivi. Puoi leggere ciò che è già stato depositato e poi aggiungere le tue tracce quando una terra è collegata.',
            'echo_reply' => 'Echo serve per risonanze dirette tra terre. Non è un muro pubblico, ma un passaggio più indirizzato.',
            'create_reply' => 'Per entrare davvero, devi creare una terra. Scegli un nome, un fuso orario e ricevi il tuo ancoraggio in O.',
            'reopen_reply_auth' => 'La tua terra è già collegata qui. Posso riportarti lì direttamente.',
            'reopen_reply_guest' => 'Non vedo una terra collegata in questa sessione. Torna al nucleo per ricollegarti o ricominciare.',
            'overview' => 'O. non è un feed sociale classico. È un insieme di terre e porte. Sono qui per chiarire il luogo e guidarti verso la pagina giusta.',
            'unknown' => 'Posso aiutarti a comprendere O., visitare pubblicamente, creare una terra o scegliere tra Signal, Str3m, aZa ed Echo. Dimmi solo cosa vuoi fare.',
            'route_signal' => 'Vai a Signal',
            'route_str3m' => 'Vai a Str3m',
            'route_aza' => 'Leggi aZa',
            'route_echo' => 'Vai a Echo',
            'route_create' => 'Crea una terra',
            'route_reopen' => 'Apri la mia terra',
            'route_home' => 'Torna al nucleo',
        ],
    ];

    if (!isset($messages[$language])) {
        $language = 'fr';
    }

    return (string) ($messages[$language][$key] ?? $messages['fr'][$key] ?? '');
}

function guide_voice_route_label(string $route, string $language = 'fr'): string
{
    return guide_voice_message('route_' . $route, $language);
}

function guide_voice_interaction_state(): array
{
    $state = $_SESSION['guide_voice_state'] ?? [];
    if (!is_array($state)) {
        return [];
    }

    return [
        'last_intent' => trim((string) ($state['last_intent'] ?? '')),
        'last_language' => trim((string) ($state['last_language'] ?? 'fr')) ?: 'fr',
        'last_route_href' => trim((string) ($state['last_route_href'] ?? '')),
    ];
}

function guide_voice_store_interaction_state(array $state): void
{
    $_SESSION['guide_voice_state'] = [
        'last_intent' => trim((string) ($state['last_intent'] ?? '')),
        'last_language' => trim((string) ($state['last_language'] ?? 'fr')) ?: 'fr',
        'last_route_href' => trim((string) ($state['last_route_href'] ?? '')),
    ];
}

function guide_voice_followup_requested(string $text): bool
{
    return guide_voice_contains($text, [
        'et apres', 'et après', 'apres', 'après', 'ensuite', 'puis', 'et ensuite',
        'and after', 'what next', 'then what', 'after that',
        'y despues', 'y después', 'despues', 'después', 'luego',
        'e depois', 'depois', 'depois disso',
        'e poi', 'dopo', 'dopo di che'
    ]);
}

function guide_voice_prompt_text(string $language = 'fr', bool $hasLand = false): string
{
    return match ($language) {
        'en' => $hasLand
            ? 'Tell me simply: do you want to look around, write, archive, or reopen your land?'
            : 'Tell me simply: do you want to look around, write, archive, or create a land?',
        'es' => $hasLand
            ? 'Dímelo simple: ¿quieres mirar, escribir, archivar o reabrir tu tierra?'
            : 'Dímelo simple: ¿quieres mirar, escribir, archivar o crear una tierra?',
        'pt' => $hasLand
            ? 'Diz-me de forma simples: queres olhar, escrever, arquivar ou reabrir a tua terra?'
            : 'Diz-me de forma simples: queres olhar, escrever, arquivar ou criar uma terra?',
        'it' => $hasLand
            ? 'Dimmi in modo semplice: vuoi guardare, scrivere, archiviare o riaprire la tua terra?'
            : 'Dimmi in modo semplice: vuoi guardare, scrivere, archiviare o creare una terra?',
        default => $hasLand
            ? 'Dis-moi simplement : tu veux regarder, écrire, archiver, ou rouvrir ta terre ?'
            : 'Dis-moi simplement : tu veux regarder, écrire, archiver, ou poser une terre ?',
    };
}

function guide_voice_build_prompt_reply(string $reply, string $language, ?array $authenticatedLand = null, ?string $intent = null): array
{
    $hasLand = trim((string) ($authenticatedLand['slug'] ?? '')) !== '';

    return [
        'reply' => trim($reply . ' ' . guide_voice_prompt_text($language, $hasLand)),
        'route' => null,
        'source' => 'local',
        '_intent' => $intent,
        '_language' => $language,
    ];
}

function guide_voice_contextual_followup_reply(string $lastIntent, string $language, ?array $authenticatedLand = null): ?array
{
    $slug = trim((string) ($authenticatedLand['slug'] ?? ''));

    return match ($lastIntent) {
        'signal' => $slug !== ''
            ? guide_voice_build_route_reply(
                match ($language) {
                    'en' => 'If you want to write now, stay with Signal. That is where your land opens a conversation and keeps its address.',
                    'es' => 'Si quieres escribir ahora, quédate con Signal. Ahí tu tierra abre una conversación y conserva su dirección.',
                    'pt' => 'Se queres escrever agora, fica com Signal. É aí que a tua terra abre uma conversa e guarda o endereço.',
                    'it' => 'Se vuoi scrivere adesso, resta su Signal. È lì che la tua terra apre una conversazione e conserva il suo indirizzo.',
                    default => 'Si tu veux écrire maintenant, reste sur Signal. C’est là que ta terre ouvre un fil et garde son adresse.',
                },
                '/signal',
                guide_voice_route_label('signal', $language),
                ''
            )
            : guide_voice_build_prompt_reply(
                match ($language) {
                    'en' => 'Signal really opens once a land is linked.',
                    'es' => 'Signal se abre de verdad cuando una tierra está vinculada.',
                    'pt' => 'Signal abre-se de verdade quando uma terra está ligada.',
                    'it' => 'Signal si apre davvero quando una terra è collegata.',
                    default => 'Signal s’ouvre vraiment quand une terre est liée.',
                },
                $language,
                $authenticatedLand,
                'signal'
            ),
        'str3m', 'public' => guide_voice_build_route_reply(
            match ($language) {
                'en' => 'After public discovery, the next step is usually to create a land if you want to stay and write.',
                'es' => 'Después de la visite publique, el siguiente paso suele ser crear una tierra si quieres quedarte y escribir.',
                'pt' => 'Depois da visita pública, o passo seguinte costuma ser criar uma terra se quiseres ficar e escrever.',
                'it' => 'Dopo la visita pubblica, il passo successivo è di solito creare una terra se vuoi restare e scrivere.',
                default => 'Après la visite publique, l’étape suivante est souvent de poser une terre si tu veux rester et écrire.',
            },
            '/rejoindre.php',
            guide_voice_route_label('create', $language),
            ''
        ),
        'aza' => guide_voice_build_route_reply(
            match ($language) {
                'en' => 'You can read aZa publicly right away. Depositing traces comes once a land is linked.',
                'es' => 'Puedes leer aZa públicamente enseguida. Depositar trazas viene después, con una tierra vinculada.',
                'pt' => 'Podes ler aZa publicamente já. Depositar vestígios vem depois, com uma terra ligada.',
                'it' => 'Puoi leggere aZa pubblicamente subito. Depositare tracce viene dopo, con una terra collegata.',
                default => 'Tu peux lire aZa publiquement tout de suite. Déposer des traces vient ensuite, avec une terre liée.',
            },
            '/aza',
            guide_voice_route_label('aza', $language),
            ''
        ),
        'create' => guide_voice_build_route_reply(
            match ($language) {
                'en' => 'The next move is to choose a land name, read the AzA pages, then seal the land.',
                'es' => 'El siguiente gesto es elegir un nombre de tierra, leer las páginas de AzA y luego sellarla.',
                'pt' => 'O próximo gesto é escolher um nome de terra, ler as páginas de AzA e depois selá-la.',
                'it' => 'Il prossimo gesto è scegliere un nome di terra, leggere le pagine di AzA e poi sigillarla.',
                default => 'Le prochain geste est de choisir un nom de terre, lire les pages d’AzA, puis la sceller.',
            },
            '/rejoindre.php',
            guide_voice_route_label('create', $language),
            ''
        ),
        'overview', 'compare', 'confused', 'unknown', 'greeting' => guide_voice_build_prompt_reply(
            match ($language) {
                'en' => 'We can narrow it down now.',
                'es' => 'Ahora podemos afinar.',
                'pt' => 'Agora podemos afinar.',
                'it' => 'Ora possiamo stringere.',
                default => 'On peut resserrer maintenant.',
            },
            $language,
            $authenticatedLand,
            $lastIntent
        ),
        default => null,
    };
}

function guide_voice_commit_interaction_state(array $result, string $defaultLanguage = 'fr'): void
{
    $state = guide_voice_interaction_state();
    $state['last_intent'] = trim((string) ($result['_intent'] ?? $state['last_intent'] ?? ''));
    $state['last_language'] = trim((string) ($result['_language'] ?? $defaultLanguage)) ?: 'fr';
    $state['last_route_href'] = trim((string) ($result['route']['href'] ?? $state['last_route_href'] ?? ''));

    guide_voice_store_interaction_state($state);
}

function guide_voice_suggestions_for_intent(string $intent, string $language = 'fr', ?array $authenticatedLand = null): array
{
    $hasLand = trim((string) ($authenticatedLand['slug'] ?? '')) !== '';

    $prompts = match ($intent) {
        'signal' => match ($language) {
            'en' => ['Open Signal.', 'How do I validate my contact address?', 'What next?'],
            'es' => ['Abre Signal.', '¿Cómo valido mi dirección de contacto?', '¿Y después?'],
            'pt' => ['Abre o Signal.', 'Como valido o meu endereço de contacto?', 'E depois?'],
            'it' => ['Apri Signal.', 'Come valido il mio indirizzo di contatto?', 'E poi?'],
            default => ['Ouvre Signal.', 'Comment valider mon adresse ?', 'Et après ?'],
        },
        'str3m', 'public' => match ($language) {
            'en' => ['Take me to Str3m.', 'What can I read publicly?', 'How do I create a land?'],
            'es' => ['Llévame a Str3m.', '¿Qué puedo leer públicamente?', '¿Cómo creo una tierra?'],
            'pt' => ['Leva-me ao Str3m.', 'O que posso ler publicamente?', 'Como crio uma terra?'],
            'it' => ['Portami a Str3m.', 'Cosa posso leggere pubblicamente?', 'Come creo una terra?'],
            default => ['Emmène-moi vers Str3m.', 'Que puis-je lire publiquement ?', 'Comment poser une terre ?'],
        },
        'aza' => match ($language) {
            'en' => ['Open aZa.', 'What does aZa keep?', 'What next?'],
            'es' => ['Abre aZa.', '¿Qué guarda aZa?', '¿Y después?'],
            'pt' => ['Abre aZa.', 'O que aZa guarda?', 'E depois?'],
            'it' => ['Apri aZa.', 'Che cosa conserva aZa?', 'E poi?'],
            default => ['Ouvre aZa.', 'Que garde aZa ?', 'Et après ?'],
        },
        'echo' => match ($language) {
            'en' => ['Open Echo.', 'Explain the difference with Signal.', 'What next?'],
            'es' => ['Abre Echo.', 'Explícame la diferencia con Signal.', '¿Y después?'],
            'pt' => ['Abre o Echo.', 'Explica a diferença com Signal.', 'E depois?'],
            'it' => ['Apri Echo.', 'Spiegami la differenza con Signal.', 'E poi?'],
            default => ['Ouvre Echo.', 'Explique-moi la différence avec Signal.', 'Et après ?'],
        },
        'create' => match ($language) {
            'en' => ['Open the land creation path.', 'Explain the steps.', 'Can I look around first?'],
            'es' => ['Abre el parcours de creación.', 'Explícame las etapas.', '¿Puedo mirar primero?'],
            'pt' => ['Abre o percurso de criação.', 'Explica as etapas.', 'Posso olhar primeiro?'],
            'it' => ['Apri il percorso di creazione.', 'Spiegami i passaggi.', 'Posso guardare prima?'],
            default => ['Ouvre le parcours de création.', 'Explique-moi les étapes.', 'Puis-je regarder d’abord ?'],
        },
        'reopen' => $hasLand
            ? match ($language) {
                'en' => ['Open my land.', 'Guide me to Signal.', 'What next?'],
                'es' => ['Abre mi tierra.', 'Guíame hacia Signal.', '¿Y después?'],
                'pt' => ['Abre a minha terra.', 'Guia-me até ao Signal.', 'E depois?'],
                'it' => ['Apri la mia terra.', 'Guidami verso Signal.', 'E poi?'],
                default => ['Ouvre ma terre.', 'Guide-moi vers Signal.', 'Et après ?'],
            }
            : match ($language) {
                'en' => ['Take me back to core.', 'I want to create a land.', 'Explain O.'],
                'es' => ['Llévame al núcleo.', 'Quiero crear una tierra.', 'Explica O.'],
                'pt' => ['Leva-me ao núcleo.', 'Quero criar uma terra.', 'Explica O.'],
                'it' => ['Portami al nucleo.', 'Voglio creare una terra.', 'Spiega O.'],
                default => ['Ramène-moi au noyau.', 'Je veux poser une terre.', 'Explique O.'],
            },
        'confused', 'compare', 'overview', 'unknown', 'greeting' => $hasLand
            ? match ($language) {
                'en' => ['Reopen my land.', 'Guide me to Signal.', 'Explain the difference between Signal and aZa.'],
                'es' => ['Reabre mi tierra.', 'Guíame hacia Signal.', 'Explícame la diferencia entre Signal y aZa.'],
                'pt' => ['Reabre a minha terra.', 'Guia-me até ao Signal.', 'Explica a diferença entre Signal e aZa.'],
                'it' => ['Riapri la mia terra.', 'Guidami verso Signal.', 'Spiegami la differenza tra Signal e aZa.'],
                default => ['Rouvre ma terre.', 'Guide-moi vers Signal.', 'Explique-moi la différence entre Signal et aZa.'],
            }
            : match ($language) {
                'en' => ['Explain O.', 'Take me to Str3m.', 'I want to create a land.'],
                'es' => ['Explícame O.', 'Llévame a Str3m.', 'Quiero crear una tierra.'],
                'pt' => ['Explica O.', 'Leva-me ao Str3m.', 'Quero criar uma terra.'],
                'it' => ['Spiega O.', 'Portami a Str3m.', 'Voglio creare una terra.'],
                default => ['Explique O.', 'Emmène-moi vers Str3m.', 'Je veux poser une terre.'],
            },
        default => [],
    };

    return guide_voice_format_suggestions($prompts);
}

function guide_voice_attach_suggestions(array $result, ?array $authenticatedLand = null): array
{
    $intent = trim((string) ($result['_intent'] ?? 'unknown')) ?: 'unknown';
    $language = trim((string) ($result['_language'] ?? 'fr')) ?: 'fr';
    $result['suggestions'] = guide_voice_suggestions_for_intent($intent, $language, $authenticatedLand);
    return $result;
}

function guide_voice_reply(string $utterance, ?array $authenticatedLand = null): array
{
    $normalizedUtterance = guide_voice_normalize_text($utterance);
    $local = guide_voice_local_reply($normalizedUtterance, $authenticatedLand);
    $remote = guide_voice_upstream_configured()
        ? guide_voice_remote_reply($normalizedUtterance, $authenticatedLand, $local)
        : null;

    $result = is_array($remote) ? $remote : $local;
    $result['reply'] = guide_voice_compact_reply_text((string) ($result['reply'] ?? ''));
    $result['route'] = guide_voice_normalize_route($result['route'] ?? null);
    $result['source'] = trim((string) ($result['source'] ?? 'local')) ?: 'local';
    $result['heard'] = $normalizedUtterance;
    $result['ok'] = true;
    $result = guide_voice_attach_suggestions($result, $authenticatedLand);

    guide_voice_commit_interaction_state($result, guide_voice_detect_language($normalizedUtterance));

    foreach (array_keys($result) as $key) {
        if (is_string($key) && str_starts_with($key, '_')) {
            unset($result[$key]);
        }
    }

    guide_voice_store_turn($normalizedUtterance, $result['reply']);

    return $result;
}

function guide_voice_remote_reply(string $utterance, ?array $authenticatedLand, array $localFallback): ?array
{
    $config = guide_voice_config();
    $endpoint = (string) $config['endpoint'];
    if ($endpoint === '') {
        return null;
    }

    $history = guide_voice_recent_history();
    $messages = [
        [
            'role' => 'system',
            'content' => guide_voice_system_prompt(),
        ],
    ];

    foreach ($history as $turn) {
        $user = trim((string) ($turn['user'] ?? ''));
        $assistant = trim((string) ($turn['assistant'] ?? ''));
        if ($user !== '') {
            $messages[] = ['role' => 'user', 'content' => $user];
        }
        if ($assistant !== '') {
            $messages[] = ['role' => 'assistant', 'content' => $assistant];
        }
    }

    $messages[] = ['role' => 'user', 'content' => $utterance];

    $context = [
        'origin' => site_origin(),
        'authenticated_land_slug' => trim((string) ($authenticatedLand['slug'] ?? '')),
        'route_map' => [
            'home' => '/',
            'create_land' => '/rejoindre.php',
            'signal' => '/signal',
            'str3m' => '/str3m',
            'aza' => '/aza',
            'echo' => '/echo',
            'guide' => '/0wlslw0',
        ],
        'voice_only' => true,
    ];

    if ($config['request_mode'] === 'message') {
        $payload = [
            (string) $config['input_field'] => $utterance,
            'session_id' => session_id(),
            'history' => $history,
            'context' => $context,
            'system' => guide_voice_system_prompt(),
        ];
    } else {
        $payload = [
            'session_id' => session_id(),
            'messages' => $messages,
            'context' => $context,
        ];
    }

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: sowwwl-0wlslw0-voice/1.0',
    ];

    if ($config['agent_key'] !== '') {
        $headers[] = $config['auth_header'] . ': ' . trim($config['auth_scheme'] . ' ' . $config['agent_key']);
    }

    foreach ($config['extra_headers'] as $headerLine) {
        $headers[] = $headerLine;
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body)) {
        return null;
    }

    $contextResource = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => (int) $config['timeout'],
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($endpoint, false, $contextResource);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = guide_voice_http_status($responseHeaders);
    if ($statusCode < 200 || $statusCode >= 300 || !is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    $reply = guide_voice_extract_text($decoded);
    if ($reply === '' && is_string($raw)) {
        $reply = guide_voice_compact_reply_text($raw);
    }

    if ($reply === '') {
        return null;
    }

    $route = guide_voice_extract_route($decoded);
    if ($route === null) {
        $route = guide_voice_infer_route_from_text($reply, $utterance);
    }
    if ($route === null && !empty($localFallback['route'])) {
        $route = $localFallback['route'];
    }

    $result = [
        'reply' => $reply,
        'route' => $route,
        'source' => 'remote',
    ];

    if (!empty($localFallback['_intent'])) {
        $result['_intent'] = (string) $localFallback['_intent'];
    }
    if (!empty($localFallback['_language'])) {
        $result['_language'] = (string) $localFallback['_language'];
    }
    if (!empty($localFallback['suggestions']) && is_array($localFallback['suggestions'])) {
        $result['suggestions'] = $localFallback['suggestions'];
    }

    return $result;
}

function guide_voice_http_status(array $headers): int
{
    $statusLine = (string) ($headers[0] ?? '');
    if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
        return (int) $matches[1];
    }

    return 0;
}

function guide_voice_extract_text(mixed $payload): string
{
    if (is_string($payload)) {
        return guide_voice_compact_reply_text($payload);
    }

    if (!is_array($payload)) {
        return '';
    }

    $priorityKeys = ['output_text', 'answer', 'response', 'reply', 'text', 'message', 'content'];
    foreach ($priorityKeys as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }

        $text = guide_voice_extract_text($payload[$key]);
        if ($text !== '') {
            return $text;
        }
    }

    if (isset($payload['choices']) && is_array($payload['choices'])) {
        foreach ($payload['choices'] as $choice) {
            $text = guide_voice_extract_text($choice);
            if ($text !== '') {
                return $text;
            }
        }
    }

    if (isset($payload['messages']) && is_array($payload['messages'])) {
        foreach ($payload['messages'] as $message) {
            if (is_array($message) && (($message['role'] ?? '') === 'assistant')) {
                $text = guide_voice_extract_text($message['content'] ?? null);
                if ($text !== '') {
                    return $text;
                }
            }
        }
    }

    foreach ($payload as $value) {
        $text = guide_voice_extract_text($value);
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function guide_voice_extract_route(mixed $payload): ?array
{
    if (!is_array($payload)) {
        return null;
    }

    $candidate = $payload['route'] ?? null;
    if (is_array($candidate) && !empty($candidate['href'])) {
        return guide_voice_normalize_route($candidate);
    }

    foreach (['href', 'url', 'cta_url'] as $key) {
        $href = trim((string) ($payload[$key] ?? ''));
        if ($href !== '') {
            return guide_voice_normalize_route([
                'href' => $href,
                'label' => (string) ($payload['label'] ?? $payload['cta'] ?? 'Aller plus loin'),
                'auto_navigate' => false,
            ]);
        }
    }

    foreach ($payload as $value) {
        $route = guide_voice_extract_route($value);
        if ($route !== null) {
            return $route;
        }
    }

    return null;
}

function guide_voice_local_reply(string $utterance, ?array $authenticatedLand = null): array
{
    $text = guide_voice_normalize_text($utterance);
    $slug = trim((string) ($authenticatedLand['slug'] ?? ''));
    $intent = guide_voice_detect_intent($text);
    $language = guide_voice_detect_language($text);
    $state = guide_voice_interaction_state();

    if ($text === '') {
        return [
            'reply' => guide_voice_message('not_heard', $language),
            'route' => null,
            'source' => 'local',
            '_intent' => 'silence',
            '_language' => $language,
        ];
    }

    if (guide_voice_followup_requested($text) && trim((string) ($state['last_intent'] ?? '')) !== '') {
        $contextual = guide_voice_contextual_followup_reply((string) $state['last_intent'], $language, $authenticatedLand);
        if (is_array($contextual)) {
            $contextual['_intent'] = (string) ($state['last_intent'] ?? '');
            $contextual['_language'] = $language;
            return $contextual;
        }
    }

    if ($intent === 'goodbye') {
        return [
            'reply' => guide_voice_message('goodbye', $language),
            'route' => null,
            'source' => 'local',
            '_intent' => $intent,
            '_language' => $language,
        ];
    }

    if ($intent === 'greeting') {
        return guide_voice_build_prompt_reply(guide_voice_message('greeting', $language), $language, $authenticatedLand, $intent);
    }

    if ($intent === 'confused') {
        return guide_voice_build_prompt_reply(guide_voice_message('confused', $language), $language, $authenticatedLand, $intent);
    }

    if ($intent === 'compare') {
        return guide_voice_build_prompt_reply(guide_voice_message('compare', $language), $language, $authenticatedLand, $intent);
    }

    if ($intent === 'signal') {
        $reply = guide_voice_build_route_reply(
            guide_voice_message('signal_reply', $language),
            '/signal',
            guide_voice_route_label('signal', $language),
            $text
        );
        $reply['_intent'] = $intent;
        $reply['_language'] = $language;
        return $reply;
    }

    if ($intent === 'str3m' || $intent === 'public') {
        $reply = guide_voice_build_route_reply(
            guide_voice_message('str3m_reply', $language),
            '/str3m',
            guide_voice_route_label('str3m', $language),
            $text
        );
        $reply['_intent'] = $intent;
        $reply['_language'] = $language;
        return $reply;
    }

    if ($intent === 'aza') {
        $reply = guide_voice_build_route_reply(
            guide_voice_message('aza_reply', $language),
            '/aza',
            guide_voice_route_label('aza', $language),
            $text
        );
        $reply['_intent'] = $intent;
        $reply['_language'] = $language;
        return $reply;
    }

    if ($intent === 'echo') {
        $reply = guide_voice_build_route_reply(
            guide_voice_message('echo_reply', $language),
            '/echo',
            guide_voice_route_label('echo', $language),
            $text
        );
        $reply['_intent'] = $intent;
        $reply['_language'] = $language;
        return $reply;
    }

    if ($intent === 'create') {
        $reply = guide_voice_build_route_reply(
            guide_voice_message('create_reply', $language),
            '/rejoindre.php',
            guide_voice_route_label('create', $language),
            $text
        );
        $reply['_intent'] = $intent;
        $reply['_language'] = $language;
        return $reply;
    }

    if ($intent === 'reopen') {
        if ($slug !== '') {
            $reply = guide_voice_build_route_reply(
                guide_voice_message('reopen_reply_auth', $language),
                '/land.php?u=' . rawurlencode($slug),
                guide_voice_route_label('reopen', $language),
                $text
            );
            $reply['_intent'] = $intent;
            $reply['_language'] = $language;
            return $reply;
        }

        $reply = guide_voice_build_route_reply(
            guide_voice_message('reopen_reply_guest', $language),
            '/',
            guide_voice_route_label('home', $language),
            $text
        );
        $reply['_intent'] = $intent;
        $reply['_language'] = $language;
        return $reply;
    }

    if ($intent === 'overview') {
        return guide_voice_build_prompt_reply(guide_voice_message('overview', $language), $language, $authenticatedLand, $intent);
    }

    return guide_voice_build_prompt_reply(guide_voice_message('unknown', $language), $language, $authenticatedLand, $intent);
}

function guide_voice_detect_intent(string $text): string
{
    if ($text === '') {
        return 'silence';
    }

    if (guide_voice_contains($text, ['merci', 'a bientot', 'à bientot', 'au revoir', 'bye', 'thanks', 'thank you', 'see you', 'adios', 'adiós', 'gracias', 'tchau', 'obrigado', 'obrigada', 'grazie', 'arrivederci'])) {
        return 'goodbye';
    }

    if (guide_voice_contains($text, ['bonjour', 'salut', 'hello', 'hi', 'coucou', 'hola', 'buenos dias', 'buenos días', 'ola', 'olá', 'ciao'])) {
        return 'greeting';
    }

    if (guide_voice_contains($text, ['je suis perdu', 'perdu', 'je comprends rien', 'je comprends pas', 'je ne comprends pas', 'aide moi', 'aide-moi', 'je suis confus', 'i am lost', 'i am confused', 'help me', 'no entiendo', 'estoy perdido', 'ayudame', 'ayúdame', 'estou perdido', 'estou confuso', 'ajuda me', 'ajuda-me', 'sono perso', 'sono confuso', 'aiutami'])) {
        return 'confused';
    }

    if (guide_voice_contains($text, [
        'difference', 'différence', 'choisir entre', 'quel ferry', 'quelle porte',
        'signal ou', 'str3m ou', 'aza ou', 'echo ou',
        'signal or', 'str3m or', 'aza or', 'echo or',
        'or signal', 'or str3m', 'or aza', 'or echo',
        'difference between', 'which door', 'which ferry',
        'cual puerta', 'cuál puerta', 'elegir entre',
        'qual porta', 'qual ferry',
        'scegliere tra', 'quale porta'
    ])) {
        return 'compare';
    }

    if (guide_voice_contains($text, ['signal', 'message', 'messagerie', 'mailbox', 'inbox', 'courrier', 'boite', 'boîte', 'mensaje', 'mensagem', 'messaggio', 'ecrire', 'écrire', 'write', 'send', 'envoyer', 'escribir', 'scrivere'])) {
        return 'signal';
    }

    if (guide_voice_contains($text, ['str3m', 'stream', 'courant public', 'public current', 'corriente publica', 'corriente pública', 'corrente publica', 'corrente pública', 'corrente pubblico'])) {
        return 'str3m';
    }

    if (guide_voice_contains($text, ['visiter', 'public', 'voir', 'observer', 'decouvrir', 'découvrir', 'regarder', 'visit', 'look around', 'discover', 'watch', 'publicly', 'read publicly', 'browse publicly', 'visitar', 'ver', 'mirar', 'descubrir', 'visitar', 'olhar', 'guardare', 'visitare', 'scoprire'])) {
        return 'public';
    }

    if (guide_voice_contains($text, ['aza', 'archive', 'archives', 'memoire', 'mémoire', 'souvenir', 'trace', 'traces', 'memory', 'archivo', 'archivos', 'memoria', 'arquivo', 'archivio'])) {
        return 'aza';
    }

    if (guide_voice_contains($text, ['echo', 'écho', 'direct', 'resonance', 'résonance', 'resonancia', 'ressonancia', 'risonanza'])) {
        return 'echo';
    }

    if (guide_voice_contains($text, ['poser une terre', 'creer une terre', 'créer une terre', 'creation', 'création', 'inscription', 'commencer', 'entrer', 'rejoindre', 'create a land', 'sign up', 'start', 'join', 'crear una tierra', 'crear tierra', 'crear conta', 'criar uma terra', 'criar terra', 'iniziare', 'creare una terra'])) {
        return 'create';
    }

    if (guide_voice_contains($text, ['retrouver ma terre', 'rouvrir ma terre', 'ouvrir ma terre', 'ma terre', 'terre existante', 'revenir', 'my land', 'open my land', 'existing land', 'mi tierra', 'abrir mi tierra', 'minha terra', 'aprire la mia terra'])) {
        return 'reopen';
    }

    if (guide_voice_contains($text, ['comprendre', 'c est quoi', 'c’est quoi', 'que fais tu', 'qui es tu', 'qui es-tu', 'explique', 'projet', 'understand', 'what is', 'who are you', 'explain', 'project', 'comprender', 'que es', 'qué es', 'quien eres', 'quién eres', 'explica', 'compreender', 'o que e', 'o que é', 'chi sei', 'spiega'])) {
        return 'overview';
    }

    return 'unknown';
}

function guide_voice_build_route_reply(string $reply, string $href, string $label, string $utterance): array
{
    return [
        'reply' => $reply,
        'route' => [
            'href' => $href,
            'label' => $label,
            'auto_navigate' => guide_voice_should_auto_navigate($utterance),
        ],
        'source' => 'local',
    ];
}

function guide_voice_should_auto_navigate(string $utterance): bool
{
    return preg_match('/\b(go|take me|guide me|vas-y|ouvre|ouvrir|emmene|emmène|amene|amène|mene|mène|allons|conduis|direction|direct|llevame|llévame|guia me|guíame|leva me|leva-me|portami)\b/u', $utterance) === 1;
}

function guide_voice_contains(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, guide_voice_normalize_text($needle))) {
            return true;
        }
    }

    return false;
}

function guide_voice_normalize_text(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function guide_voice_compact_reply_text(string $value): string
{
    $value = strip_tags($value);
    $value = preg_replace('/[`*_#>]+/', '', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr') && function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > 420) {
        return rtrim(mb_substr($value, 0, 417, 'UTF-8')) . '…';
    }

    if (strlen($value) > 420) {
        return rtrim(substr($value, 0, 417)) . '...';
    }

    return $value;
}

function guide_voice_normalize_route(mixed $route): ?array
{
    if (!is_array($route)) {
        return null;
    }

    $href = trim((string) ($route['href'] ?? ''));
    if ($href === '') {
        return null;
    }

    if (!preg_match('~^(?:/|https?://)~i', $href)) {
        $href = '/' . ltrim($href, '/');
    }

    return [
        'href' => $href,
        'label' => trim((string) ($route['label'] ?? 'Continuer')) ?: 'Continuer',
        'auto_navigate' => !empty($route['auto_navigate']),
    ];
}

function guide_voice_infer_route_from_text(string $reply, string $utterance = ''): ?array
{
    $combined = guide_voice_normalize_text(trim($reply . ' ' . $utterance));
    $intent = guide_voice_detect_intent($combined);
    $language = guide_voice_detect_language($combined);

    return match ($intent) {
        'signal' => ['href' => '/signal', 'label' => guide_voice_route_label('signal', $language), 'auto_navigate' => false],
        'str3m', 'public', 'confused', 'compare' => ['href' => '/str3m', 'label' => guide_voice_route_label('str3m', $language), 'auto_navigate' => false],
        'aza' => ['href' => '/aza', 'label' => guide_voice_route_label('aza', $language), 'auto_navigate' => false],
        'echo' => ['href' => '/echo', 'label' => guide_voice_route_label('echo', $language), 'auto_navigate' => false],
        'create' => ['href' => '/rejoindre.php', 'label' => guide_voice_route_label('create', $language), 'auto_navigate' => false],
        default => null,
    };
}

function guide_voice_recent_history(): array
{
    $history = $_SESSION['guide_voice_history'] ?? [];
    if (!is_array($history)) {
        return [];
    }

    $sanitized = [];
    foreach ($history as $turn) {
        if (!is_array($turn)) {
            continue;
        }

        $user = guide_voice_compact_reply_text((string) ($turn['user'] ?? ''));
        $assistant = guide_voice_compact_reply_text((string) ($turn['assistant'] ?? ''));
        if ($user === '' && $assistant === '') {
            continue;
        }

        $sanitized[] = [
            'user' => $user,
            'assistant' => $assistant,
        ];
    }

    return array_slice($sanitized, -6);
}

function guide_voice_store_turn(string $user, string $assistant): void
{
    $history = guide_voice_recent_history();
    $history[] = [
        'user' => guide_voice_compact_reply_text($user),
        'assistant' => guide_voice_compact_reply_text($assistant),
    ];

    $_SESSION['guide_voice_history'] = array_slice($history, -6);
}

function guide_voice_system_prompt(): string
{
    static $prompt = null;

    if (is_string($prompt)) {
        return $prompt;
    }

    $agentPath = dirname(__DIR__) . '/0wlslw0_AGENT.md';
    $knowledgePath = dirname(__DIR__) . '/0wlslw0_KNOWLEDGE.md';
    $playbookPath = dirname(__DIR__) . '/0wlslw0_VOICE_PLAYBOOK.md';
    $agent = is_readable($agentPath) ? trim((string) file_get_contents($agentPath)) : '';
    $knowledge = is_readable($knowledgePath) ? trim((string) file_get_contents($knowledgePath)) : '';
    $playbook = is_readable($playbookPath) ? trim((string) file_get_contents($playbookPath)) : '';

    $prompt = trim(implode("\n\n", array_filter([
        'You are 0wlslw0, the voice-only guide for O.',
        'Reply in the visitor\'s likely language when it is obvious. Default to French otherwise.',
        'Keep a calm, precise, slightly mystical tone, but stay easy to understand.',
        'Keep answers short enough to be spoken aloud comfortably, ideally under three sentences.',
        'Do not use markdown, bullets, or code formatting in your final reply.',
        'If the visitor intent is clear, orient them toward one primary route only.',
        'Never invent permissions, signup completion, or private access.',
        $agent,
        $knowledge,
        $playbook,
    ])));

    return $prompt;
}

function guide_voice_decode_headers(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $headers = [];
    foreach ($decoded as $name => $value) {
        $headerName = trim((string) $name);
        $headerValue = trim((string) $value);
        if ($headerName === '' || $headerValue === '' || str_contains($headerName, ':')) {
            continue;
        }

        $headers[] = $headerName . ': ' . $headerValue;
    }

    return $headers;
}
