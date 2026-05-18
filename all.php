<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Order Management</title>
<link rel="stylesheet" href="styles.css">
    <style>
#missingSummaryContainer {
    margin-top: 20px;
    font-family: Arial, sans-serif;
}

#missingSummaryContainer h3 {
    margin-bottom: 10px;
    font-size: 18px;
    color: #333;
}

#missingSummary {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* ── Récap manquants ───────────────────────────────────────────────── */
.missing-card {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-left: 4px solid #ef4444;
    border-radius: 10px;
    padding: 10px 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    flex-wrap: wrap;
}
.missing-card-label {
    font-weight: 700;
    font-size: 0.95rem;
    color: #111827;
    min-width: 80px;
}
.missing-card-ref {
    font-size: 0.85rem;
    color: #6b7280;
    font-family: monospace;
    background: #f3f4f6;
    padding: 2px 7px;
    border-radius: 5px;
}
.missing-card-sizes {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.missing-size-chip {
    background: #ef4444;
    color: #fff;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
}
.missing-badge {
    background-color: #e0e0e0;
    color: #000;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 15px;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: inline-block;
}




	#clubButtons {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
}

#searchOrderBar {
    padding: 10px 18px;
    font-size: 15px;
    border: 2px solid #ccc;
    border-radius: 25px;
    outline: none;
    transition: all 0.3s ease;
    width: 180px;
    background-color: #f9f9f9;
    color: #333;
    font-family: Arial, sans-serif;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* MOBILE: Repositionner les barres de recherche */
@media (max-width: 768px) {
    /* Container avec barre "Recherche" et bouton "Scan" côte à côte */
    #searchContainer {
        position: static !important;
        display: flex !important;
        gap: 10px;
        margin: 15px 10px;
        padding: 0;
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    #searchBar {
        width: 140px;
        font-family: Arial, sans-serif;
        font-size: 14px;
        padding: 10px 15px;
    }
    
    /* Bouton scan - stylisé comme les onglets, plus grand */
    #scanButton {
        display: inline-block !important;
        padding: 10px 20px;
        font-size: 25px;
        font-weight: 600;
        color: #fff;
        background-color: #007BFF;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        font-family: Arial, sans-serif;
        white-space: nowrap;
    }
    
    #scanButton:hover {
        background-color: #0056b3;
    }
    
    #scanButton:active {
        background-color: #007BFF;
        transform: scale(0.95);
    }
    
    /* Barre "N° Pack" petite, à gauche avec les filtres */
    #searchOrderBar {
        width: 80px;
        font-family: Arial, sans-serif;
        font-size: 12px;
        padding: 7px 10px;
        margin-right: 5px;
    }
    
    /* Mettre searchOrderBar dans le même container que les filtres */
    .filter-bar .search-section {
        display: inline-block;
        margin-right: 0;
    }
}

#searchOrderBar::placeholder {
    color: #888;
    font-style: italic;
}

#searchOrderBar:hover {
    border-color: #007BFF;
    background-color: #eef6ff;
}

#searchOrderBar:focus {
    border-color: #007BFF;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.4);
    background-color: #fff;
}


#checkboxFilters {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 15px;
    font-family: Arial, sans-serif;
}

/* MOBILE: Centrer les filtres checkbox */
@media (max-width: 768px) {
    #checkboxFilters {
        justify-content: center;
    }
}

/* Label stylisé comme un bouton */
.filter-label {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

/* Masquer l’input réel */
.filter-label input[type="checkbox"] {
    display: none;
}

/* Le span stylisé pour ressembler à un bouton */
.filter-label span {
    padding: 6px 15px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    background-color: #f0f0f0;
    border-radius: 25px;
    transition: all 0.2s ease;
}

/* Hover */
.filter-label span:hover {
    background-color: #d0e7ff;
}

/* Quand l’input est coché, changer la couleur du span */
.filter-label input[type="checkbox"]:checked + span {
    background-color: #007BFF;
    color: #fff;
}

.club-btn {
    width: 120px;
    height: 120px;
    object-fit: contain;
    border-radius: 50%;
    border: 3px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f9f9f9;
    padding: 8px;
}

/* MOBILE: Réduire taille des logos clubs */
@media (max-width: 768px) {
    .club-btn {
        width: 80px;
        height: 80px;
    }
}

/* Effet hover */
.club-btn:hover {
    transform: scale(1.1);
    border-color: #007BFF;
    background-color: #eef6ff;
}

/* État actif (le club sélectionné) */
.club-btn.active {
    transform: scale(1.2);
    border-color: #007BFF;
    box-shadow: 0 0 15px rgba(0, 123, 255, 0.6);
    background-color: #e8f2ff;
}


.delete {
    color: red; /* Couleur rouge pour la croix */
    font-size: 24px; /* Taille plus grande de la croix */
    font-weight: bold; /* Rendre la croix plus épaisse */
    cursor: pointer; /* Pointeur pour signaler que c'est cliquable */
    display: inline-block; /* Pour que le span se comporte comme un bloc en ligne */
    padding: 0; /* Retirer tout le padding */
}

.delete:hover {
    color: darkred; /* Couleur plus foncée au survol */
}
.edit {
    font-size: 18px; /* Taille de l'icône */
    background: none;  /* Retire le fond du bouton */
    border: none;      /* Retire la bordure du bouton */
    cursor: pointer;   /* Change le curseur au survol */
    color: blue;       /* Couleur du crayon */
    padding: 0;        /* Retire tout espacement autour du contenu */
    outline: none;     /* Retire l'effet de focus (le rectangle gris) */
}

.edit:hover {
    color: darkblue; /* Couleur au survol */
}

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
		
		        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            align-items: center;
            padding: 10px 15px;
            font-size: 16px;
            color: white;
            background-color: #6c757d;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
        .back-button::before {
            content: "←";
            margin-right: 8px;
            font-size: 18px;
        }
		

        th {
            background-color: #f4f4f4;
        }

    input[type="date"].selected {
        background-color: transparent; /* Enlever la couleur de fond de l'input pour ne pas la masquer */
    }
    input[type="date"] {
        width: 70 px;  /* Ajuster la largeur selon vos besoins */
        padding: 5px;
        text-align: center;
		
    }
        .missing {
            background-color: #ffcccc; /* Rouge pour les articles manquants */
        }

        .completed {
            background-color: #ccffcc; /* Vert pour les commandes terminées */
        }

        /* Ligne grise pour email interne — prioritaire sur .completed */
        tr.grey-row td,
        tr.grey-row,
        tr.grey-row.completed,
        tr.grey-row.completed td {
            background-color: #e0e0e0 !important;
            color: #888 !important;
            opacity: 0.75;
        }
        tr.grey-row td input,
        tr.grey-row td a,
        tr.grey-row.completed td input,
        tr.grey-row.completed td a {
            color: #888 !important;
        }

        .club-logo {
            width: 50px;
            height: 50px;
            cursor: pointer;
        }

        .club-filter {
            margin-bottom: 10px;
        }
.selected {
    background-color: #007bff; /* Bleu */
    color: white;
}

td.selected {
    background-color: blue; /* Fond bleu */
    color: white; /* Texte blanc */
}

        button.delete {
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }

        button.delete:hover {
            background-color: #cc0000;
        }

        button.completed {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }

        button.completed:hover {
            background-color: #218838;
        }
		        .club-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }
		
#searchContainer {
    position: absolute; /* position fixe par rapport à la page */
    top: 10px;          /* distance du haut */
    right: 20px;        /* distance de la droite */
    display: flex;
    gap: 10px;           /* espace entre la barre et le bouton */
    align-items: center;
    z-index: 1000;       /* pour être au-dessus du reste du contenu */
}

/* Barre de recherche stylisée comme #searchOrderBar */
#searchBar {
    padding: 10px 18px;
    font-size: 15px;
    border: 2px solid #ccc;
    border-radius: 25px;
    outline: none;
    transition: all 0.3s ease;
    background-color: #f9f9f9;
    color: #333;
    font-family: Arial, sans-serif;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

#searchBar::placeholder {
    color: #888;
    font-style: italic;
}

#searchBar:hover {
    border-color: #007BFF;
    background-color: #eef6ff;
}

#searchBar:focus {
    border-color: #007BFF;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.4);
    background-color: #fff;
}

/* Bouton identique aux filter-btn */
#exportButton {
    padding: 6px 15px;
    font-size: 16px;
    font-weight: 500;
    color: #FFF;
    background-color: #007BFF;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.2s ease;
}

#exportButton:hover {
    background-color: #d0e7ff; /* bleu clair hover identique aux checkboxes */
}

#exportButton:active {
    background-color: #007BFF; /* bleu actif identique aux checkboxes cochée */
    color: #fff;
}

/* Bouton scan - caché par défaut sur desktop */
#scanButton {
    display: none;
}

	
	
#filterContainer {
    text-align: center;
    margin: 10px 0;
    margin-top: 50px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    font-family: Arial, sans-serif;
}

.filter-btn {
    padding: 6px 15px;
    font-size: 18px;
    font-weight: 500;
    color: #333;
    background-color: #f0f0f0; /* gris clair identique */
    border: none;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.2s ease;
}

/* Hover : bleu clair comme les checkbox */
.filter-btn:hover {
    background-color: #d0e7ff;
    color: #333; /* couleur texte inchangée */
}

/* Actif : bleu identique à la checkbox cochée */
.filter-btn.active {
    background-color: #007BFF;
    color: #fff;
}


.scan-yellow {
  background-color: yellow !important;
}


.reprise-orange {
    background-color: orange;
}

/* Bouton signature dans colonne repris */
.btn-signature {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0 3px;
    vertical-align: middle;
    line-height: 1;
    opacity: 0.85;
    transition: transform 0.15s, opacity 0.15s;
}
.btn-signature:hover {
    opacity: 1;
    transform: scale(1.25);
}

/* Modal popup signature */
#signaturePopup {
    display: none;
    position: fixed;
    z-index: 99999;
    inset: 0;
    background: rgba(0,0,0,0.75);
    align-items: center;
    justify-content: center;
}
#signaturePopup.open {
    display: flex;
}
#signaturePopupInner {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    max-width: 90vw;
    max-height: 90vh;
    box-shadow: 0 8px 40px rgba(0,0,0,0.35);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
}
#signaturePopupInner h3 {
    margin: 0;
    font-size: 1rem;
    color: #334155;
    text-align: center;
    max-width: 320px;
    word-break: break-word;
}
#signaturePopupImg {
    max-width: min(80vw, 500px);
    max-height: 55vh;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    object-fit: contain;
    background: #f8fafc;
}
#signaturePopupClose {
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 28px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
}
#signaturePopupClose:hover { background: #dc2626; }

