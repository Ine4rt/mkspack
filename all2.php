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

    rows.forEach(row => {
        let orderNumberCell = row.querySelector("td"); // La première cellule contient l'order_number
        let orderNumber = orderNumberCell.textContent.toLowerCase(); // Récupérer le texte de cette cellule

        row.style.display = orderNumber.includes(input) ? "" : "none"; // Afficher ou masquer la ligne en fonction de la recherche
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
    var td = element.closest('td');
    td.classList.toggle('selected'); // Ajoute ou retire la classe 'selected' du <td>

    // Si c'est une reprise, ajoute aussi la classe 'reprise-orange'
    if (type === 'reprise') {
        td.classList.toggle('reprise-orange'); // Ajoute ou retire la couleur orange pour la case entière
    }

    // Si c'est une facture, applique la classe 'facture-blue'
    if (type === 'facture') {
        td.classList.toggle('facture-blue'); // Ajoute ou retire la couleur bleu clair pour la case entière
    }

    const orderId = element.closest('tr').dataset.orderId;
    let status = td.classList.contains('selected') ? 1 : 0;

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
                echo "<tr data-order-id='" . htmlspecialchars($row['id']) . "' data-club='" . htmlspecialchars($row['club']) . "' class='" . $completedClass . "'>";
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
                echo "<td data-column='option_kway' onclick=\"toggleMissing(" . $row['id'] . ", 'option_kway_missing', this)\" class='" . ($row['option_kway_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['option_kway']) . "</td>";
                echo "<td data-column='option_bas' onclick=\"toggleMissing(" . $row['id'] . ", 'option_bas_missing', this)\" class='" . ($row['option_bas_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['option_bas']) . "</td>";
echo "<td data-column='polo_size' onclick=\"toggleMissing(" . $row['id'] . ", 'polo_missing', this)\" class='" . ($row['polo_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['polo_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'kit_missing', this)\" class='" . ($row['kit_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['kit_size']) . "</td>";

                echo "<td data-column='initials'>" . htmlspecialchars($row['initials']) . "</td>";
               // echo "<td><textarea onchange=\"saveNotes(" . $row['id'] . ", this)\">" . htmlspecialchars($row['notes']) . "</textarea></td>";

                // Colonne Cotisation Payée
                $cotisationClass = ($row['coti_status'] == 1) ? 'selected' : '';
                echo "<td class='$cotisationClass'>
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
echo "<td class='$repriseClass'>
        <input type='date' 
               id='reprise_date_" . $row['id'] . "' 
               onchange=\"updateRepriseStatus(" . $row['id'] . ", this)\" 
               value='" . ($row['reprise'] ? date('Y-m-d', strtotime($row['reprise'])) : '') . "' 
               onclick=\"toggleSelected(this, 'reprise')\" />
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
	<script>
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

    // Gestion des boutons actifs
    const buttons = document.querySelectorAll(".filter-btn");
    buttons.forEach(btn => btn.classList.remove("active"));
    button.classList.add("active");

    rows.forEach(row => {
        const roleCell = row.querySelector("td:nth-child(8)"); // 8ème colonne = Rôle
        if (!roleCell) return;
        const cellContent = roleCell.textContent.trim();
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
    const showRepris = document.getElementById("filter_repris").checked;
    const showNonRepris = document.getElementById("filter_non_repris").checked;
    const showNonTermine = document.getElementById("filter_non_termine").checked;

    const table = document.getElementById("ordersTable");
    const headers = table.querySelectorAll("thead th");
    const rows = table.querySelectorAll("tbody tr");

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

        // Vérifie la présence d'au moins une cellule "missing"
        const missing = row.querySelector(".missing") !== null;

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
            if (showRepris && !repris) visible = false;
            if (showNonRepris && repris) visible = false;
            if (showNonTermine && completed) visible = false;
        }

        row.style.display = visible ? "" : "none";
    });

    // Gère le checkbox "Tout"
    if (showManquant || showCoti || showFacture || showRepris || showNonRepris || showNonTermine) {
        document.getElementById("filter_tout").checked = false;
    }
    if (!showManquant && !showCoti && !showFacture && !showRepris && !showNonRepris && !showNonTermine && !showAll) {
        document.getElementById("filter_tout").checked = true;
        applyFilters();
    }
	
	// -----------------------------
    // AJOUT DU RECAP DES ARTICLES MANQUANTS
    // -----------------------------

const summaryDiv = document.getElementById("missingSummary");
if (!summaryDiv) return;

if (showManquant) {
    const summary = {};

    rows.forEach(row => {
        if (row.style.display === "none") return;
        const cells = row.querySelectorAll("td");
        cells.forEach((cell, index) => {
            if (cell.classList.contains("missing")) {
                const columnName = headers[index].textContent.trim();
                const value = cell.textContent.trim();
                if (!summary[columnName]) summary[columnName] = {};
                summary[columnName][value] = (summary[columnName][value] || 0) + 1;
            }
        });
    });

    if (Object.keys(summary).length === 0) {
        summaryDiv.innerHTML = "<em>Aucun article manquant.</em>";
    } else {
        summaryDiv.innerHTML = "";

        for (const [articleType, values] of Object.entries(summary)) {
            // ligne pour le type d'article
            const rowDiv = document.createElement("div");
            rowDiv.style.display = "flex";
            rowDiv.style.flexWrap = "wrap"; // forcer retour à la ligne si besoin
            rowDiv.style.gap = "6px";
            rowDiv.style.marginBottom = "10px";

            // ajouter chaque badge
            for (const [val, count] of Object.entries(values)) {
                const badge = document.createElement("span");
                badge.className = "missing-badge";
                badge.textContent = `${count} x ${articleType} ${val}`;
                rowDiv.appendChild(badge);
            }

            summaryDiv.appendChild(rowDiv);
        }
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


</script>

<div id="missingSummaryContainer">
    <h3>Récap articles manquants</h3>
    <div id="missingSummary"></div>
</div>
</body>
</html>
