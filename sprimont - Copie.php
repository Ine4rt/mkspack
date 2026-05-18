<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Order Management</title>
<link rel="stylesheet" href="styles.css">
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

function saveCotisation(orderId, element) {
    // Effectuer une requête pour obtenir la valeur actuelle de coti_status
    let xhrGet = new XMLHttpRequest();
    xhrGet.open('POST', 'get_current_coti_status.php', true);
    xhrGet.setRequestHeader('Content-Type', 'application/json');

    let dataGet = {
        orderId: orderId
    };

    console.log("Données envoyées pour obtenir coti_status :", JSON.stringify(dataGet));
    xhrGet.send(JSON.stringify(dataGet));

    xhrGet.onload = function() {
        if (xhrGet.status === 200) {
            try {
                let response = JSON.parse(xhrGet.responseText);
                if (response.success) {
                    let currentCotiStatus = response.coti_status;  // La valeur actuelle de coti_status
                    // Logique pour alterner coti_status
                    let newCotiStatus = (currentCotiStatus === 1) ? 0 : 1; // On inverse la valeur
                    
                    // Maintenant, envoyer la requête pour mettre à jour coti_status
                    let xhrUpdate = new XMLHttpRequest();
                    xhrUpdate.open('POST', 'update_order.php', true);
                    xhrUpdate.setRequestHeader('Content-Type', 'application/json');
                    
                    let dataUpdate = {
                        orderId: orderId,
                        field: 'coti_status',  // Le champ à mettre à jour
                        value: newCotiStatus   // La nouvelle valeur de coti_status
                    };
                    
                    console.log("Données envoyées à update_order.php:", JSON.stringify(dataUpdate)); // Vérification

                    xhrUpdate.send(JSON.stringify(dataUpdate));

                    xhrUpdate.onload = function() {
                        console.log('Réponse du serveur reçue avec statut:', xhrUpdate.status);
                        if (xhrUpdate.status === 200) {
                            try {
                                let updateResponse = JSON.parse(xhrUpdate.responseText);
                                if (updateResponse.success) {
                                    console.log("Coti_status mis à jour avec succès");
                                } else {
                                    console.error('Erreur de mise à jour de coti_status :', updateResponse.message);
                                }
                            } catch (e) {
                                console.error('Erreur lors de la réponse AJAX :', e.message);
                            }
                        } else {
                            console.error('Erreur lors de la mise à jour de coti_status.', xhrUpdate.responseText);
                        }
                    };

                    xhrUpdate.onerror = function() {
                        console.error('Erreur AJAX lors de la mise à jour de coti_status:', xhrUpdate.statusText);
                    };
                    
                } else {
                    console.error("Erreur lors de l'obtention du coti_status actuel :", response.message);
                }
            } catch (e) {
                console.error('Erreur lors de la réponse AJAX pour obtenir coti_status :', e.message);
            }
        } else {
            console.error('Erreur AJAX pour obtenir coti_status :', xhrGet.statusText);
        }
    };

    xhrGet.onerror = function() {
        console.error('Erreur AJAX pour obtenir coti_status:', xhrGet.statusText);
    };
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


function saveFacture(orderId, element) {
    let value = element.value;
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    // Passer la date sélectionnée et la mettre à jour dans la base de données
    let data = JSON.stringify({ 
        orderId: orderId, 
        field: 'facture',   // Le champ que nous mettons à jour
        value: value       // La valeur de la date
    });

    console.log("Données envoyées à update_order.php:", data);  // Message pour vérifier ce qui est envoyé
    
    xhr.send(data);
    
    xhr.onload = function() {
        console.log('Réponse du serveur reçue avec statut:', xhr.status); // Vérification du statut de la réponse
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log("Réponse JSON:", response); // Vérification de la réponse JSON
                
                if (response.success) {
                    console.log("Date de facturation mise à jour avec succès");
                } else {
                    console.error('Erreur de mise à jour de la date de facture :', response.message);
                }
            } catch (e) {
                console.error('Erreur lors de la réponse AJAX :', e.message);
            }
        } else {
            console.error('Erreur lors de la mise à jour de la facture.', xhr.responseText);
        }
    };

    xhr.onerror = function() {
        console.error('Erreur AJAX:', xhr.statusText);  // Pour attraper les erreurs de la requête AJAX
    };
}


function toggleSelected(element, type) {
    var td = element.closest('td'); // Trouver la cellule parente
    td.classList.toggle('selected'); // Ajouter ou supprimer la classe 'selected' à chaque clic

    const orderId = element.closest('tr').dataset.orderId; // Récupérer l'ID de la commande
    let status = td.classList.contains('selected') ? 1 : 0; // Statut à 1 ou 0 selon la sélection

    // Envoi de la requête AJAX pour mettre à jour la base de données selon le type de statut
    if (type === 'facture') {
        // Mettre à jour le statut de facture
        updateFactureStatus(orderId, status);
    } else if (type === 'cotisation') {
        // Mettre à jour le statut de cotisation
        updateCotisationStatus(orderId, status);
    }

    // Si le champ est sélectionné, mettre la date actuelle
    if (status === 1) {
        let today = new Date().toISOString().split('T')[0]; // Format YYYY-MM-DD
        element.value = today; // Mettre la date actuelle dans l'input
    } else {
        element.value = ''; // Si non sélectionné, vider la date
    }
}