td.reprise-orange.selected {
    background-color: orange; /* Fond orange pour la case entière */
    color: white; /* Texte blanc */
}


input[type="date"].selected {
    background-color: transparent; /* Retirer la couleur de fond de l'input pour la rendre visible */
}

td.facture-blue.selected {
    background-color: #66b3ff; /* Bleu plus foncé que le bleu clair */
    color: white; /* Texte blanc */
}

/* Conteneur principal : 2 zones, gauche et droite */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    width: 100%;
}

/* Zone gauche : barre de recherche */
.search-section {
    flex: 0 0 auto; /* Taille automatique */
    text-align: left;
}

/* Zone droite : filtres */
.checkbox-section {
    flex: 1; /* prend le reste de la place */
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
}

/* Style de la barre de recherche */
#searchOrderBar {
    padding: 10px 18px;
    font-size: 15px;
    border: 2px solid #ccc;
    border-radius: 25px;
    outline: none;
    transition: all 0.3s ease;
    width: 70px;
    background-color: #f9f9f9;
    color: #333;
    font-family: Arial, sans-serif;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    margin-right: auto; /* 🔹 Force l’alignement à gauche */
}

#searchOrderBar::placeholder {
    color: #888;
    font-style: italic;
}

#searchOrderBar:hover {
    border-color: #007BFF;
    background-color: #eef6ff;
}

#searchOrderBar:focus {
    border-color: #007BFF;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.4);
    background-color: #fff;
}

/* ========== RESPONSIVE MOBILE - ACCORDÉON ========== */
@media screen and (max-width: 768px) {
    body { 
        padding: 8px !important; 
        font-size: 14px; 
    }
    
    /* Adaptations générales */
    .back-button { 
        position: static !important; 
        margin: 10px auto !important; 
        width: 90%;
        max-width: 300px;
        display: block;
        text-align: center;
    }
    
    h1 { 
        font-size: 20px !important; 
        padding-top: 10px !important; 
        margin-bottom: 15px !important;
    }
    
    /* Logos clubs - PLUS GRANDS */
    .club-btn { 
        width: 75px !important; 
        height: 75px !important; 
        padding: 5px !important;
    }
    
    #clubButtons { 
        gap: 12px !important; 
        padding: 10px 5px; 
        margin-bottom: 20px !important;
    }
    
    /* Filtres de rôles - mieux espacés */
    #filterContainer { 
        margin-top: 20px !important; 
        gap: 8px !important; 
        padding: 0 5px;
        display: flex !important;
        justify-content: center !important;
        flex-wrap: wrap !important;
    }
    
    .filter-btn { 
        padding: 8px 15px; 
        font-size: 14px; 
        font-weight: 600;
        cursor: pointer;
        border: 2px solid #ddd;
        background: #f0f0f0;
        border-radius: 20px;
        transition: all 0.2s;
    }
    
    .filter-btn.active {
        background: #007BFF;
        color: white;
        border-color: #007BFF;
    }
    
    .filter-btn:active {
        transform: scale(0.95);
    }
    
    /* MASQUER l'export sur mobile */
    #searchContainer {
        position: static !important;
        display: block !important;
        margin: 15px 0;
        padding: 0;
    }
    
    #exportButton { 
        display: none !important; /* Masqué sur mobile */
    }
    
    /* Barre de recherche - adaptée mobile */
    .filter-bar {
        flex-direction: row;
        gap: 5px;
        padding: 0;
        flex-wrap: wrap;
        justify-content: flex-start;
        align-items: center;
    }
    
    .search-section {
        width: auto;
        text-align: left;
        flex: 0 0 auto;
    }
    
    /* Filtres checkbox - alignés avec searchOrderBar */
    .checkbox-section {
        width: auto;
        flex: 1;
        justify-content: flex-start;
    }
    
    #checkboxFilters { 
        justify-content: flex-start !important; 
        gap: 5px; 
        padding: 10px 5px;
    }
    
    .filter-label span { 
        font-size: 12px; 
        padding: 7px 10px; 
        font-weight: 500;
    }
    
    /* Résumé articles manquants */
    #missingSummaryContainer { 
        padding: 10px; 
        margin-top: 20px;
    }
    
    #missingSummaryContainer h3 {
        font-size: 17px;
        margin-bottom: 10px;
    }
    
    .missing-badge { 
        font-size: 13px; 
        padding: 6px 12px; 
    }
    
    
    /* ===== HARMONISATION DES POLICES SUR MOBILE ===== */
    @media (max-width: 768px) {
        body, 
        #ordersTable tbody tr .card-header,
        #ordersTable tbody tr .card-details,
        #searchOrderBar,
        #filterPack,
        .filter-btn,
        .filter-label span,
        #missingSummaryContainer,
        .missing-badge {
            font-family: Arial, sans-serif !important;
        }
        
        /* Titres et labels avec même police */
        #ordersTable tbody tr .card-details .detail-title,
        #ordersTable tbody tr .card-details .size-label,
        #ordersTable tbody tr .card-details .action-label {
            font-family: Arial, sans-serif !important;
        }
        
        /* Valeurs et contenus */
        #ordersTable tbody tr .card-details .detail-value,
        #ordersTable tbody tr .card-details .size-value {
            font-family: Arial, sans-serif !important;
        }
    }
    
    /* ===== TABLEAU EN ACCORDÉON ===== */
    
    #ordersTable thead {
        display: none;
    }
    
    #ordersTable {
        display: block;
        width: 100%;
    }
    
    #ordersTable tbody {
        display: block;
        width: 100%;
    }
    
    /* Chaque ligne = une carte */
    #ordersTable tbody tr {
        display: block;
        margin-bottom: 15px;
        border: 2px solid #ddd;
        border-radius: 12px;
        background: white;
        box-shadow: 0 3px 8px rgba(0,0,0,0.12);
        overflow: hidden;
        position: relative;
    }
    
    /* CODE COULEUR - Ligne terminée = FOND VERT CLAIR */
    #ordersTable tbody tr.completed {
        background: #d4edda !important;
        border-left: 5px solid #28a745;
    }
    
    #ordersTable tbody tr.completed .card-header {
        background: #c3e6cb !important;
    }
    
    #ordersTable tbody tr.completed .card-header .name-info {
        color: #155724 !important;
        font-weight: 700 !important;
    }

    /* grey-row PRIORITAIRE sur completed — email interne */
    #ordersTable tbody tr.grey-row,
    #ordersTable tbody tr.grey-row td,
    #ordersTable tbody tr.grey-row.completed,
    #ordersTable tbody tr.grey-row.completed td {
        background: #d1d5db !important;
        background-color: #d1d5db !important;
        border-left: 4px solid #9ca3af !important;
        color: #6b7280 !important;
        opacity: 0.8;
    }
    #ordersTable tbody tr.grey-row .card-header,
    #ordersTable tbody tr.grey-row.completed .card-header {
        background: #e5e7eb !important;
    }
    #ordersTable tbody tr.grey-row .card-header .name-info,
    #ordersTable tbody tr.grey-row.completed .card-header .name-info {
        color: #6b7280 !important;
    }
    #ordersTable tbody tr.grey-row td input,
    #ordersTable tbody tr.grey-row td a {
        color: #6b7280 !important;
    }
    
    /* CODE COULEUR - Pack avec scan = BADGE JAUNE */
    #ordersTable tbody tr .card-header .pack-number.scan-yellow {
        background: #ffc107 !important;
        color: #000 !important;
    }
    
    /* En-tête de carte */
    #ordersTable tbody tr .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px;
        background: #f8f9fa;
        border-bottom: 1px solid #ddd;
        cursor: pointer;
        font-weight: 600;
    }
    
    #ordersTable tbody tr .card-header .pack-number {
        background: #007BFF;
        color: white;
        padding: 6px 14px;
        border-radius: 18px;
        font-size: 15px;
        font-weight: bold;
        min-width: 50px;
        text-align: center;
    }
    
    #ordersTable tbody tr .card-header .name-info {
        flex: 1;
        margin: 0 12px;
        font-size: 15px;
        color: #333;
    }
    
    #ordersTable tbody tr .card-header .toggle-icon {
        font-size: 20px;
        color: #007BFF;
        transition: transform 0.3s;
        font-weight: bold;
    }
    
    #ordersTable tbody tr.expanded .card-header .toggle-icon {
        transform: rotate(180deg);
    }
    
    /* Masquer les TD */
    #ordersTable tbody tr td {
        display: none;
        border: none !important;
    }
    
    /* Container des détails */
    #ordersTable tbody tr .card-details {
        display: none;
        padding: 0;
    }
    
    #ordersTable tbody tr.expanded .card-details {
        display: block;
    }
    
    /* Sections d'infos */
    #ordersTable tbody tr .card-details .detail-section {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    #ordersTable tbody tr .card-details .detail-section:last-child {
        border-bottom: none;
    }
    
    #ordersTable tbody tr .card-details .detail-title {
        font-weight: bold;
        color: #666;
        font-size: 11px;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
        letter-spacing: 0.5px;
    }
    
    #ordersTable tbody tr .card-details .detail-value {
        font-size: 12px;
        color: #333;
        font-weight: 500;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    /* Grille des articles - 2 colonnes */
    #ordersTable tbody tr .card-details .sizes-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 15px;
        background: #f8f9fa;
    }
    
    #ordersTable tbody tr .card-details .size-item {
        background: white;
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        min-height: 65px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    #ordersTable tbody tr .card-details .size-item:active {
        transform: scale(0.95);
    }
    
    /* CODE COULEUR - Cases manquantes = ROUGE */
    #ordersTable tbody tr .card-details .size-item.missing {
        background-color: #ffcccc !important;
        border-color: #ff4444 !important;
        border-width: 2px;
    }
    
    #ordersTable tbody tr .card-details .size-item .size-label {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        font-weight: bold;
        display: block;
        margin-bottom: 6px;
        letter-spacing: 0.3px;
    }
    
    #ordersTable tbody tr .card-details .size-item .size-value {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    
    /* Section actions */
    #ordersTable tbody tr .card-details .actions-section {
        padding: 15px;
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    #ordersTable tbody tr .card-details .actions-section .action-item {
        background: white;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    
    #ordersTable tbody tr .card-details .actions-section .action-label {
        font-size: 11px;
        font-weight: bold;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
    }
    
    #ordersTable tbody tr .card-details .actions-section input[type="date"] {
        width: 100%;
        padding: 6px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        -webkit-appearance: none;
        appearance: none;
        background: white;
        max-width: 100%;
        box-sizing: border-box;
    }
    
    #ordersTable tbody tr .card-details .actions-section input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        font-size: 12px;
    }
    
    /* CODE COULEUR - Cotisation avec date */
    #ordersTable tbody tr .card-details .actions-section .action-item.selected input[type="date"] {
        background-color: #cfe2ff;
        border-color: #007BFF;
        font-weight: 600;
    }
    
    /* CODE COULEUR - Date sélectionnée = BLEU */
    #ordersTable tbody tr .card-details .actions-section input[type="date"].selected {
        background-color: #cfe2ff;
        border-color: #007BFF;
        font-weight: 600;
    }
    
    /* CODE COULEUR - Facture = BLEU CLAIR */
    #ordersTable tbody tr .card-details .actions-section .action-item.facture-blue input[type="date"],
    #ordersTable tbody tr .card-details .actions-section .facture-blue input {
        background-color: #e7f3ff;
        border-color: #66b3ff;
        font-weight: 600;
    }
    
    /* CODE COULEUR - Reprise = ORANGE */
    #ordersTable tbody tr .card-details .actions-section .action-item.reprise-orange input[type="date"],
    #ordersTable tbody tr .card-details .actions-section .reprise-orange input {
        background-color: #fff3cd;
        border-color: #ffc107;
        font-weight: 600;
    }
    
    #ordersTable tbody tr .card-details .actions-section .buttons-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    #ordersTable tbody tr .card-details .actions-section button {
        flex: 1;
        min-width: 120px;
        padding: 12px;
        font-size: 14px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
    }
    
    /* Boutons Facturé et Repris - visibles et cliquables */
    #ordersTable tbody tr .card-details .actions-section .action-item button {
        width: 100%;
        padding: 12px;
        font-size: 14px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }
    
    #ordersTable tbody tr .card-details .actions-section .action-item button:active {
        transform: scale(0.95);
    }
    
    /* Bouton Facturé - couleur bleue */
    #ordersTable tbody tr .card-details .actions-section .action-item button[onclick*="toggleFacture"] {
        background: #007bff;
        color: white;
    }
    
    #ordersTable tbody tr .card-details .actions-section .action-item.facture-blue button[onclick*="toggleFacture"] {
        background: #0056b3;
        color: white;
    }
    
    /* Bouton Repris - couleur orange */
    #ordersTable tbody tr .card-details .actions-section .action-item button[onclick*="toggleReprise"] {
        background: #ffc107;
        color: #000;
    }
    
    #ordersTable tbody tr .card-details .actions-section .action-item.reprise-orange button[onclick*="toggleReprise"] {
        background: #e0a800;
        color: #000;
    }
    
    /* Section séparée pour les boutons en bas */
    #ordersTable tbody tr .card-details .buttons-section {
        padding: 15px;
        background: #f8f9fa;
        border-top: 2px solid #dee2e6;
        margin-top: 0;
    }
    
    #ordersTable tbody tr .card-details .buttons-section .buttons-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    #ordersTable tbody tr .card-details .buttons-section button,
    #ordersTable tbody tr .card-details .buttons-section .delete {
        flex: 1;
        min-width: 100px;
        padding: 14px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    #ordersTable tbody tr .card-details .buttons-section button.completed {
        background: #28a745;
        color: white;
    }
    
    #ordersTable tbody tr .card-details .buttons-section button.edit {
        background: #007bff;
        color: white;
    }
    
    #ordersTable tbody tr .card-details .buttons-section .delete {
        background: #dc3545;
        color: white;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .club-logo { 
        width: 28px !important; 
        height: 28px !important; 
    }
}

