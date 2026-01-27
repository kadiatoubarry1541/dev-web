<?php
/**
 * Script pour télécharger des images de personnes noires depuis Unsplash
 * et remplacer les images de témoignages
 */

// URLs d'images Unsplash de personnes noires (format direct avec IDs spécifiques)
$images = [
    'pic1.jpg' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=200&h=200&fit=crop&crop=face', // Femme noire professionnelle
    'pic2.jpg' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200&h=200&fit=crop&crop=face', // Femme noire souriante  
    'pic3.jpg' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop&crop=face', // Homme noir professionnel
];

// URLs alternatives de personnes noires si les premières ne fonctionnent pas
$alternative_images = [
    'pic1.jpg' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&h=200&fit=crop&crop=face', // Femme noire
    'pic2.jpg' => 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?w=200&h=200&fit=crop&crop=face', // Femme noire
    'pic3.jpg' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=200&h=200&fit=crop&crop=face', // Homme noir
];

$testimonials_dir = __DIR__ . '/images/testimonials/';

// Créer le dossier s'il n'existe pas
if (!is_dir($testimonials_dir)) {
    mkdir($testimonials_dir, 0755, true);
}

foreach ($images as $filename => $url) {
    $filepath = $testimonials_dir . $filename;
    
    // Télécharger l'image
    $image_data = @file_get_contents($url);
    
    if ($image_data === false && isset($alternative_images[$filename])) {
        // Essayer l'URL alternative
        $url = $alternative_images[$filename];
        $image_data = @file_get_contents($url);
    }
    
    if ($image_data !== false) {
        // Sauvegarder l'image
        if (file_put_contents($filepath, $image_data)) {
            echo "✓ Image téléchargée avec succès : $filename\n";
        } else {
            echo "✗ Erreur lors de la sauvegarde : $filename\n";
        }
    } else {
        echo "✗ Erreur lors du téléchargement : $filename\n";
        echo "  URL : $url\n";
    }
}

echo "\nTéléchargement terminé !\n";
?>
