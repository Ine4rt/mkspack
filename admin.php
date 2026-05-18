<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Order Management</title>
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
    </style>
    <script>
// Fonction pour marquer la commande comme terminée
function markCompleted(orderId, button, email) {
    console.log("Marquage de la commande comme terminée : ID " + orderId);
    console.log("Adresse email du destinataire : " + email);  // Log de l'adresse email du destinataire
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
                    sendCompletionEmail(orderId);
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
function sendCompletionEmail(orderId) {
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'send_email.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('orderId=' + orderId);
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
    xhr.setRequestHeader('Content-Type', 'application/json');  // Modification pour envoyer en JSON

    // Envoi des données sous forme de JSON
    let data = JSON.stringify({
        orderId: orderId,
        field: field,
        value: newStatus
    });
    console.log("Données envoyées au serveur : " + data);
    xhr.send(data);

    // Lorsque la requête est terminée, mettre à jour l'affichage
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                let response = JSON.parse(xhr.responseText);
                console.log("Réponse du serveur : " + xhr.responseText);

                if (response.success) {
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
            }Mo
        } else {
            console.error("Erreur lors de la mise à jour de l'état de la taille.", xhr.responseText);
        }
    };
}

function searchOrders() {
    let input = document.getElementById("searchBar").value.toLowerCase();
    let rows = document.querySelectorAll("tbody tr");

    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function saveCotisation(orderId, element) {
    let value = element.value;
    let xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    let data = JSON.stringify({ orderId: orderId, field: 'cotisation_payee', value: value });
    xhr.send(data);
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



// Fonction pour gérer le clic et ajouter la classe 'selected' à la cellule
function toggleSelected(element) {
    // Trouver la cellule parent (td) de l'élément cliqué (input)
    var td = element.closest('td'); // Trouver la cellule parente

    // Ajouter la classe 'selected' à la cellule
    td.classList.add('selected');

    // Envoi d'une requête AJAX pour mettre à jour le statut dans la base de données
    updateFactureStatus(element);
}

// Fonction pour gérer le clic et ajouter la classe 'selected' à la cellule
// Fonction pour gérer le clic et ajouter la classe 'selected' à la cellule et la date du jour
function toggleSelected(element) {
    var td = element.closest('td'); // Trouver la cellule parente
    td.classList.toggle('selected'); // Ajouter ou supprimer la classe 'selected' à chaque clic

    const orderId = element.closest('tr').dataset.orderId; // Récupérer l'ID de la commande
    const factureStatus = td.classList.contains('selected') ? 1 : 0; // Statut de facture à 1 ou 0

    // Si le champ est sélectionné, mettre la date actuelle
    if (factureStatus === 1) {
        let today = new Date().toISOString().split('T')[0]; // Format YYYY-MM-DD
        element.value = today; // Mettre la date actuelle dans l'input
    } else {
        element.value = ''; // Si non sélectionné, vider la date
    }

    // Mise à jour de la base de données avec la nouvelle date et statut de la facture
    updateFactureStatus(orderId, factureStatus, element.value); // Passer la date comme paramètre
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

function editOrder(button, orderId) {
    let row = button.closest("tr");
    let cells = row.querySelectorAll("td:not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3)):not(:nth-last-child(4))");

    if (button.textContent === "Modifier") {
        cells.forEach(cell => {
            let value = cell.textContent.trim();
            cell.innerHTML = `<input type="text" value="${value}">`;
        });
        button.textContent = "Enregistrer";
    } else {
        let updatedData = {};
        cells.forEach(cell => {
            let input = cell.querySelector("input");
            if (input) {
                let columnName = cell.getAttribute("data-column");
                updatedData[columnName] = input.value;
                cell.textContent = input.value;
            }
        });

        fetch("modifier_order.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id: orderId, ...updatedData })
}).then(response => response.json())
          .then(data => {
              if (data.success) {
                  button.textContent = "Modifier";
              } else {
                  alert("Erreur lors de la mise à jour.");
              }
          });
    }
}










    </script>
</head>
<body>
    <h1>Order Management</h1>

    <!-- Filtrer par club avec les logos -->
    <div class="club-filter">
<button onclick="filterOrdersByClub('all')">
    <img src="logos/all_clubs.png" alt="Tous les Clubs" class="club-logo">
    Tous les Clubs
</button>

<button onclick="filterOrdersByClub('Bas-Oha')">
    <img src="bas-oha.png" alt="Bas-Oha" class="club-logo">
</button>

<button onclick="filterOrdersByClub('RFCB Sprimont')">
    <img src="sprimont.png" alt="RFCB Sprimont" class="club-logo">
</button>

<button onclick="filterOrdersByClub('Ellas')">
    <img src="logos/ellas.png" alt="Ellas" class="club-logo">
</button>

    </div>
	
<input type="text" id="searchBar" placeholder="Rechercher une commande..." onkeyup="searchOrders()">

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
				echo "<td><button class='edit' onclick=\"editOrder(this, " . $row['id'] . ")\">Modifier</button></td>";

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
    $sql = "SELECT * FROM orders";
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

        // Nouvelle colonne Cotisation Payée
        echo "<td><input type='date' onchange=\"saveCotisation(" . $row['id'] . ", this)\" value='" . htmlspecialchars($row['cotisation_payee']) . "' /></td>";

// Nouvelle colonne Facturé avec l'événement onclick pour changer la couleur et sauvegarder
$factureClass = ($row['facture_status'] == 1) ? 'selected' : ''; // Vérifier si la facture est marquée
echo "<td class='$factureClass'><input type='date' onchange=\"saveFacture(" . $row['id'] . ", this)\" value='" . htmlspecialchars($row['facture']) . "' onclick=\"toggleSelected(this)\" /></td>";

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
