document.addEventListener("DOMContentLoaded", () => {
    // Gérer les cases "Manquant"
    document.querySelectorAll(".missing-checkbox").forEach(checkbox => {
        checkbox.addEventListener("change", () => {
            const row = checkbox.closest("tr");
            const orderId = row.dataset.id;
            const field = checkbox.dataset.field;
            const isMissing = checkbox.checked ? "1" : "0";

            // Colorer la cellule si "Manquant"
            checkbox.closest("td").previousElementSibling.style.backgroundColor = checkbox.checked ? "#f8d7da" : "";
            saveChange(orderId, field, isMissing);
        });
    });

    // Gérer les cases "Terminé"
    document.querySelectorAll(".mark-completed").forEach(checkbox => {
        checkbox.addEventListener("click", () => {
            const row = checkbox.closest("tr");
            const orderId = row.dataset.id;
            const isCompleted = checkbox.checked ? "1" : "0";

            row.classList.toggle("completed", checkbox.checked);
            saveChange(orderId, "status", isCompleted);
        });
    });

    // Sauvegarder les notes
    document.querySelectorAll(".order-notes").forEach(input => {
        input.addEventListener("blur", () => {
            const row = input.closest("tr");
            const orderId = row.dataset.id;
            const notes = input.value;

            saveChange(orderId, "notes", notes);
        });
    });

    // Fonction pour envoyer les modifications au serveur
    function saveChange(orderId, field, value) {
        fetch("update_order.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                orderId: orderId,
                field: field,
                value: value,
            }),
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert("Erreur lors de la sauvegarde : " + data.error);
                }
            })
            .catch(error => {
                console.error("Erreur réseau :", error);
            });
    }
});
