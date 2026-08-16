<?php
declare(strict_types=1);

/**
 * Fabrique l'archive à envoyer par FTP.
 *
 *   php scripts/build_release.php
 *
 * Reprend le projet en écartant ce qui n'a rien à faire sur un serveur public
 * (tests, cache, base locale, fichiers de travail), régénère la démo, vérifie
 * que rien de confidentiel ne part avec, et produit un ZIP daté.
 */

$root = realpath(__DIR__ . '/..');
$name = 'budgeat';
$version = date('Y-m-d');
$zipPath = dirname($root) . "/$name-$version.zip";

// Ce qui ne part jamais : secrets, données locales, outillage de développement.
$excludeDirs = ['storage', 'tests', '.git', 'node_modules'];
$excludeFiles = ['config.php', '.DS_Store', 'releves.csv', 'carnet.txt', 'a_mapper.txt'];
$excludeExt = ['sqlite', 'sqlite-wal', 'sqlite-shm', 'log', 'zip'];

echo "Assemblage de la démo…\n";
passthru(sprintf('php %s', escapeshellarg("$root/scripts/build_demo.php")), $code);
if ($code !== 0) {
    fwrite(STDERR, "Échec de la génération de la démo.\n");
    exit(1);
}

echo "\nVérifications avant archivage\n";
$problems = [];

// Une clé Stripe oubliée dans un fichier versionné serait un incident.
foreach (['config.example.php', 'data/collectors.json'] as $file) {
    $content = file_get_contents("$root/$file");
    if (preg_match('/sk_(live|test)_[A-Za-z0-9]{10,}/', (string) $content)) {
        $problems[] = "$file contient une clé Stripe";
    }
}
if (file_exists("$root/config.php")) {
    echo "  · config.php existe en local : il est exclu de l'archive (normal)\n";
}
$stores = json_decode(file_get_contents("$root/data/stores.json"), true);
if (empty($stores['calibrated'])) {
    echo "  · les prix ne sont pas encore calibrés (voir LISEZMOI.txt, section 6)\n";
}

foreach ($problems as $problem) {
    fwrite(STDERR, "  ! $problem\n");
}
if ($problems !== []) {
    fwrite(STDERR, "\nArchive non créée. Corrigez les points ci-dessus.\n");
    exit(1);
}

echo "\nCréation de l'archive…\n";
@unlink($zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Impossible de créer $zipPath\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
$bytes = 0;

foreach ($files as $file) {
    /** @var SplFileInfo $file */
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $segments = explode('/', $relative);

    if (array_intersect($segments, $excludeDirs) !== []) {
        continue;
    }
    if (in_array($file->getFilename(), $excludeFiles, true)) {
        continue;
    }
    if (in_array(strtolower($file->getExtension()), $excludeExt, true)) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relative);
        continue;
    }
    $zip->addFile($file->getPathname(), $relative);
    $count++;
    $bytes += $file->getSize();
}

// storage/ doit exister sur le serveur : on l'ajoute vide, avec sa protection.
$zip->addEmptyDir('storage');
$zip->addFromString('storage/.htaccess', "Require all denied\n");
$zip->addFromString('storage/.gitkeep', '');

// Les tests ne partent pas, mais l'acheteur doit savoir qu'ils existent.
$zip->addFromString('storage/LISEZMOI.txt',
    "Ce dossier doit rester inscriptible (permissions 755, ou 775 si besoin).\n" .
    "Il contient la base de données et le cache. Ne le supprimez pas.\n");

$zip->close();

printf("\nArchive : %s\n", $zipPath);
printf("  %d fichiers · %.1f Mo compressés (%.1f Mo à l'origine)\n",
    $count, filesize($zipPath) / 1048576, $bytes / 1048576);
echo "\nÀ faire ensuite :\n";
echo "  1. décompresser l'archive\n";
echo "  2. envoyer son contenu dans le dossier public de l'hébergement\n";
echo "  3. ouvrir https://votre-domaine/install.php\n";
echo "  4. supprimer install.php\n";
