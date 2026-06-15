            <nav class="home-route-orbit" aria-label="Chaînons publics du seuil">
                <?php foreach ($homeRouteNodes as $routeNode): ?>
                    <a class="home-route-node home-route-node--<?= h((string) $routeNode['kicker']) ?>" href="<?= h((string) $routeNode['href']) ?>">
                        <span class="home-route-node__index"><?= h((string) $routeNode['index']) ?></span>
                        <span class="home-route-node__body">
                            <span class="summary-label"><?= h((string) $routeNode['kicker']) ?></span>
                            <strong><?= h((string) $routeNode['title']) ?></strong>
                            <span><?= h((string) $routeNode['copy']) ?></span>
                        </span>
                        <span class="home-route-node__signal"><?= h((string) $routeNode['signal']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