@media screen and (max-width: 480px) {
    h1 { font-size: 18px !important; }
    .club-btn { width: 65px !important; height: 65px !important; }
    
    #ordersTable tbody tr .card-details .sizes-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 12px;
    }
    
    #ordersTable tbody tr .card-details .size-item {
        padding: 10px;
        min-height: 60px;
    }
    
    #ordersTable tbody tr .card-details .size-item .size-value {
        font-size: 16px;
    }
}

    </style>
    <script>
	
	
// Fonction pour marquer la commande comme terminée
function markCompleted(orderId, button) {
    console.log("Marquage de la commande comme terminée : ID " + orderId);

    // Effectuer une requête AJAX pour récupérer l'email de la commande
    let xhrEmail = new XMLHttpRequest();
    xhrEmail.open('GET', 'get_email.php?orderId=' + orderId, true);  // Passer l'ID de la commande pour récupérer l'email
    xhrEmail.onload = function() {
        if (xhrEmail.status === 200) {
            try {
                let response = JSON.parse(xhrEmail.responseText);
                console.log("Réponse de récupération de l'email : " + xhrEmail.responseText);

                if (response.success && response.email) {
                    let email = response.email;
                    console.log("Adresse email du destinataire : " + email);  // Log de l'email récupéré

                    // Si l'email est trouvé, procéder à la mise à jour de la commande
                    updateOrder(orderId, email, button);
                } else {
                    console.error("L'email n'a pas été trouvé pour la commande.");
                }
            } catch (e) {
                console.error("Erreur lors de la récupération de l'email : " + e.message);
            }
        } else {
            console.error("Erreur lors de la récupération de l'email.", xhrEmail.responseText);
        }
    };
    xhrEmail.send();
}

function updateOrder(orderId, email, button) {
    console.log("Mise à jour de la commande avec l'ID : " + orderId);

    // Vérification de l'email avant de continuer
    if (!email) {
        console.error("L'adresse email est manquante.");
        return;
    }

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');  // Envoi des données en format JSON

    // Données envoyées sous forme de JSON, incluant l'email
    let data = JSON.stringify({
        orderId: orderId,
        field: 'completed',
        value: 1,  // Marquer comme terminé (1)
        email: email  // Ajouter l'email dans les données envoyées
    });
    console.log("Données envoyées au serveur : " + data);  // Log des données envoyées
    xhr.send(data);

    // Lorsque la requête est terminée, vérifier si la mise à jour a réussi
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log("Réponse du serveur : " + xhr.responseText);  // Log de la réponse serveur

                if (response.success) {
                    console.log("La commande a été marquée comme terminée.");  // Message de confirmation
                    // Modifier la couleur de la ligne pour la marquer comme terminée
                    button.closest('tr').classList.add('completed');
                    alert('La commande a été marquée comme terminée.');
                    sendCompletionEmail(orderId, email);  // Passer l'email lors de l'envoi de l'email
                } else {
                    console.error("Erreur lors du marquage comme terminé : " + response.message);
                    alert("Erreur : " + response.message);
                }
            } catch (e) {
                console.error("Erreur lors du traitement de la réponse : " + e.message);
                alert("Erreur de réponse du serveur");
            }
        } else {
            console.error("Erreur lors de la mise à jour de la commande.", xhr.responseText);
        }
    };

    // Log de l'état de la requête (avant l'envoi)
    xhr.onreadystatechange = function() {
        console.log("État de la requête : " + xhr.readyState);
    };
}

// Fonction pour envoyer l'email
function sendCompletionEmail(orderId, email) {
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'send_email.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('orderId=' + orderId + '&email=' + encodeURIComponent(email));  // Utiliser l'encodage URL

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log("Réponse de l'email : ", response);  // Vérifiez la réponse ici
                if (response.success) {
                    console.log('Email envoyé');
                } else {
                    console.error('Erreur d\'envoi d\'email : ', response.message);
                }
            } catch (e) {
                console.error('Erreur lors de la réponse : ', e.message);
            }
        } else {
            console.error('Erreur lors de l\'envoi de la requête AJAX', xhr.responseText);
        }
    };

    xhr.onerror = function() {
        console.error('Erreur AJAX : ', xhr.statusText);
    };
}

// Fonction pour supprimer une commande
function deleteOrder(orderId, row) {
    // Demander confirmation avant suppression
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette commande ?\n\nCette action est irréversible.')) {
        return; // Annuler si l'utilisateur clique sur "Annuler"
    }
    
    console.log("Suppression de la commande : ID " + orderId);
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'delete_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');  // Envoi des données en format JSON

    // Données envoyées sous forme de JSON
    let data = JSON.stringify({
        orderId: orderId
    });
    console.log("Données envoyées au serveur : " + data);
    xhr.send(data);

    // Lorsque la requête est terminée, vérifier si la suppression a réussi
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log("Réponse du serveur : " + xhr.responseText);

                if (response.success) {
                    // Supprimer la ligne de la table
                    row.remove();
                    alert('La commande a été supprimée avec succès.');
                } else {
                    console.error("Erreur lors de la suppression de la commande : " + response.message);
                    alert("Erreur : " + response.message);
                }
            } catch (e) {
                console.error("Erreur lors du traitement de la réponse : " + e.message);
                alert("Erreur de réponse du serveur");
            }
        } else {
            console.error("Erreur lors de la suppression de la commande.", xhr.responseText);
        }
    };
}

        // Filtrer les commandes par club
function filterOrdersByClub(club) {
    console.log("Filtrer les commandes par club : " + club);
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const rowClub = row.getAttribute('data-club');  // Utiliser 'data-club' ici
        if (club === 'all' || rowClub === club) {
            row.style.display = '';  // Affiche la ligne
        } else {
            row.style.display = 'none';  // Cache la ligne
        }
    });
}
function toggleMissing(orderId, field, element) {
    console.log("Changement du statut manquant pour la commande " + orderId + ", champ : " + field);
    let currentStatus = element.classList.contains('missing');
    let newStatus = currentStatus ? 0 : 1;

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');

    let data = JSON.stringify({
        orderId: orderId,
        field: field,
        value: newStatus
    });
    console.log("Données envoyées au serveur : " + data);
    xhr.send(data);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log("Réponse du serveur : " + xhr.responseText);

                if (response.success) {
                    // Gérer l'affichage
                    if (newStatus === 1) {
                        element.classList.add('missing');
                    } else {
                        element.classList.remove('missing');
                    }
                } else {
                    console.error("Erreur lors de la mise à jour du statut manquant : " + response.message);
                    alert("Erreur : " + response.message);
                }
            } catch (e) {
                console.error("Erreur lors du traitement de la réponse : " + e.message);
                alert("Erreur de réponse du serveur");
            }
        } else {
            console.error("Erreur lors de la mise à jour de l'état de la commande.", xhr.responseText);
        }
    };
}

// Fonction pour formater la date en mois et jour uniquement
function formatDateForDisplay(date) {
    let d = new Date(date);
    let month = d.toLocaleString('default', { month: 'short' });  // Obtenir le mois au format abrégé (ex : Jan, Feb)
    let day = String(d.getDate()).padStart(2, '0');  // Ajouter un zéro devant si nécessaire pour les jours < 10
    return `${month} ${day}`;  // Format final : "Jan 08"
}

// Exemple de mise à jour de la valeur de l'input
document.querySelectorAll('input[type="date"]').forEach(input => {
    let today = new Date();
    input.value = formatDateForDisplay(today);  // Appliquer la date formatée (mois jour)
});
function searchOrders() {
    let input = document.getElementById("searchBar").value.toLowerCase();
    let rows = document.querySelectorAll("tbody tr");

    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}


