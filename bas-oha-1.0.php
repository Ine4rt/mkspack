<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Order Management</title>
<link rel="stylesheet" href="styles.css">
    <style>
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
    position: absolute;
    right: 20px;
    top: 10px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

#searchBar {
    padding: 8px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 5px;
    margin-bottom: 10px; /* Espace entre la barre et le bouton */
}

#exportButton {
    padding: 10px 15px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}		
#filterContainer {
    text-align: center;
    margin: 10px 0;
}

.filter-btn {
    background-color: #007BFF; /* Bleu */
    color: white;
    border: none;
    padding: 10px 20px;
    margin: 5px;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background-color: #0056b3; /* Bleu plus foncé au survol */
}

.filter-btn.active {
    background-color: #004494; /* Bleu encore plus foncé pour l'actif */
    font-weight: bold;
    transform: scale(1.05);
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

#searchOrderBar {
    width: 60px; /* Moins longue, ajustez la largeur selon vos besoins */
    font-size: 15px; /* Un peu plus grosse */
    padding: 10px; /* Un peu plus d'espace intérieur */
    margin-bottom: 15px; /* Marge en dessous */
    border: 1px solid #ccc; /* Bordure légère */
    border-radius: 5px; /* Coins arrondis pour un look plus doux */
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
    </script>
</head>
<body>
    <a href="admin_mks.html" class="back-button">Retour</a>
    <div class="club-container">
        <img src="bas-oha.png" alt="RFCB Sprimont" class="club-logo">
        <span style="font-size: 40px; font-weight: bold;">Wanze/Bas-Oha</span>
		
    </div>
	<div id="searchContainer">
    <input type="text" id="searchBar" placeholder="Recherche" onkeyup="searchOrders()">
	
    <button id="exportButton" onclick="exportToExcel()">Exporter en Excel</button>
</div>
<div id="filterContainer">
    <button class="filter-btn active" onclick="filterByRole('all', this)">Tous</button>
    <button class="filter-btn" onclick="filterByRole('Joueur', this)">Joueur</button>
    <button class="filter-btn" onclick="filterByRole('Keeper', this)">Keeper</button>
</div>
<input type="text" id="searchOrderBar" placeholder="N° Pack" onkeyup="searchNumberOrders()">


    <table>
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
                <th>Veste </br> 100527504</th>
                <th>Pantalon </br> 100522102</th>
                <th>Kit </br> 100345105</th>
                <th>Bas </br> 100330213</th>
                <th>Sous-pull </br> 100307814</th>
                <th>Kway </br> 100529960</th>
                <th>Initiales</th>
           
                <th>Coti Payée</th>
                <th>Facturé</th>
				 <th>Repris</th>
                <th>Terminé</th>
                <th>Supprimer</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            $sql = "SELECT * FROM orders WHERE club = 'Bas-Oha'";
            $result = $conn->query($sql);

            while ($row = $result->fetch_assoc()) {
                $completedClass = ($row['completed'] == 1) ? 'completed' : '';
                echo "<tr data-order-id='" . htmlspecialchars($row['id']) . "' data-club='" . htmlspecialchars($row['club']) . "' class='" . $completedClass . "'>";
                echo "<td>" . htmlspecialchars($row['order_number']) . "</td>";
                $date = new DateTime($row['created_at']);
echo "<td>" . $date->format('d/m') . "</td>";

                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['firstname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";

                // Nouvelle colonne rôle avec icône
                echo "<td style='text-align: center; padding: 5px; ";
                if ($row['role'] == 'keeper') {
                    echo "background-color: lightblue;"; // Fond bleu pour les keepers
                }
                echo "'>";
                if ($row['role'] == 'Joueur') {
                    echo "⚽️"; // Icône ballon de foot
                } elseif ($row['role'] == 'Keeper') {
                    echo "🧤"; // Icône paire de gants
                }
                echo "</td>";

                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'jacket_missing', this)\" class='" . ($row['jacket_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['jacket_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'pants_missing', this)\" class='" . ($row['pants_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['pants_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'kit_missing', this)\" class='" . ($row['kit_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['kit_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'bas_missing', this)\" class='" . ($row['bas_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['bas_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'under_shirt_missing', this)\" class='" . ($row['under_shirt_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['under_shirt_size']) . "</td>";
                echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'option_kway_missing', this)\" class='" . ($row['option_kway_missing'] ? 'missing' : '') . "'>" . htmlspecialchars($row['option_kway']) . "</td>";

                echo "<td>" . htmlspecialchars($row['initials']) . "</td>";
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
                echo "<td><button class='delete' onclick=\"deleteOrder(" . $row['id'] . ", this.parentElement.parentElement)\">Supprimer</button></td>";
                echo "</tr>";
            }
            $conn->close();
            ?>
        </tbody>
    </table>
	<script>
function exportToExcel() {
    window.location.href = 'export_excel.php';
}

let currentFilter = "all"; // Par défaut, afficher tous les rôles

function toggleFilter() {
    const button = document.getElementById("filterButton");
    const rows = document.querySelectorAll("tbody tr");

    if (currentFilter === "all") {
        currentFilter = "Joueur";
        button.textContent = "Afficher: Joueur";
    } else if (currentFilter === "Joueur") {
        currentFilter = "Keeper";
        button.textContent = "Afficher: Keeper";
    } else {
        currentFilter = "all";
        button.textContent = "Afficher: Tous";
    }

    rows.forEach(row => {
        const roleCell = row.querySelector("td:nth-child(8)"); // 8ème colonne = Rôle
        if (roleCell) {
            const role = roleCell.textContent.trim();
            if (currentFilter === "all" || role === "⚽️" && currentFilter === "Joueur" || role === "🧤" && currentFilter === "Keeper") {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });
}

function filterByRole(role, button) {
    const rows = document.querySelectorAll("tbody tr");
    const buttons = document.querySelectorAll(".filter-btn");

    // Mettre à jour l'état actif des boutons
    buttons.forEach(btn => btn.classList.remove("active"));
    button.classList.add("active");

    rows.forEach(row => {
        const roleCell = row.querySelector("td:nth-child(8)"); // 8ème colonne = Rôle
        if (roleCell) {
            const roleText = roleCell.textContent.trim();
            if (role === "all" || (role === "Joueur" && roleText === "⚽️") || (role === "Keeper" && roleText === "🧤")) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });
}
</script>
</body>
</html>

</html>
