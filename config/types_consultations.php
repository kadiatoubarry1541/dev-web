<?php
/**
 * Configuration des types de consultations par service
 * 
 * Ce fichier définit les différents types de consultations possibles
 * pour chaque service médical. Ces types apparaîtront dans les menus
 * déroulants lors de la création d'ordonnances.
 */

/**
 * Types de consultations par service
 * 
 * Structure: [
 *     'Nom du Service' => [
 *         'Type de consultation 1',
 *         'Type de consultation 2',
 *         ...
 *     ]
 * ]
 */
$TYPES_CONSULTATIONS_PAR_SERVICE = [
    'Maternité' => [
        'Consultation prénatale',
        'Échographie obstétricale',
        'Suivi de grossesse',
        'Consultation post-natale',
        'Suivi post-accouchement',
        'Consultation gynécologique',
        'Échographie de datation',
        'Échographie morphologique',
        'Consultation de suivi mensuel',
        'Consultation d\'urgence obstétricale'
    ],
    
    'Médecine générale' => [
        'Consultation générale',
        'Consultation de routine',
        'Consultation de suivi',
        'Consultation d\'urgence',
        'Consultation préventive',
        'Consultation pour certificat médical',
        'Consultation de vaccination',
        'Consultation de contrôle',
        'Consultation pour douleur',
        'Consultation pour fièvre'
    ],
    
    'Chirurgie' => [
        'Consultation pré-opératoire',
        'Consultation post-opératoire',
        'Consultation de suivi chirurgical',
        'Consultation d\'évaluation',
        'Consultation de contrôle post-opératoire',
        'Consultation pour intervention',
        'Consultation d\'urgence chirurgicale',
        'Consultation de révision'
    ],
    
    'Ophtalmologie' => [
        'Consultation ophtalmologique',
        'Examen de la vue',
        'Consultation pour troubles visuels',
        'Consultation post-opératoire',
        'Consultation de suivi',
        'Consultation pour glaucome',
        'Consultation pour cataracte',
        'Consultation pour rétinopathie'
    ],
    
    'Cardiologie' => [
        'Consultation cardiologique',
        'Électrocardiogramme (ECG)',
        'Échographie cardiaque',
        'Consultation de suivi cardiaque',
        'Consultation pour hypertension',
        'Consultation post-infarctus',
        'Consultation pour troubles du rythme',
        'Consultation préventive cardiaque'
    ],
    
    'Pédiatrie' => [
        'Consultation pédiatrique',
        'Consultation de routine enfant',
        'Consultation de vaccination enfant',
        'Consultation de croissance',
        'Consultation pour fièvre enfant',
        'Consultation d\'urgence pédiatrique',
        'Consultation de suivi nouveau-né',
        'Consultation de développement'
    ],
    
    'Dermatologie' => [
        'Consultation dermatologique',
        'Consultation pour acné',
        'Consultation pour eczéma',
        'Consultation pour psoriasis',
        'Consultation pour verrues',
        'Consultation pour allergies cutanées',
        'Consultation de suivi dermatologique',
        'Consultation pour dépigmentation'
    ],
    
    'ORL (Oto-Rhino-Laryngologie)' => [
        'Consultation ORL',
        'Consultation pour troubles auditifs',
        'Consultation pour sinusite',
        'Consultation pour otite',
        'Consultation pour angine',
        'Consultation pour troubles de la voix',
        'Consultation post-opératoire ORL',
        'Consultation de suivi ORL'
    ],
    
    'Neurologie' => [
        'Consultation neurologique',
        'Consultation pour maux de tête',
        'Consultation pour épilepsie',
        'Consultation pour troubles de la mémoire',
        'Consultation pour accident vasculaire cérébral',
        'Consultation de suivi neurologique',
        'Consultation pour sclérose en plaques',
        'Consultation pour Parkinson'
    ],
    
    'Orthopédie' => [
        'Consultation orthopédique',
        'Consultation pour fracture',
        'Consultation pour entorse',
        'Consultation pour douleurs articulaires',
        'Consultation post-opératoire orthopédique',
        'Consultation pour scoliose',
        'Consultation de rééducation',
        'Consultation de suivi orthopédique'
    ],
    
    'Urologie' => [
        'Consultation urologique',
        'Consultation pour troubles urinaires',
        'Consultation pour calculs rénaux',
        'Consultation pour prostatite',
        'Consultation de suivi urologique',
        'Consultation post-opératoire urologique',
        'Consultation pour incontinence',
        'Consultation pour infections urinaires'
    ],
    
    'Gynécologie' => [
        'Consultation gynécologique',
        'Consultation de routine gynécologique',
        'Consultation pour troubles menstruels',
        'Consultation pour infections gynécologiques',
        'Consultation de suivi gynécologique',
        'Consultation pour contraception',
        'Consultation pour ménopause',
        'Consultation préventive gynécologique'
    ],
    
    'Psychiatrie' => [
        'Consultation psychiatrique',
        'Consultation pour dépression',
        'Consultation pour anxiété',
        'Consultation pour troubles du sommeil',
        'Consultation de suivi psychiatrique',
        'Consultation pour troubles de l\'humeur',
        'Consultation pour stress',
        'Consultation d\'urgence psychiatrique'
    ],
    
    'Radiologie' => [
        'Consultation radiologique',
        'Radiographie',
        'Échographie',
        'Scanner',
        'IRM',
        'Consultation de suivi radiologique',
        'Consultation pour interprétation d\'images',
        'Consultation pré-opératoire radiologique'
    ]
];

/**
 * Obtenir les types de consultations pour un service donné
 * 
 * @param string $nom_service Le nom du service
 * @return array Liste des types de consultations pour ce service
 */
function getTypesConsultationsParService($nom_service) {
    global $TYPES_CONSULTATIONS_PAR_SERVICE;
    
    // Retourner les types pour le service spécifié, ou un tableau vide
    return $TYPES_CONSULTATIONS_PAR_SERVICE[$nom_service] ?? [];
}

/**
 * Obtenir tous les services avec leurs types de consultations
 * 
 * @return array Tableau associatif service => types de consultations
 */
function getAllTypesConsultations() {
    global $TYPES_CONSULTATIONS_PAR_SERVICE;
    return $TYPES_CONSULTATIONS_PAR_SERVICE;
}

/**
 * Vérifier si un type de consultation existe pour un service
 * 
 * @param string $nom_service Le nom du service
 * @param string $type_consultation Le type de consultation
 * @return bool True si le type existe pour ce service
 */
function typeConsultationExiste($nom_service, $type_consultation) {
    $types = getTypesConsultationsParService($nom_service);
    return in_array($type_consultation, $types);
}

?>