function searchNumberOrders() {
    let input = document.getElementById("searchOrderBar").value.toLowerCase();
    let rows = document.querySelectorAll("tbody tr");
    const isMobile = window.innerWidth <= 768;

    rows.forEach(row => {
        let orderNumber = "";
        
        if (isMobile) {
            // MODE MOBILE : chercher dans le .pack-number
            const packSpan = row.querySelector(".pack-number");
            if (packSpan) {
                orderNumber = packSpan.textContent.toLowerCase();
            }
        } else {
            // MODE DESKTOP : première cellule
            const orderNumberCell = row.querySelector("td");
            if (orderNumberCell) {
                orderNumber = orderNumberCell.textContent.toLowerCase();
            }
        }

        row.style.display = orderNumber.includes(input) ? "" : "none";
    });
}


function saveCotisation(orderId, element) {
    let value = element.value;
    console.log("Valeur de cotisation_payee avant l'envoi :", value);  // Log de la valeur

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    let data = JSON.stringify({ 
        orderId: orderId, 
        field: 'cotisation_payee', 
        value: value 
    });

    console.log("Données envoyées à update_order.php:", data);  // Log de la requête

    xhr.send(data);

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log('Réponse du serveur :', response);  // Log de la réponse du serveur
                if (response.success) {
                    console.log("Date de cotisation payée mise à jour avec succès");
                    printCotisationReceipt(orderId); // Lancer l'impression après mise à jour
                } else {
                    console.error('Erreur de mise à jour de la cotisation payée:', response.message);
                }
            } catch (e) {
                console.error('Erreur lors de la réponse AJAX :', e.message);
            }
        } else {
            console.error('Erreur lors de la mise à jour de la cotisation payée.', xhr.responseText);
        }
    };

    xhr.onerror = function() {
        console.error('Erreur AJAX:', xhr.statusText);
    };
}




function displayCotiDate(orderId, date) {
    // Trouver l'élément de la cotisation correspondant à la commande et afficher la date
    let cotiElement = document.querySelector(`#order-${orderId} .cotisation-date`);
    if (cotiElement) {
        cotiElement.textContent = `Cotisation Payée: ${date}`;
    }
}



function updateCotisationStatus(orderId, factureStatus, factureDate) {
    console.log("Envoi de la requête pour mettre à jour coti_status : ", orderId, factureStatus, factureDate);

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    let data = JSON.stringify({
        orderId: orderId,
        field: 'coti_status',  // Remplacer 'facture_status' par 'coti_status'
        value: factureStatus,  // Valeur à insérer dans coti_status
        factureDate: factureDate  // La date à insérer dans le champ facture (si facturé)
    });

    xhr.send(data);

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Si la mise à jour est réussie, assurez-vous que la case reste bleue
                    console.log('Coti_status mis à jour avec succès');
                } else {
                    console.error('Erreur de mise à jour de coti_status :', response.message);
                }
            } catch (e) {
                console.error('Erreur lors de la réponse AJAX :', e.message);
            }
        } else {
            console.error('Erreur lors de la mise à jour de coti_status.', xhr.responseText);
        }
    };
}

function updateCotisation(orderId, date) {
    console.log("Envoi des données à update_order.php pour cotisation_payee...");
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    let data = JSON.stringify({
        orderId: orderId,
        field: 'cotisation_payee', // Le champ à mettre à jour
        value: date  // La date du jour ou vide selon le statut
    });
    
    console.log("Données envoyées à update_order.php:", data);  // Log pour vérifier

    xhr.send(data);

    xhr.onload = function() {
        if (xhr.status == 200) {
            console.log("Réponse du serveur pour cotisation_payee:", xhr.responseText);
            let response = JSON.parse(xhr.responseText);
            if (response.success) {
                console.log("Cotisation payée mise à jour avec succès.");
            } else {
                console.log("Erreur lors de la mise à jour de cotisation payée:", response.message);
            }
        } else {
            console.log("Erreur lors de la mise à jour de cotisation payée:", xhr.responseText);
        }
    };
}
function toggleSelected(element, type) {
    // Sur mobile, chercher soit le TD soit le action-item parent
    var td = element.closest('td');
    var actionItem = element.closest('.action-item');
    
    if (td) {
        td.classList.toggle('selected'); // Ajoute ou retire la classe 'selected' du <td>
        
        // Si c'est une reprise, ajoute aussi la classe 'reprise-orange'
        if (type === 'reprise') {
            td.classList.toggle('reprise-orange');
        }
        
        // Si c'est une facture, applique la classe 'facture-blue'
        if (type === 'facture') {
            td.classList.toggle('facture-blue');
        }
    }
    
    // Sur mobile, appliquer aussi les classes au action-item
    if (actionItem) {
        actionItem.classList.toggle('selected');
        
        if (type === 'reprise') {
            actionItem.classList.toggle('reprise-orange');
        }
        
        if (type === 'facture') {
            actionItem.classList.toggle('facture-blue');
        }
    }

    const orderId = element.closest('tr').dataset.orderId;
    let status = (td && td.classList.contains('selected')) || (actionItem && actionItem.classList.contains('selected')) ? 1 : 0;

    // Mettre la date actuelle pour la reprise, cotisation et facture
    let today = status === 1 ? new Date().toISOString().split('T')[0] : '';  // Extrait la date sans l'heure

    if (type === 'facture') {
        element.value = today; // Met la date dans l'input
        updateFactureStatus(orderId, status, today); // Envoie la date de facturation
        displayFactureDate(orderId, today);  // Affiche la date de facturation dans l'élément approprié
    } else if (type === 'cotisation') {
        element.value = today;
        updateCotisationStatus(orderId, status, today);  // Met à jour le statut de la cotisation
        saveCotisation(orderId, element);  // Met à jour la cotisation
    } else if (type === 'reprise') {  // Ajouter la logique pour 'reprise'
        element.value = today;
        updateRepriseStatus(orderId, status, today);  // Met à jour le statut de la reprise
        displayRepriseDate(orderId, today);  // Affiche la date de reprise dans l'élément approprié
    }
}

// Modification de la fonction pour inclure la date
function updateFactureStatus(orderId, factureStatus, factureDate) {
    console.log("Envoi de la requête pour mettre à jour facture_status : ", orderId, factureStatus, factureDate);

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    // Vérifier si factureDate est vide ou invalide avant de l'envoyer
    let data = {
        orderId: orderId,
        field: 'facture_status',
        value: factureStatus,  // Mettre à jour le statut
        factureDate: factureDate && factureDate !== '' ? factureDate : null  // Si factureDate est vide ou null, l'envoyer comme null
    };

    xhr.send(JSON.stringify(data));

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                if (response.success) {
                    console.log('Facture mise à jour avec succès');
                } else {
                    console.error('Erreur de mise à jour de la facture :', response.message);
                }
            } catch (e) {
                console.error('Erreur de réponse AJAX :', e.message);
            }
        } else {
            console.error('Erreur lors de la mise à jour de la facture.', xhr.responseText);
        }
    };
}

function updateFactureDateDisplay(orderId, factureDate) {
    let factureInput = document.querySelector(`#facture_date_${orderId}`);
    if (factureInput) {
        factureInput.value = factureDate ? factureDate : '';  // Mettre la nouvelle date ou une chaîne vide si pas de date
        console.log(`Date de facture mise à jour pour la commande ${orderId}:`, factureDate);
    } else {
        console.error(`Élément pour la date de facture non trouvé pour la commande ${orderId}`);
    }
}

// Fonction pour afficher la date de la facture dans l'élément approprié
function displayFactureDate(orderId, factureDate) {
    console.log(`Recherche de l'input pour la commande ${orderId}`);
    
    // Utiliser un intervalle pour s'assurer que l'élément est disponible
    let attempts = 0;
    let maxAttempts = 10;
    let interval = setInterval(() => {
        let factureInput = document.querySelector(`#facture_date_${orderId}`);
        
        if (factureInput) {
            factureInput.value = factureDate ? factureDate : '';  // Mettre la nouvelle date ou une chaîne vide si pas de date
            console.log(`Date de facture mise à jour pour la commande ${orderId}:`, factureDate);
            clearInterval(interval);  // Arrêter l'intervalle une fois l'élément trouvé
        } else if (attempts >= maxAttempts) {
            console.error(`Élément input pour la date de facture non trouvé pour la commande ${orderId}`);
            clearInterval(interval);  // Arrêter après le nombre maximal d'essais
        }
        attempts++;
    }, 100);  // Réessayer toutes les 100ms
}


// Modification de la fonction pour mettre à jour le statut de reprise et la date de reprise
function updateRepriseStatus(orderId, repriseStatus, repriseDate) {
    console.log("Envoi de la requête pour mettre à jour reprise : ", orderId, repriseStatus, repriseDate);

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    // Vérifier si repriseDate est vide ou invalide avant de l'envoyer
    let data = {
        orderId: orderId,
        field: 'reprise',
        value: repriseStatus,  // Mettre à jour le statut de reprise
        repriseDate: repriseDate && repriseDate !== '' ? repriseDate : null  // Si repriseDate est vide ou null, l'envoyer comme null
    };

    xhr.send(JSON.stringify(data));

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                if (response.success) {
                    console.log('Reprise mise à jour avec succès');
                } else {
                    console.error('Erreur de mise à jour de la reprise :', response.message);
                }
            } catch (e) {
                console.error('Erreur de réponse AJAX :', e.message);
            }
        } else {
            console.error('Erreur lors de la mise à jour de la reprise.', xhr.responseText);
        }
    };
}

// Fonction pour afficher la date de reprise dans l'élément approprié
function updateRepriseDateDisplay(orderId, repriseDate) {
    let repriseInput = document.querySelector(`#reprise_date_${orderId}`);
    if (repriseInput) {
        repriseInput.value = repriseDate ? repriseDate : '';  // Mettre la nouvelle date ou une chaîne vide si pas de date
        console.log(`Date de reprise mise à jour pour la commande ${orderId}:`, repriseDate);
    } else {
        console.error(`Élément pour la date de reprise non trouvé pour la commande ${orderId}`);
    }
}

