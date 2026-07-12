<?php

declare(strict_types=1);

return [
    'title' => 'Gestionnaire de sauvegardes',
    'subtitle' => 'État des sources et historique des sauvegardes.',
    'read_only' => 'Lecture seule',
    'tables_missing' => 'Les tables du gestionnaire de sauvegardes sont absentes. Exécutez d’abord les migrations de l’application.',
    'states' => ['dirty' => 'Sauvegarde requise', 'clean' => 'À jour'],
    'statuses' => ['running' => 'En cours', 'successful' => 'Réussie', 'failed' => 'Échouée'],
    'pagination' => ['label' => 'Pagination', 'previous' => 'Page précédente', 'next' => 'Page suivante'],
    'sources' => [
        'title' => 'Sources', 'source' => 'Source', 'type' => 'Type', 'state' => 'État',
        'last_change' => 'Dernière modification', 'last_attempt' => 'Dernière tentative', 'last_success' => 'Dernier succès',
        'last_error' => 'Dernière erreur', 'empty' => 'Aucune source de sauvegarde n’a encore été enregistrée.',
    ],
    'runs' => [
        'title' => 'Historique des exécutions', 'started' => 'Démarrage', 'source' => 'Source', 'status' => 'Statut',
        'destination' => 'Destination', 'artifact' => 'Fichier', 'size' => 'Taille', 'completed' => 'Fin',
        'error' => 'Erreur', 'empty' => 'Aucune sauvegarde n’a encore été enregistrée.',
    ],
];