// Fonction pour mettre à jour la cotisation payée
function updateCotisation(orderId, date) {
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    let data = JSON.stringify({
        orderId: orderId,
        field: 'cotisation_payee', // Le champ à mettre à jour
        value: date  // La date du jour
    });
    
    xhr.send(data);

    xhr.onload = function() {
        if (xhr.status == 200) {
            console.log("Cotisation payée mise à jour avec succès.");
        } else {
            console.log("Erreur lors de la mise à jour de la cotisation payée.");
        }
    };
}


// Modification de la fonction pour inclure la date
function updateFactureStatus(orderId, factureStatus, factureDate) {
    console.log("Envoi de la requête pour mettre à jour facture_status : ", orderId, factureStatus, factureDate);

    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    let data = JSON.stringify({
        orderId: orderId,
        field: 'facture_status',  // Champ à mettre à jour
        value: factureStatus,     // Valeur à insérer dans le champ facture_status
        factureDate: factureDate  // La date à insérer dans le champ facture (si facturé)
    });

    xhr.send(data);

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Si la mise à jour est réussie, assurez-vous que la case reste bleue
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
<!-- Bouton retour dans le coin gauche -->
    <a href="admin_mks.html" class="back-button">Retour</a>
	
    <!-- Logo + Nom du club -->
<div class="club-container">
        <img src="sprimont.png" alt="RFCB Sprimont" class="club-logo">
		<span style="font-size: 40px; font-weight: bold;">RFCB Sprimont</span>
		<button id="sortCotisation" onclick="sortCotisation()">Afficher les commandes sans cotisation payée</button>

    </div>

<input type="text" id="searchBar" placeholder="Recherche" onkeyup="searchOrders()">

    <table>
        <thead>
            <tr>
                <th>Pack</th>
                <th>Date</th>
				<th>Nom</th>
                <th>Prénom</th>
                <th>Catégorie</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Pack Veste</th>
                <th>Pack Pantalon</th>
                <th>Pack Kit</th>
                <th>Initiales</th>
                <th>Notes</th>
				<th>Coti Payée</th>
				<th>Facturé</th>
                <th>Terminé</th>
                <th>Supprimer</th>
            </tr>
        </thead>
<tbody>
    <?php
    // Connexion à la base de données
    $conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Récupérer toutes les commandes avec l'ID du club
    $sql = "SELECT * FROM orders WHERE club = 'RFCB Sprimont'";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $completedClass = ($row['completed'] == 1) ? 'completed' : '';
        echo "<tr data-order-id='" . htmlspecialchars($row['id']) . "' data-club='" . htmlspecialchars($row['club']) . "' class='" . $completedClass . "'>";
        echo "<td>" . htmlspecialchars($row['order_number']) . "</td>";
		$date = new DateTime($row['created_at']);
echo "<td>" . $date->format('d/m/Y') . "</td>"; 
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['firstname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'jacket_missing', this)\" class='" . ($row['jacket_missing'] ? 'missing' : '') . "'>";
echo htmlspecialchars($row['jacket_size']);
echo "</td>";

echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'pants_missing', this)\" class='" . ($row['pants_missing'] ? 'missing' : '') . "'>";
echo htmlspecialchars($row['pants_size']);
echo "</td>";

echo "<td onclick=\"toggleMissing(" . $row['id'] . ", 'kit_missing', this)\" class='" . ($row['kit_missing'] ? 'missing' : '') . "'>";
echo htmlspecialchars($row['kit_size']);
echo "</td>";
        echo "<td>" . htmlspecialchars($row['initials']) . "</td>";
        echo "<td><textarea onchange=\"saveNotes(" . $row['id'] . ", this)\">";
        echo htmlspecialchars($row['notes']);
        echo "</textarea></td>";

// Colonne Cotisation Payée
$cotisationClass = ($row['coti_status']) ? 'selected cotisation-selected' : ''; // Ajouter cotisation-selected
echo "<td class='$cotisationClass'>
        <input type='date' onchange=\"saveCotisation(" . $row['id'] . ", this)\" 
               value='" . htmlspecialchars($row['cotisation_payee']) . "' 
               onclick=\"toggleSelected(this, 'cotisation')\" />
      </td>";


// Nouvelle colonne Facturé avec l'événement onclick pour changer la couleur et sauvegarder
$factureClass = ($row['facture_status'] == 1) ? 'selected' : ''; // Vérifier si la facture est marquée
echo "<td class='$factureClass'>
        <input type='date' onchange=\"saveFacture(" . $row['id'] . ", this)\" 
               value='" . htmlspecialchars($row['facture']) . "' 
               onclick=\"toggleSelected(this, 'facture')\" />
      </td>";

        echo "<td><button class='completed' onclick=\"markCompleted(" . $row['id'] . ", this)\">Terminé</button></td>";
        echo "<td><button class='delete' onclick=\"deleteOrder(" . $row['id'] . ", this.parentElement.parentElement)\">Supprimer</button></td>";
        echo "</tr>";
    }
    $conn->close();
    ?>
</tbody>

    </table>
</body>
</html>
