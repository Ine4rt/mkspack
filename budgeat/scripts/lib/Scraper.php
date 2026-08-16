<?php
declare(strict_types=1);

/**
 * Récupération de pages et extraction de prix.
 *
 * Trois principes, dans l'ordre :
 *   1. on ne demande que ce que robots.txt autorise ;
 *   2. on espace les requêtes et on met en cache ;
 *   3. face à un blocage, on s'arrête — jamais de contournement.
 *
 * Pour l'extraction, on cherche d'abord les données structurées (JSON-LD
 * schema.org, microdata, balises meta) que la plupart des sites marchands
 * publient pour les moteurs de recherche. Elles sont normalisées et bien plus
 * stables que des classes CSS, qui changent à chaque refonte. Les sélecteurs
 * XPath configurés ne servent que de dernier recours.
 */
final class Scraper
{
    private ?string $lastError = null;
    private bool $blocked = false;
    private bool $fromCache = false;
    private float $lastRequestAt = 0.0;

    public function __construct(
        private string $cacheDir,
        private string $contact,
        private float $delaySeconds = 2.0,
        private bool $useCache = true,
        private int $cacheTtlHours = 72,
    ) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function lastError(): string
    {
        return $this->lastError ?? 'erreur inconnue';
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function fromCache(): bool
    {
        return $this->fromCache;
    }

    private function userAgent(): string
    {
        // Un robot qui ne dit pas qui il est se fait bloquer, et le mérite.
        return sprintf('BudgeatPriceBot/1.0 (+mailto:%s) collecte trimestrielle de prix', $this->contact);
    }

    // ------------------------------------------------------------- robots.txt

    /** @var array<string,array<int,string>> hôte => chemins interdits */
    private array $robotsCache = [];

    public function robotsAllows(string $baseUrl, string $path = '/'): bool
    {
        $host = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);

        if (!isset($this->robotsCache[$host])) {
            $body = $this->fetch($host . '/robots.txt', bypassRobots: true);
            $this->robotsCache[$host] = $body === null ? [] : $this->parseRobots($body);
        }

        $target = parse_url($path, PHP_URL_PATH) ?: $path;
        foreach ($this->robotsCache[$host] as $rule) {
            if ($rule !== '' && str_starts_with($target, $rule)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Lecture des Disallow qui s'appliquent à nous : le bloc « User-agent: * »
     * et, s'il existe, un bloc visant explicitement notre robot.
     *
     * @return array<int,string>
     */
    private function parseRobots(string $body): array
    {
        $disallow = [];
        $applies = false;

        foreach (preg_split('/\R/', $body) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');
            if ($line === '') {
                continue;
            }
            [$field, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $value = strtolower($value);
                $applies = $value === '*' || str_contains($value, 'budgeat');
            } elseif ($field === 'disallow' && $applies) {
                $disallow[] = $value;
            }
        }
        return $disallow;
    }

    // ------------------------------------------------------------ téléchargement

    public function get(string $url): ?string
    {
        if (!$this->robotsAllows($url, (string) parse_url($url, PHP_URL_PATH))) {
            $this->lastError = 'interdit par robots.txt';
            return null;
        }
        return $this->fetch($url);
    }

    private function fetch(string $url, bool $bypassRobots = false): ?string
    {
        $this->fromCache = false;
        $this->lastError = null;

        $cacheFile = $this->cacheDir . '/' . sha1($url) . '.html';
        if ($this->useCache && file_exists($cacheFile)
            && filemtime($cacheFile) > time() - $this->cacheTtlHours * 3600) {
            $this->fromCache = true;
            return (string) file_get_contents($cacheFile);
        }

        // On respecte le délai entre deux requêtes réseau.
        $elapsed = microtime(true) - $this->lastRequestAt;
        if ($elapsed < $this->delaySeconds) {
            usleep((int) (($this->delaySeconds - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => $this->userAgent(),
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                'Accept-Language: fr-BE,fr;q=0.9,nl-BE;q=0.8',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $this->lastError = 'connexion impossible : ' . $error;
            return null;
        }
        if ($code === 403 || $code === 429 || $code === 503) {
            $this->blocked = true;
            $this->lastError = "le site refuse la requête (HTTP $code)";
            return null;
        }
        if ($code >= 400) {
            $this->lastError = "page indisponible (HTTP $code)";
            return null;
        }

        $body = (string) $body;
        if ($this->looksLikeChallenge($body)) {
            $this->blocked = true;
            $this->lastError = 'page de vérification anti-robot';
            return null;
        }

        if (!$bypassRobots) {
            file_put_contents($cacheFile, $body);
        }
        return $body;
    }

    /** Détecte une page de vérification plutôt qu'un vrai contenu. */
    private function looksLikeChallenge(string $html): bool
    {
        if (strlen($html) > 80_000) {
            return false;                       // une vraie page produit est volumineuse
        }
        foreach (['cf-browser-verification', 'captcha', 'Just a moment', 'Attention Required',
                  'datadome', 'px-captcha', 'incapsula'] as $needle) {
            if (stripos($html, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------- extraction

    /**
     * Cherche le prix dans la page, du plus fiable au plus fragile.
     * @param array $rules règles propres à l'enseigne (xpath, regex, devise)
     */
    public function extractPrice(string $html, array $rules = []): ?float
    {
        foreach (['jsonLd', 'microdata', 'metaTags'] as $method) {
            $price = $this->$method($html);
            if ($price !== null) {
                return $price;
            }
        }

        foreach ($rules['xpath'] ?? [] as $expression) {
            $price = $this->byXpath($html, $expression);
            if ($price !== null) {
                return $price;
            }
        }
        foreach ($rules['regex'] ?? [] as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $price = $this->toFloat($m[1] ?? $m[0]);
                if ($price !== null) {
                    return $price;
                }
            }
        }
        return null;
    }

    /** Données structurées schema.org : le format le plus stable. */
    public function jsonLd(string $html): ?float
    {
        if (!preg_match_all(
            '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
            $html, $matches
        )) {
            return null;
        }

        foreach ($matches[1] as $block) {
            $data = json_decode(trim($block), true);
            if (!is_array($data)) {
                continue;
            }
            $price = $this->findOfferPrice($data);
            if ($price !== null) {
                return $price;
            }
        }
        return null;
    }

    /** Parcourt un JSON-LD (y compris @graph et tableaux) à la recherche d'une offre. */
    private function findOfferPrice(array $node): ?float
    {
        // Une offre porte un prix, éventuellement à travers priceSpecification.
        foreach (['price', 'lowPrice'] as $key) {
            if (isset($node[$key]) && (is_string($node[$key]) || is_numeric($node[$key]))) {
                $price = $this->toFloat((string) $node[$key]);
                if ($price !== null) {
                    return $price;
                }
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $price = $this->findOfferPrice($value);
                if ($price !== null) {
                    return $price;
                }
            }
        }
        return null;
    }

    public function microdata(string $html): ?float
    {
        if (preg_match('#<[^>]+itemprop=["\']price["\'][^>]*content=["\']([^"\']+)["\']#i', $html, $m)) {
            return $this->toFloat($m[1]);
        }
        if (preg_match('#<[^>]+itemprop=["\']price["\'][^>]*>([^<]+)<#i', $html, $m)) {
            return $this->toFloat($m[1]);
        }
        return null;
    }

    public function metaTags(string $html): ?float
    {
        $patterns = [
            '#<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([^"\']+)["\']#i',
            '#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']product:price:amount["\']#i',
            '#<meta[^>]+name=["\']twitter:data1["\'][^>]+content=["\']([^"\']*\d[^"\']*)["\']#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $price = $this->toFloat($m[1]);
                if ($price !== null) {
                    return $price;
                }
            }
        }
        return null;
    }

    public function byXpath(string $html, string $expression): ?float
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $nodes = (new DOMXPath($dom))->query($expression);
        if ($nodes === false) {
            return null;
        }
        foreach ($nodes as $node) {
            $price = $this->toFloat($node->textContent);
            if ($price !== null) {
                return $price;
            }
        }
        return null;
    }

    /**
     * « 4,99 € », « € 4.99 », « 499 » (centimes) → 4.99
     * Renvoie null si la chaîne ne contient pas un montant crédible.
     */
    public function toFloat(string $raw): ?float
    {
        $raw = trim(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'));
        $raw = str_replace(["\u{00A0}", "\u{202F}", ' '], '', $raw);

        if (!preg_match('/(\d+(?:[.,]\d{1,2})?)/', $raw, $m)) {
            return null;
        }
        $value = (float) str_replace(',', '.', $m[1]);

        // Un prix alimentaire hors de cette fourchette est presque sûrement une
        // erreur de sélecteur (numéro d'article, poids, note client…).
        if ($value <= 0.05 || $value > 400) {
            return null;
        }
        return round($value, 2);
    }
}
