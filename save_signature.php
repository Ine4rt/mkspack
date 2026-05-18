<?php
header('Content-Type: application/json');

// Afficher les erreurs PHP dans la réponse JSON (pour déboguer)
ini_set('display_errors', 0);
error_reporting(E_ALL);

$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée reçue']);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'JSON invalide : ' . json_last_error_msg()]);
    exit;
}

$orderId   = isset($data['orderId'])   ? $data['orderId']   : null;
$signature = isset($data['signature']) ? $data['signature'] : null;
$filename  = isset($data['filename'])  ? $data['filename']  : 'signature_' . $orderId . '_' . time() . '.png';

if (!$signature) {
    echo json_encode(['success' => false, 'message' => 'Pas de données signature']);
    exit;
}

// Créer le dossier signatures s'il n'existe pas
$dir = __DIR__ . '/signatures';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0775, true)) {
        echo json_encode(['success' => false, 'message' => 'Impossible de créer le dossier signatures (permissions ?)']);
        exit;
    }
}

// Vérifier que le dossier est writable
if (!is_writable($dir)) {
    echo json_encode(['success' => false, 'message' => 'Dossier signatures non accessible en écriture. Permissions : ' . decoct(fileperms($dir) & 0777)]);
    exit;
}

// Extraire les données base64
// Supporte data:image/png;base64,... et data:image/jpeg;base64,...
$signature = preg_replace('/^data:image\/\w+;base64,/', '', $signature);
$signature = str_replace(' ', '+', $signature);
$imageData = base64_decode($signature, true);

if ($imageData === false || strlen($imageData) < 10) {
    echo json_encode(['success' => false, 'message' => 'Décodage base64 échoué ou image vide (taille: ' . strlen($imageData) . ')']);
    exit;
}

// Nettoyer le nom de fichier
$filename = basename($filename);
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

$filepath = $dir . '/' . $filename;

$bytes = file_put_contents($filepath, $imageData);
if ($bytes === false) {
    echo json_encode(['success' => false, 'message' => 'file_put_contents a échoué pour : ' . $filepath]);
    exit;
}

echo json_encode([
    'success'  => true,
    'filename' => $filename,
    'path'     => 'signatures/' . $filename,
    'size'     => $bytes
]);
?>