// Fonction pour afficher la date de reprise dans l'élément approprié avec un intervalle pour être sûr
function displayRepriseDate(orderId, repriseDate) {
    console.log(`Recherche de l'input pour la commande ${orderId}`);
    
    // Utiliser un intervalle pour s'assurer que l'élément est disponible
    let attempts = 0;
    let maxAttempts = 10;
    let interval = setInterval(() => {
        let repriseInput = document.querySelector(`#reprise_date_${orderId}`);
        
        if (repriseInput) {
            repriseInput.value = repriseDate ? repriseDate : '';  // Mettre la nouvelle date ou une chaîne vide si pas de date
            console.log(`Date de reprise mise à jour pour la commande ${orderId}:`, repriseDate);
            clearInterval(interval);  // Arrêter l'intervalle une fois l'élément trouvé
        } else if (attempts >= maxAttempts) {
            console.error(`Élément input pour la date de reprise non trouvé pour la commande ${orderId}`);
            clearInterval(interval);  // Arrêter après le nombre maximal d'essais
        }
        attempts++;
    }, 100);  // Réessayer toutes les 100ms
}

const printFiles = {
    "Bas-Oha": "print_bas-oha.php",
    "RFCB Sprimont": "print_sprimont.php",
    "Ellas": "print_ellas.php",
    "JSN": "print_jsn.php",
    "Union Hutoise": "print_uh.php",
    "Hannut": "print_hannut.php"
};
function printCotisationReceipt(orderId) {
    // Récupère le club depuis l'URL ou par défaut
    const urlParams = new URLSearchParams(window.location.search);
    const currentClub = urlParams.get("club") || "Bas-Oha";

    // Récupère le fichier correspondant au club
    const printFile = printFiles[currentClub] || "print_bas-oha.php";

    // Redirection vers le fichier print avec l'orderId
    window.open(`${printFile}?orderId=${orderId}`, "_blank");
}




