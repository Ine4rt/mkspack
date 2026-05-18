<?php
// Récupère l'orderId passé en GET
$orderId = isset($_GET['orderId']) ? intval($_GET['orderId']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de commande - Bas Oha</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; padding: 20px; text-align: center; }
        .container { width: 90%; margin: auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); position: relative; }
        .header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; }
        .logo { height: 60px; display: block; margin: 0 auto; }
        .pack-number { font-size: 24px; font-weight: bold; color: #2c3e50; text-align: right; margin-right: 50px; }
        h2 { text-align: center; font-size: 30px; color: #333; margin-top: 15px; text-transform: uppercase; }
        .grid-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px; justify-content: center; align-items: center; }
        .item-box { border: 2px solid #000; border-radius: 5px; overflow: hidden; text-align: center; width: 100%; max-width: 200px; margin: auto; page-break-inside: avoid; }
        .item-title { background-color: #000; color: #fff; padding: 8px; font-weight: bold; font-size: 18px; text-transform: uppercase; }
        .item-size { padding: 10px; font-size: 16px; color: #333; }
        .item-reference { background-color: #000; color: #fff; font-size: 12px; padding: 0px; margin-top: -1.2px; text-transform: uppercase; font-weight: normal; }
        .line { margin: 20px 0; border-top: 1px solid #ccc; }
        .footer { text-align: left; font-size: 14px; color: #888; margin-top: 20px; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .container { width: 100%; padding: 20px; box-shadow: none; }
            .grid-container { grid-template-columns: repeat(3, 1fr); }
            .item-box { max-width: 180px; }
            #qrcode { position: absolute; right: 100px; width: 80px; height: 80px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <p><strong>Date :</strong> <span id="cotisationPayee"></span></p>
            <div class="pack-number" id="packNumber"></div>
        </div>

        <img src="bas-oha.png" class="logo" alt="Logo Club">
        <h2 id="titrePack">Pack WBO</h2>

        <p><strong>Nom :</strong> <span id="nom"></span></p>
        <p><strong>Prénom :</strong> <span id="prenom"></span></p>
        <p><strong>Catégorie :</strong> <span id="categorie"></span></p>
        <p><strong>Email :</strong> <span id="email"></span></p>
        <p><strong>Téléphone :</strong> <span id="phone"></span></p>

        <div class="line"></div>
        <div class="grid-container" id="itemsContainer"></div>

        <div class="line"></div>
        <div class="grid-container" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin: 0 auto;">
            <div class="item-size" id="initialesContainer"></div>
            <div class="item-box" style="border: 2px solid #c00; background-color: #ffe6e6;">
                <div class="item-title" style="background-color: #c00;">Prix à payer</div>
                <div class="item-size" id="prixTotal"></div>
            </div>
        </div>

        <div class="line"></div>
        <p class="footer">Repris le :</p>
        <div style="margin-top: 30px; text-align: center;">
            <div id="qrcode"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const orderId = <?php echo $orderId; ?>;

        fetch('get_order_details.php?orderId=' + orderId)
            .then(res => res.json())
            .then(order => {
                if(!order.success) { console.error(order.message); return; }

                const data = order.order;
                document.getElementById('nom').textContent = data.name || 'Non défini';
                document.getElementById('prenom').textContent = data.firstname || 'Non défini';
                document.getElementById('categorie').textContent = data.category || 'Non défini';
                document.getElementById('email').textContent = data.email || 'Non défini';
                document.getElementById('phone').textContent = data.phone || 'Non défini';
                document.getElementById('cotisationPayee').textContent = data.cotisation_payee || 'Non défini';
                document.getElementById('packNumber').textContent = 'N° ' + (data.order_number || 'Non défini');

                // Gestion pack
                let titrePack = 'Pack WBO';
                if(data.role === 'Keeper-WBO') titrePack = 'Pack WBO Keeper';
                document.getElementById('titrePack').textContent = titrePack;

                // Items
                const keyToFieldMap = { veste:'jacket_size', pantalon:'pants_size', bas:'bas_size', souspull:'under_shirt_size', jersey:'jersey_size', short:'short_size', kway:'option_kway', option_bas:'option_bas' };
                const titles = { veste:'Veste', pantalon:'Pantalon', bas:'Bas', souspull:'Sous-pull', jersey:'Maillot', short:'Short', kway:'Opt Kway', option_bas:'Opt Bas' };
                const references = { veste:'100226941', pantalon:'100522102', bas:'100369706', souspull:'100307814', jersey:'100226741', short:'100530641', kway:'Opt Kway', option_bas:'Opt Bas' };

                let itemsHTML = '';
                let prixTotal = 0;
                if(data.initials) prixTotal += 15;
                if(data.option_kway && data.option_kway !== 'NON' && data.option_kway !== 'null' && data.option_kway !== '') prixTotal += 19;
                if(data.option_bas && data.option_bas !== 'NON' && data.option_bas !== 'null' && data.option_bas !== '') prixTotal += 6;

                for(let key in titles){
                    let val = data[keyToFieldMap[key]] || (key==='kway'? data.option_kway : 'Non défini');
                    itemsHTML += `<div class="item-box"><div class="item-title">${titles[key]}<div class="item-reference">${references[key]}</div></div><div class="item-size">${val}</div></div>`;
                }

                document.getElementById('itemsContainer').innerHTML = itemsHTML;
                document.getElementById('initialesContainer').innerHTML = data.initials ? `<div class="item-box"><div class="item-title">Initiales</div><div class="item-size">${data.initials}</div></div>` : `<div class="item-box"><div class="item-title">Initiales</div><div class="item-size">NON</div></div>`;
                document.getElementById('prixTotal').textContent = prixTotal + '€';

                // QR code
                new QRCode(document.getElementById("qrcode"), { text: JSON.stringify({orderId:orderId,email:data.email}), width:128, height:128 });

                // Print
                setTimeout(() => window.print(), 500);
            })
            .catch(err => console.error(err));
    </script>
</body>
</html>