function sortCotisation() {
    let xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_orders.php?cotisation_payee=NULL', true);  // On passe la condition pour "NULL"
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let orders = JSON.parse(xhr.responseText);
                console.log("Réponse du serveur : ", orders);
                
                // Réinitialiser l'affichage des commandes
                let tbody = document.querySelector('tbody');
                tbody.innerHTML = '';  // Vider le tableau

                // Afficher les commandes filtrées
                orders.forEach(order => {
                    let row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${order.id}</td>
                        <td>${order.name}</td>
                        <td>${order.cotisation_payee ? 'Payée' : 'Non payée'}</td>
                        <td><button onclick="deleteOrder(${order.id}, this.closest('tr'))">Supprimer</button></td>
                    `;
                    tbody.appendChild(row);
                });
            } catch (e) {
                console.error("Erreur de traitement des données : " + e.message);
            }
        } else {
            console.error("Erreur lors de la récupération des commandes.", xhr.responseText);
        }
    };

    xhr.send();
}

function editOrder(button, orderId) {
    let row = button.closest("tr");
    let cells = row.querySelectorAll("td[data-column]"); // Sélectionne uniquement les cellules avec data-column

    if (button.innerHTML === "✏️") {  // Vérifier si le bouton contient un crayon
        if (!confirm("Modifier cette commande ?")) return;
        cells.forEach(cell => {
            let value = cell.textContent.trim();
            cell.innerHTML = `<input type="text" value="${value}">`;
        });
        button.innerHTML = "✔️";  // Remplacer le crayon par une coche pour enregistrer
    } else { // Quand on clique sur la coche
        let updatedData = {};
        cells.forEach(cell => {
            let input = cell.querySelector("input");
            if (input) {
                let columnName = cell.getAttribute("data-column");
                if (columnName) { // Vérification que la colonne existe
                    updatedData[columnName] = input.value;
                    cell.textContent = input.value;
                }
            }
        });

        fetch("modifier_order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: orderId, ...updatedData })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  button.innerHTML = "✏️";  // Remettre le crayon après la sauvegarde
              } else {
                  alert("Erreur lors de la mise à jour.");
              }
          });
    }
}


/* place ce script après tes fonctions existantes ou juste avant </body> */

/* version améliorée de loadClub : accepte l'élément cliqué (optionnel) */
function loadClub(clubName, el) {
    // si un élément est fourni, met à jour visuellement tout de suite
    if (el) {
        document.querySelectorAll('.club-btn').forEach(btn => btn.classList.remove('active'));
        el.classList.add('active');
    }
    // redirection (conserve ton comportement existant)
    window.location.href = window.location.pathname + '?club=' + encodeURIComponent(clubName);
}

/* au chargement, lit le param ?club= et marque le logo correspondant */
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const currentClub = urlParams.get('club');

    if (!currentClub) return; // rien à faire

    // Cherche un <img> avec data-club exactement égal, si pas trouvé on essaye l'attribut alt
    let selector = `[data-club="${CSS.escape(currentClub)}"]`;
    let btn = document.querySelector(selector);

    if (!btn) {
        // fallback : chercher par alt (pratique si tu n'as pas ajouté data-club)
        btn = Array.from(document.querySelectorAll('.club-btn')).find(img => {
            return img.alt && img.alt.trim() === currentClub;
        });
    }

    if (btn) {
        // retire active des autres et applique sur celui-là
        document.querySelectorAll('.club-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // optionnel : agrandir visuellement (si ta CSS gère .club-btn.active ça suffit)
        // btn.scrollIntoView({behavior: 'smooth', block: 'center'}); // si tu veux le centrer
    } else {
        console.warn('Logo correspondant au club non trouvé dans le DOM pour :', currentClub);
    }
});


// 🔧 Configuration des colonnes par club
const clubColumns = {
    "Bas-Oha": [
        { label: "Veste", field: "jacket_size" },
        { label: "Pantalon", field: "pants_size" },
		{ label: "Bas", field: "bas_size" },
		{ label: "Sous-pull", field: "under_shirt_size" },
		{ label: "Maillot", field: "jersey_size" },
		{ label: "Short", field: "short_size" },
		{ label: "Opt Kway", field: "option_kway" },
		{ label: "Opt Bas", field: "option_bas" }
    ],
    "RFCB Sprimont": [
        { label: "Veste", field: "jacket_size" },
        { label: "Pantalon", field: "pants_size" },
		{ label: "Maillot", field: "jersey_size" },
		{ label: "Bas", field: "bas_size" },
		{ label: "Short", field: "short_size" },
		{ label: "Polo", field: "polo_size" }
    ],
    "Ellas": [
        { label: "Veste", field: "jacket_size" },
        { label: "Pantalon", field: "pants_size" },
		{ label: "Kit", field: "kit_size" },
		{ label: "Bas", field: "bas_size" },
		{ label: "Short", field: "short_size" },
		{ label: "Polo", field: "polo_size" }
    ],
    "JSN": [
        { label: "Veste", field: "jacket_size" },
        { label: "Pantalon", field: "pants_size" }
    ],
    "Union Hutoise": [
        { label: "Veste", field: "jacket_size" },
        { label: "Pantalon", field: "pants_size" },
		{ label: "Maillot", field: "jersey_size" },
		{ label: "Bas", field: "bas_size" },
		{ label: "Short", field: "short_size" },
    ],
    "Hannut": [
        { label: "Veste", field: "jacket_size" },
        { label: "Pantalon", field: "pants_size" },
		{ label: "Maillot", field: "jersey_size" },
		{ label: "Bas", field: "bas_size" }
    ]
};

// 🧠 Fonction appelée quand on clique sur un club
function loadClub(clubName) {
    // Stocke le club dans l'URL pour conserver le comportement PHP existant
    window.location.href = window.location.pathname + '?club=' + encodeURIComponent(clubName);
}

// ⚙️ Quand la page est chargée, on met à jour les colonnes dynamiquement
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentClub = urlParams.get("club") || "Bas-Oha";
    updateTableColumns(currentClub);
});

// 🪄 Fonction pour modifier dynamiquement les en-têtes et les colonnes visibles
function updateTableColumns(clubName) {
    const table = document.getElementById("ordersTable");
    const headers = table.querySelectorAll("thead th");

    // Cache toutes les colonnes "produit" (à partir de la colonne 9 jusqu'à avant les colonnes finales)
    headers.forEach((th, i) => {
        if (i >= 8 && i < headers.length - 5) { // laisse les colonnes coti/facture/etc.
            th.style.display = "none";
        }
    });

    // Affiche uniquement les colonnes définies pour ce club
    const config = clubColumns[clubName] || [];
    config.forEach(col => {
        headers.forEach(th => {
            if (th.textContent.trim().startsWith(col.label)) {
                th.style.display = "";
            }
        });
    });

    // Cache ou affiche les cellules correspondantes dans le corps du tableau
    const rows = table.querySelectorAll("tbody tr");
    rows.forEach(row => {
        const cells = row.querySelectorAll("td");
        cells.forEach((td, i) => {
            if (i >= 8 && i < headers.length - 5) {
                td.style.display = "none";
            }
        });

        config.forEach(col => {
            headers.forEach((th, index) => {
                if (th.textContent.trim().startsWith(col.label)) {
                    cells[index].style.display = "";
                }
            });
        });
    });
}



    </script>
<?php
$refsFile = __DIR__ . '/references.json';
$articleRefs = [];
if (file_exists($refsFile)) {
    $decoded = json_decode(file_get_contents($refsFile), true);
    if ($decoded) {
        unset($decoded['_comment'], $decoded['_articles']);
        $articleRefs = $decoded;
    }
}
?>
<script>
const ARTICLE_REFS = <?php echo json_encode($articleRefs, JSON_UNESCAPED_UNICODE); ?>;
function getRef(articleLabel, club, role) {
    const c = ARTICLE_REFS[club]; if (!c) return null;
    const r = c[role] || null;    if (!r) return null;
    return r[articleLabel] || null;
}
</script>
</head>
<body>
    
 

<div id="clubButtons" style="margin: 20px 0; text-align:center;">
    <img src="bas-oha.png" alt="Bas-Oha" data-club="Bas-Oha" class="club-btn" onclick="loadClub('Bas-Oha', this)">
    <img src="sprimont.png" alt="RFCB Sprimont" data-club="RFCB Sprimont" class="club-btn" onclick="loadClub('RFCB Sprimont', this)">
    <img src="ellas.png" alt="Ellas" data-club="Ellas" class="club-btn" onclick="loadClub('Ellas', this)">
    <img src="jsn.png" alt="JSN" data-club="JSN" class="club-btn" onclick="loadClub('JSN', this)">
    <img src="hannut.png" alt="Hannut" data-club="Hannut" class="club-btn" onclick="loadClub('Hannut', this)">
    <img src="uh.png" alt="Union Hutoise" data-club="Union Hutoise" class="club-btn" onclick="loadClub('Union Hutoise', this)">
</div>


	<div id="searchContainer">
    <input type="text" id="searchBar" placeholder="Recherche" onkeyup="searchOrders()">
    <button id="scanButton" onclick="window.location.href='http://football_orders.marksports.eu/scanv1.html'">Scan</button>
    <button id="exportButton" onclick="exportToExcel()">Exporter en Excel</button>
</div>

<div id="filterContainer">
    <button class="filter-btn active" onclick="filterByRole('all', this)">Tous</button>
    <button class="filter-btn" onclick="filterByRole('⚽️', this)">Joueur</button>
    <button class="filter-btn" onclick="filterByRole('🧤', this)">Keeper</button>
    <button class="filter-btn" onclick="filterByRole('👶🏻', this)">Entraîneur</button>
</div>


<div id="checkboxFilters">
    <div class="filter-bar">
        <div class="search-section">
            <input 
                type="text" 
                id="searchOrderBar" 
                placeholder="N° Pack" 
                onkeyup="searchNumberOrders()"
            >
        </div>

        <div class="checkbox-section">
            <label class="filter-label">
                <input type="checkbox" id="filter_tout" checked onchange="applyFilters()">
                <span>Tout</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_manquant" onchange="onFilterChange()">
                <span>Manquant</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_coti" onchange="onFilterChange()">
                <span>Coti</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_facture" onchange="onFilterChange()">
                <span>Facturé</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_non_facture" onchange="onFilterChange()">
                <span>Non facturé</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_repris" onchange="onFilterChange()">
                <span>Repris</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_non_repris" onchange="onFilterChange()">
                <span>Non repris</span>
            </label>
            <label class="filter-label">
                <input type="checkbox" class="filter" id="filter_non_termine" onchange="onFilterChange()">
                <span>Non terminé</span>
            </label>
        </div>
    </div>
</div>






    <table id="ordersTable">
        <thead>
            <tr>
                <th>Pack</th>
                <th>Date</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Cat.</th>
                <th>Tél.</th>
                <th>Email</th>
                <th>Rôle</th> <!-- Nouvelle colonne rôle -->
                <th>Veste</th>
                <th>Pantalon</th>
                <th>Bas</th>
                <th>Sous-pull</th>
                <th>Maillot</th>
				<th>Short</th>
				<th>Opt Kway</th>
				<th>Opt Bas</th>
				<th>Polo</th>
				<th>Kit</th>
                <th>Initiales</th>
           
                <th>Coti Payée</th>
                <th>Facturé</th>
				 <th>Repris</th>
                <th>Terminé</th>
                
            </tr>
        </thead>
        <tbody>
		<?php
$clubRoles = [
    "Bas-Oha" => ["Joueur-WBO" => "⚽️", "Keeper-WBO" => "🧤"],
    "RFCB Sprimont" => ["U10-U21" => "⚽️", "Entraineur" => "👶🏻"],
    "Ellas" => ["Ellas-Joueur" => "⚽️", "Ellas-Keeper" => "🧤", "Ellas-Entraineur" => "👶🏻"],
    "JSN" => ["Joueur-JSN" => "⚽️", "Entraineur-JSN" => "👶🏻"],
    "Union Hutoise" => ["Joueur" => "⚽️", "Keeper" => "🧤"],
    "Hannut" => ["Joueur-Hannut" => "⚽️", "Keeper-Hannut" => "🧤"]
];
?>

            <?php
            $conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            $club = isset($_GET['club']) ? $_GET['club'] : 'Bas-Oha';
$sql = "SELECT * FROM orders WHERE club = '" . $conn->real_escape_string($club) . "'";
            $result = $conn->query($sql);


            while ($row = $result->fetch_assoc()) {
			$scanClass = ($row['scan'] == 1) ? 'scan-yellow' : '';
                $completedClass = ($row['completed'] == 1) ? 'completed' : '';
                $greyClass = (strtolower(trim($row['email'])) === 'stephanie@mksweb.be') ? 'grey-row' : '';
                echo "<tr data-order-id='" . htmlspecialchars($row['id']) . "' data-club='" . htmlspecialchars($row['club']) . "' data-role='" . htmlspecialchars($row['role']) . "' class='" . $completedClass . ' ' . $greyClass . "'>";
echo "<td class='" . $scanClass . "'>" . htmlspecialchars($row['order_number']) . "</td>";
                $date = new DateTime($row['created_at']);
echo "<td>" . $date->format('d/m') . "</td>";

                echo "<td data-column='name'>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td data-column='firstname'>" . htmlspecialchars($row['firstname']) . "</td>";
                echo "<td data-column='category'>" . htmlspecialchars($row['category']) . "</td>";
                echo "<td data-column='phone'>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td data-column='email'><a href='#' onclick=\"sendEmail(" . $row['id'] . ")\">" . htmlspecialchars($row['email']) . "</a></td>";

                // Nouvelle colonne rôle avec icône

                echo "<td style='text-align: center; padding: 5px;'>";
$rolesForClub = $clubRoles[$club] ?? [];
if(isset($rolesForClub[$row['role']])) {
    echo $rolesForClub[$row['role']]; // Affiche l’icône correspondante
}
echo "</td>";
                echo "<td data-column='jacket_size' onclick=\"toggleMissing(" . $row['id'] . ", 'jacket_missing', this)\" class='" . ($row['jacket_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['jacket_size']) . "</td>";

                echo "<td data-column='pants_size' onclick=\"toggleMissing(" . $row['id'] . ", 'pants_missing', this)\" class='" . ($row['pants_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['pants_size']) . "</td>";
                echo "<td data-column='bas_size' onclick=\"toggleMissing(" . $row['id'] . ", 'bas_missing', this)\" class='" . ($row['bas_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['bas_size']) . "</td>";
                echo "<td data-column='under_shirt_size' onclick=\"toggleMissing(" . $row['id'] . ", 'under_shirt_missing', this)\" class='" . ($row['under_shirt_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['under_shirt_size']) . "</td>";
                echo "<td data-column='jersey_size' onclick=\"toggleMissing(" . $row['id'] . ", 'jersey_missing', this)\" class='" . ($row['jersey_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['jersey_size']) . "</td>";
                echo "<td data-column='short_size' onclick=\"toggleMissing(" . $row['id'] . ", 'short_missing', this)\" class='" . ($row['short_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['short_size']) . "</td>";
                echo "<td data-column='option_kway' onclick=\"toggleMissing(" . $row['id'] . ", 'option_kway_missing', this)\" class='" . ($row['option_kway_missing'] ? 'missing' : '') . "'>" . (is_null($row['option_kway']) || $row['option_kway'] === '' || $row['option_kway'] === 'null' ? '' : htmlspecialchars($row['option_kway'])) . "</td>";
                echo "<td data-column='option_bas' onclick=\"toggleMissing(" . $row['id'] . ", 'option_bas_missing', this)\" class='" . ($row['option_bas_missing'] ? 'missing' : '') . "'>" . (is_null($row['option_bas']) || $row['option_bas'] === '' || $row['option_bas'] === 'null' ? '' : htmlspecialchars($row['option_bas'])) . "</td>";
echo "<td data-column='polo_size' onclick=\"toggleMissing(" . $row['id'] . ", 'polo_missing', this)\" class='" . ($row['polo_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['polo_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'kit_missing', this)\" class='" . ($row['kit_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['kit_size']) . "</td>";

                echo "<td data-column='initials'>" . htmlspecialchars($row['initials']) . "</td>";
               // echo "<td><textarea onchange=\"saveNotes(" . $row['id'] . ", this)\">" . htmlspecialchars($row['notes']) . "</textarea></td>";

                // Colonne Cotisation Payée
                $cotisationClass = ($row['coti_status'] == 1) ? 'selected' : '';
                echo "<td class='$cotisationClass' style='white-space:nowrap;'>
                        <input type='date' onchange=\"saveCotisation(" . $row['id'] . ", this)\" 
                               value='" . htmlspecialchars($row['cotisation_payee']) . "' 
                               onclick=\"toggleSelected(this, 'cotisation')\" />
                      </td>";

                // Colonne Facturé
$factureClass = ($row['facture_status'] == 1) ? 'selected facture-blue' : ''; // Ajoute 'facture-blue' si le statut de la facture est 1
echo "<td class='$factureClass'>
        <input type='date' 
               id='facture_date_" . $row['id'] . "' 
               onchange=\"updateFactureStatus(" . $row['id'] . ", this)\" 
               value='" . ($row['facture'] ? date('Y-m-d', strtotime($row['facture'])) : '') . "' 
               onclick=\"toggleSelected(this, 'facture')\" />
      </td>";

					  
// Colonne reprise
$repriseClass = ($row['reprise_status'] == 1) ? 'selected reprise-orange' : ''; // Ajoute 'reprise-orange' quand le statut est sélectionné

// Chercher si une signature existe pour ce client (glob sur club-name-firstname-ordernum-*.png)
$sigClub     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['club']);
$sigName     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['name']);
$sigFirstname= preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['firstname']);
$sigOrderNum = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['order_number']);
$sigPattern  = __DIR__ . '/signatures/' . $sigClub . '-' . $sigName . '-' . $sigFirstname . '-' . $sigOrderNum . '-*.png';
$sigFiles    = glob($sigPattern);
$sigBtn      = '';
if (!empty($sigFiles)) {
    $sigPath = 'signatures/' . basename($sigFiles[0]);
    $sigLabel = htmlspecialchars($row['firstname'] . ' ' . $row['name']);
    $sigBtn = "<button class='btn-signature' onclick=\"showSignature('" . addslashes($sigPath) . "', '" . addslashes($sigLabel) . "')\" title='Voir la signature'>🗒️</button>";
}

echo "<td class='$repriseClass' style='white-space:nowrap;'>
        <input type='date' 
               id='reprise_date_" . $row['id'] . "' 
               onchange=\"updateRepriseStatus(" . $row['id'] . ", this)\" 
               value='" . ($row['reprise'] ? date('Y-m-d', strtotime($row['reprise'])) : '') . "' 
               onclick=\"toggleSelected(this, 'reprise')\" />
        $sigBtn
      </td>";



                echo "<td><button class='completed' onclick=\"markCompleted(" . $row['id'] . ", this)\">Terminé</button></td>";
				echo "<td><button class='edit' onclick=\"editOrder(this, " . $row['id'] . ")\">✏️</button></td>";
echo "<td><span class='delete' onclick=\"deleteOrder(" . $row['id'] . ", this.parentElement.parentElement)\">×</span></td>";



				


                echo "</tr>";
            }
            $conn->close();
            ?>
			

        </tbody>
    </table>
	<script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script><script>
function exportToExcel() {
    // Récupère le nom du club depuis l’URL
    const urlParams = new URLSearchParams(window.location.search);
    let currentClub = urlParams.get("club") || "Bas-Oha";
    currentClub = decodeURIComponent(currentClub.trim());

    // Associe le nom du club au bon fichier
    const exportFiles = {
        "Bas-Oha": "export_excel-wbo.php",
        "RFCB Sprimont": "export_excel-sprimont.php",
        "Ellas": "export_excel-ellas.php",
        "JSN": "export_excel-jsn.php",
        "Union Hutoise": "export_excel-uh.php",
        "Hannut": "export_excel-wh.php"
    };

    // Si le club n’existe pas dans la liste, utiliser Bas-Oha par défaut
    const exportFile = exportFiles[currentClub] || "export_excel-wbo.php";

    // Ouvre directement le bon fichier (sans ?club=)
    window.location.href = exportFile;
}




let currentFilter = "all"; // Par défaut, afficher tous les rôles

function toggleFilter() {
    const button = document.getElementById("filterButton");
    const rows = document.querySelectorAll("tbody tr");

    if (currentFilter === "all") {
        currentFilter = "Joueur-WBO";
        button.textContent = "Afficher: Joueur-WBO";
    } else if (currentFilter === "Joueur-WBO") {
        currentFilter = "Keeper-WBO";
        button.textContent = "Afficher: Keeper-WBO";
 
    } else {
        currentFilter = "all";
        button.textContent = "Afficher: Tous";
    }

    rows.forEach(row => {
        const roleCell = row.querySelector("td:nth-child(8)"); // 8ème colonne = Rôle
        if (roleCell) {
            const role = roleCell.textContent.trim();
            if (currentFilter === "all" || role === "👶🏻" && currentFilter === "Ellas-Entraineur" || role === "⚽️" && currentFilter === "Joueur-WBO" || role === "🧤" && currentFilter === "Keeper-WBO") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });
}

function filterByRole(roleIcon, button) {
    const rows = document.querySelectorAll("tbody tr");
    const isMobile = window.innerWidth <= 768;

    // Gestion des boutons actifs
    const buttons = document.querySelectorAll(".filter-btn");
    buttons.forEach(btn => btn.classList.remove("active"));
    button.classList.add("active");

    rows.forEach(row => {
        let cellContent = "";
        
        if (isMobile) {
            // MODE MOBILE : chercher dans le .detail-value du rôle
            const roleDetail = Array.from(row.querySelectorAll(".detail-section .detail-value")).find((elem, index) => {
                const prevTitle = row.querySelectorAll(".detail-title")[index];
                return prevTitle && prevTitle.textContent.includes("Rôle");
            });
            if (roleDetail) {
                cellContent = roleDetail.textContent.trim();
            }
        } else {
            // MODE DESKTOP : 8ème colonne = Rôle
            const roleCell = row.querySelector("td:nth-child(8)");
            if (roleCell) {
                cellContent = roleCell.textContent.trim();
            }
        }
        
        if (roleIcon === "all") {
            row.style.display = "";
        } else {
            row.style.display = (cellContent === roleIcon) ? "" : "none";
        }
    });
}



function sendEmail(orderId) {
    if (confirm("Envoyer un e-mail à ce client ?")) {
        fetch('send_email_recap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + encodeURIComponent(orderId)
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Affiche le résultat du script PHP
        })
        .catch(error => {
            alert('Erreur lors de l\'envoi de l\'e-mail');
            console.error(error);
        });
    }
}


function onFilterChange() {
    // Dès qu'on coche autre chose, on décoche "Tout"
    document.getElementById("filter_tout").checked = false;
    applyFilters();
}

function applyFilters() {
    const showAll = document.getElementById("filter_tout").checked;
    const showManquant = document.getElementById("filter_manquant").checked;
    const showCoti = document.getElementById("filter_coti").checked;
    const showFacture = document.getElementById("filter_facture").checked;
    const showNonFacture = document.getElementById("filter_non_facture").checked;
    const showRepris = document.getElementById("filter_repris").checked;
    const showNonRepris = document.getElementById("filter_non_repris").checked;
    const showNonTermine = document.getElementById("filter_non_termine").checked;

    const table = document.getElementById("ordersTable");
    const headers = table.querySelectorAll("thead th");
    const rows = table.querySelectorAll("tbody tr");
    
    // Détecter si on est en mode mobile
    const isMobile = window.innerWidth <= 768;

    // Trouver dynamiquement l'index des colonnes fixes
    let colIndex = {};
    headers.forEach((th, i) => {
        const text = th.textContent.trim().toLowerCase();
        if (text.includes("coti")) colIndex.coti = i;
        else if (text.includes("fact")) colIndex.facture = i;
        else if (text.includes("repris")) colIndex.repris = i;
        else if (text.includes("terminé")) colIndex.termine = i;
    });

    rows.forEach(row => {
        let visible = true;

        if (isMobile) {
            // MODE MOBILE : chercher dans les divs .size-item et .action-item
            
            // Vérifie la présence d'au moins une cellule "missing"
            const missing = row.querySelector(".size-item.missing") !== null;

            // Vérifie Coti Payée - chercher dans les action-item
            const cotiActionItem = Array.from(row.querySelectorAll(".action-item")).find(item => 
                item.querySelector(".action-label")?.textContent.includes("Cotisation")
            );
            const cotiInput = cotiActionItem ? cotiActionItem.querySelector("input[type='date']") : null;
            const coti = cotiInput && cotiInput.value.trim() !== "";

            // Vérifie Facturé
            const factureActionItem = Array.from(row.querySelectorAll(".action-item")).find(item => 
                item.querySelector(".action-label")?.textContent.includes("Facturé")
            );
            const factureInput = factureActionItem ? factureActionItem.querySelector("input[type='date']") : null;
            const facture = factureInput && factureInput.value.trim() !== "";

            // Vérifie Repris
            const reprisActionItem = Array.from(row.querySelectorAll(".action-item")).find(item => 
                item.querySelector(".action-label")?.textContent.includes("Repris")
            );
            const reprisInput = reprisActionItem ? reprisActionItem.querySelector("input[type='date']") : null;
            const repris = reprisInput && reprisInput.value.trim() !== "";

            // Vérifie Terminé
            const completed = row.classList.contains("completed");

            if (!showAll) {
                if (showManquant && !missing) visible = false;
                if (showCoti && !coti) visible = false;
                if (showFacture && !facture) visible = false;
                if (showNonFacture && facture) visible = false;
                if (showRepris && !repris) visible = false;
                if (showNonRepris && repris) visible = false;
                if (showNonTermine && completed) visible = false;
            }
            
        } else {
            // MODE DESKTOP : logique originale avec les td
            
            // Vérifie la présence d'au moins une cellule "missing"
            const missing = row.querySelector("td.missing") !== null;

            // Vérifie Coti Payée
            const cotiInput = colIndex.coti !== undefined ? row.querySelectorAll("td")[colIndex.coti].querySelector("input") : null;
            const coti = cotiInput && cotiInput.value.trim() !== "";

            // Vérifie Facturé
            const factureInput = colIndex.facture !== undefined ? row.querySelectorAll("td")[colIndex.facture].querySelector("input") : null;
            const facture = factureInput && factureInput.value.trim() !== "";

            // Vérifie Repris
            const reprisInput = colIndex.repris !== undefined ? row.querySelectorAll("td")[colIndex.repris].querySelector("input") : null;
            const repris = reprisInput && reprisInput.value.trim() !== "";

            // Vérifie Terminé
            const completed = row.classList.contains("completed");

            if (!showAll) {
                if (showManquant && !missing) visible = false;
                if (showCoti && !coti) visible = false;
                if (showFacture && !facture) visible = false;
                if (showNonFacture && facture) visible = false;
                if (showRepris && !repris) visible = false;
                if (showNonRepris && repris) visible = false;
                if (showNonTermine && completed) visible = false;
            }
        }

        row.style.display = visible ? "" : "none";
    });

    // Gère le checkbox "Tout"
    if (showManquant || showCoti || showFacture || showNonFacture || showRepris || showNonRepris || showNonTermine) {
        document.getElementById("filter_tout").checked = false;
    }
    if (!showManquant && !showCoti && !showFacture && !showNonFacture && !showRepris && !showNonRepris && !showNonTermine && !showAll) {
        document.getElementById("filter_tout").checked = true;
        applyFilters();
    }
	
	// -----------------------------
    // AJOUT DU RECAP DES ARTICLES MANQUANTS
    // -----------------------------

const summaryDiv = document.getElementById("missingSummary");
if (!summaryDiv) return;

if (showManquant) {
    // Clé = "Article|||Ref" — articles avec refs différentes (selon rôle) = cartes séparées
    // summary[key] = { label, ref, sizes: { "XL": 2, "L": 1, ... }, total }
    const summary = {};

    rows.forEach(row => {
        if (row.style.display === "none") return;
        const rowClub = row.dataset.club || "";
        const rowRole = row.dataset.role || "";

        if (isMobile) {
            const sizeItems = row.querySelectorAll(".size-item.missing");
            sizeItems.forEach(item => {
                const label = item.querySelector(".size-label")?.textContent.trim();
                const size  = item.querySelector(".size-value")?.textContent.trim() || "—";
                if (!label) return;
                const ref = getRef(label, rowClub, rowRole);
                const key = label + "|||" + (ref || "NOREF");
                if (!summary[key]) summary[key] = { label, ref: ref || null, sizes: {}, total: 0 };
                summary[key].sizes[size] = (summary[key].sizes[size] || 0) + 1;
                summary[key].total++;
            });
        } else {
            const cells = row.querySelectorAll("td");
            cells.forEach((cell, index) => {
                if (!cell.classList.contains("missing")) return;
                const label = headers[index]?.textContent.trim();
                const size  = cell.textContent.trim() || "—";
                if (!label) return;
                const ref = getRef(label, rowClub, rowRole);
                const key = label + "|||" + (ref || "NOREF");
                if (!summary[key]) summary[key] = { label, ref: ref || null, sizes: {}, total: 0 };
                summary[key].sizes[size] = (summary[key].sizes[size] || 0) + 1;
                summary[key].total++;
            });
        }
    });

    if (Object.keys(summary).length === 0) {
        summaryDiv.innerHTML = "<em>Aucun article manquant.</em>";
    } else {
        summaryDiv.innerHTML = "";

        // Trier alphabétiquement par nom d'article
        const sorted = Object.values(summary).sort((a, b) => a.label.localeCompare(b.label));

        sorted.forEach(item => {
            // ── Carte principale ───────────────────────────────────────────
            const card = document.createElement("div");
            card.className = "missing-card";

            // Nom de l'article
            const labelEl = document.createElement("span");
            labelEl.className = "missing-card-label";
            labelEl.textContent = item.label;
            card.appendChild(labelEl);

            // Référence (ou "réf ?" si absente)
            const refEl = document.createElement("span");
            refEl.className = "missing-card-ref";
            refEl.textContent = item.ref || "réf ?";
            card.appendChild(refEl);

            // Chips de tailles
            const sizesWrap = document.createElement("div");
            sizesWrap.className = "missing-card-sizes";

            // Trier les tailles : chiffres d'abord (numérique), puis S/M/L/XL...
            const sizeOrder = ["XXS","XS","S","M","L","XL","XXL","3XL","4XL","—"];
            const sizeEntries = Object.entries(item.sizes).sort(([a], [b]) => {
                const ai = sizeOrder.indexOf(a.toUpperCase());
                const bi = sizeOrder.indexOf(b.toUpperCase());
                if (ai !== -1 && bi !== -1) return ai - bi;
                if (ai !== -1) return 1;
                if (bi !== -1) return -1;
                // Tailles numériques (ex: 128, 152...)
                const an = parseFloat(a), bn = parseFloat(b);
                if (!isNaN(an) && !isNaN(bn)) return an - bn;
                return a.localeCompare(b);
            });

            sizeEntries.forEach(([size, count]) => {
                const chip = document.createElement("span");
                chip.className = "missing-size-chip";
                chip.textContent = size + " ×" + count;
                sizesWrap.appendChild(chip);
            });

            card.appendChild(sizesWrap);
            summaryDiv.appendChild(card);
        });
    }
} else {
    summaryDiv.innerHTML = "";
}




	
}

function updateMissingSummary() {
    const table = document.getElementById("ordersTable");
    const rows = table.querySelectorAll("tbody tr");
    const summary = {};

    rows.forEach(row => {
        if (row.style.display === "none") return; // ignore les lignes filtrées

        const cells = row.querySelectorAll("td");
        const headers = table.querySelectorAll("thead th");

        cells.forEach((cell, index) => {
            if (cell.classList.contains("missing")) {
                const columnName = headers[index].textContent.trim();
                const value = cell.textContent.trim();

                // On compte par taille ou article
                const key = `${columnName} ${value}`;
                summary[key] = (summary[key] || 0) + 1;
            }
        });
    });

    // Affichage
    const summaryDiv = document.getElementById("missingSummary");
    if (Object.keys(summary).length === 0) {
        summaryDiv.innerHTML = "<em>Aucun article manquant.</em>";
        return;
    }

    let html = "<strong>Récapitulatif des articles manquants :</strong><br>";
    for (const [key, count] of Object.entries(summary)) {
        html += `${count} x ${key} &nbsp;&nbsp;`;
    }

    summaryDiv.innerHTML = html;
}

// ========== TRANSFORMATION MOBILE EN ACCORDÉON ==========
function initMobileAccordion() {
    if (window.innerWidth > 768) return; // Seulement sur mobile
    
    const table = document.getElementById('ordersTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    // Récupérer le club actuel depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentClub = urlParams.get('club') || 'Bas-Oha';
    const clubConfig = clubColumns[currentClub] || [];
    
    // Créer un Set des labels autorisés pour ce club
    const allowedLabels = new Set(clubConfig.map(col => col.label));
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length === 0) return;
        
        // Vérifier si la ligne est terminée (completed)
        const isCompleted = row.classList.contains('completed');
        
        // Vérifier si le pack a un scan (classe scan-yellow)
        const packCell = cells[0];
        const hasScan = packCell.classList.contains('scan-yellow');
        
        // Extraire les données
        const pack = cells[0].textContent.trim();
        const date = cells[1].textContent.trim();
        const nom = cells[2].textContent.trim();
        const prenom = cells[3].textContent.trim();
        const category = cells[4].textContent.trim();
        const phone = cells[5].textContent.trim();
        const emailCell = cells[6];
        const email = emailCell.querySelector('a') ? emailCell.querySelector('a').outerHTML : emailCell.textContent.trim();
        const role = cells[7].textContent.trim();
        
        // Articles (colonnes 8 jusqu'aux actions)
        const articles = [];
        const headers = table.querySelectorAll('thead th');
        
        // Déterminer où s'arrêtent les articles (avant Coti Payée)
        let articlesEndIndex = cells.length - 5; // Par défaut
        for (let i = 8; i < cells.length; i++) {
            const headerText = headers[i] ? headers[i].textContent.trim() : '';
            if (headerText.includes('Coti') || headerText.includes('Fact') || headerText.includes('Repris') || headerText.includes('Terminé')) {
                articlesEndIndex = i;
                break;
            }
        }
        
        for (let i = 8; i < articlesEndIndex; i++) {
            const cell = cells[i];
            const headerText = headers[i] ? headers[i].textContent.trim() : '';
            const value = cell.textContent.trim();
            const isMissing = cell.classList.contains('missing');
            const onclick = cell.getAttribute('onclick');
            
            // FILTRAGE : n'ajouter que si le label est autorisé pour ce club
            if (headerText && allowedLabels.has(headerText)) {
                articles.push({
                    label: headerText,
                    value: value || '-',
                    missing: isMissing,
                    onclick: onclick,
                    cell: cell
                });
            }
        }
        
        // Créer l'en-tête de la carte
        const cardHeader = document.createElement('div');
        cardHeader.className = 'card-header';
        const scanClass = hasScan ? 'scan-yellow' : '';
        cardHeader.innerHTML = `
            <span class="pack-number ${scanClass}">${pack}</span>
            <div class="name-info">${nom} ${prenom}</div>
            <span class="toggle-icon">▼</span>
        `;
        
        const cardDetails = document.createElement('div');
        cardDetails.className = 'card-details';
        
        // Section infos générales
        let detailsHTML = `
            <div class="detail-section">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    <div>
                        <span class="detail-title">📅 Date</span>
                        <div class="detail-value">${date}</div>
                    </div>
                    <div>
                        <span class="detail-title">🏷️ Catégorie</span>
                        <div class="detail-value">${category}</div>
                    </div>
                    <div>
                        <span class="detail-title">📱 Téléphone</span>
                        <div class="detail-value">${phone}</div>
                    </div>
                    <div>
                        <span class="detail-title">⚽ Rôle</span>
                        <div class="detail-value">${role}</div>
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <span class="detail-title">✉️ Email</span>
                    <div class="detail-value">${email}</div>
                </div>
            </div>
        `;
        
        // Section articles (grille 2 colonnes)
        if (articles.length > 0) {
            detailsHTML += '<div class="sizes-grid">';
            articles.forEach(article => {
                const missingClass = article.missing ? 'missing' : '';
                const onclickAttr = article.onclick ? `onclick="${article.onclick}"` : '';
                detailsHTML += `
                    <div class="size-item ${missingClass}" ${onclickAttr}>
                        <span class="size-label">${article.label}</span>
                        <span class="size-value">${article.value}</span>
                    </div>
                `;
            });
            detailsHTML += '</div>';
        }
        
        // Section actions
        detailsHTML += '<div class="actions-section">';
        
        // Cotisation Payée (cells.length - 6)
        const cotiCell = cells[cells.length - 6];
        const cotiInput = cotiCell ? cotiCell.querySelector('input') : null;
        const cotiValue = cotiInput ? cotiInput.value : '';
        const cotiSelected = cotiCell && cotiCell.classList.contains('selected') ? 'selected' : '';
        detailsHTML += `
            <div class="action-item ${cotiSelected}">
                <span class="action-label">💰 Cotisation Payée</span>
                ${cotiCell ? cotiCell.innerHTML : ''}
            </div>
        `;
        
        // Facturé (cells.length - 5)
        const factureCell = cells[cells.length - 5];
        const factureClasses = factureCell ? factureCell.className : '';
        const factureBlue = factureClasses.includes('facture-blue') ? 'facture-blue' : '';
        detailsHTML += `
            <div class="action-item ${factureBlue}">
                <span class="action-label">🧾 Facturé</span>
                ${factureCell ? factureCell.innerHTML : ''}
            </div>
        `;
        
        // Repris (cells.length - 4)
        const reprisCell = cells[cells.length - 4];
        const reprisClasses = reprisCell ? reprisCell.className : '';
        const repriseOrange = reprisClasses.includes('reprise-orange') ? 'reprise-orange' : '';
        detailsHTML += `
            <div class="action-item ${repriseOrange}">
                <span class="action-label">📦 Repris</span>
                ${reprisCell ? reprisCell.innerHTML : ''}
            </div>
        `;
        
        detailsHTML += '</div>'; // Fin actions-section
        
        // Boutons Terminé, Modifier, Supprimer (séparés en bas)
        detailsHTML += '<div class="buttons-section">';
        const termineCell = cells[cells.length - 3];
        const editCell = cells[cells.length - 2];
        const deleteCell = cells[cells.length - 1];
        
        detailsHTML += `
            <div class="buttons-group">
                ${termineCell ? termineCell.innerHTML : ''}
                ${editCell ? editCell.innerHTML : ''}
                ${deleteCell ? deleteCell.innerHTML : ''}
            </div>
        `;
        
        detailsHTML += '</div>'; // Fin buttons-section
        
        cardDetails.innerHTML = detailsHTML;
        
        // Vider la row et insérer la nouvelle structure
        row.innerHTML = '';
        row.appendChild(cardHeader);
        row.appendChild(cardDetails);
        
        // Toggle au clic sur le header
        cardHeader.addEventListener('click', function(e) {
            row.classList.toggle('expanded');
        });
    });
}

// Lancer la transformation au chargement
document.addEventListener('DOMContentLoaded', function() {
    initMobileAccordion();
    
    // Activer les filtres sur mobile
    if (window.innerWidth <= 768) {
        // Assurer que applyFilters fonctionne après transformation
        setTimeout(() => {
            if (typeof applyFilters === 'function') {
                applyFilters();
            }
        }, 100);
    }
});

// Recharger si changement desktop/mobile
window.addEventListener('resize', function() {
    const wasMobile = document.body.classList.contains('mobile-view');
    const isMobile = window.innerWidth <= 768;
    
    if (wasMobile !== isMobile) {
        location.reload();
    }
});

if (window.innerWidth <= 768) {
    document.body.classList.add('mobile-view');
}



</script>

<div id="missingSummaryContainer">
    <h3>Récap articles manquants</h3>
    <div id="missingSummary"></div>
</div>
<!-- Modal popup signature -->
<div id="signaturePopup" onclick="if(event.target===this) closeSignaturePopup()">
  <div id="signaturePopupInner">
    <h3 id="signaturePopupTitle"></h3>
    <img id="signaturePopupImg" src="" alt="Signature" />
    <button id="signaturePopupClose" onclick="closeSignaturePopup()">Fermer</button>
  </div>
</div>

<script>
function showSignature(path, label) {
  document.getElementById('signaturePopupTitle').textContent = 'Signature de ' + label;
  document.getElementById('signaturePopupImg').src = path;
  document.getElementById('signaturePopup').classList.add('open');
}
function closeSignaturePopup() {
  document.getElementById('signaturePopup').classList.remove('open');
  document.getElementById('signaturePopupImg').src = '';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeSignaturePopup();
});
</script>
</body>
</html>