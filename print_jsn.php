<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bon de commande - JSN</title>
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

    <img src="jsn.png" class="logo" alt="Logo Club">
    <h2 id="titrePack">Pack JSN</h2>

    <p><strong>Nom :</strong> <span id="nom"></span></p>
    <p><strong>Prénom :</strong> <span id="prenom"></span></p>
    <p><strong>Catégorie :</strong> <span id="categorie"></span></p>
    <p><strong>Email :</strong> <span id="email"></span></p>
    <p><strong>Téléphone :</strong> <span id="phone"></span></p>

    <div class="line"></div>
    <div class="grid-container" id="itemsContainer"></div>

    <div class="line"></div>
    <div id="initialesContainer"></div>

    <div class="line"></div>
    <p class="footer">Repris le :</p>

    <div style="margin-top: 30px; text-align: center;">
        <div id="qrcode"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function printJSNReceipt(orderId){
    fetch('get_order_details.php?orderId=' + orderId)
    .then(res => res.json())
    .then(order => {
        if(!order.success){ console.error(order.message); return; }

        const data = order.order;
        document.getElementById('nom').textContent = data.name || 'Non défini';
        document.getElementById('prenom').textContent = data.firstname || 'Non défini';
        document.getElementById('categorie').textContent = data.category || 'Non défini';
        document.getElementById('email').textContent = data.email || 'Non défini';
        document.getElementById('phone').textContent = data.phone || 'Non défini';
        document.getElementById('cotisationPayee').textContent = data.cotisation_payee || 'Non défini';
        document.getElementById('packNumber').textContent = 'N° ' + (data.order_number || 'Non défini');

        let role = data.role || '';
        let titrePack = 'Pack JSN';
        let titles={}, references={};

        if(role.trim()==='Entraineur-JSN'){
            titrePack='Pack JSN Entraîneur';
            titles={veste:"Veste Entraineur", pantalon:"Pantalon Entraineur"};
            references={veste:"100227101", pantalon:"100522101"};
        } else if(role.trim()==='Ellas-Keeper'){
            titles={veste:"Veste", pantalon:"Pantalon", kit:"Kit Keeper"};
            references={veste:"100527511", pantalon:"200522102", kit:"100531701"};
        } else {
            titles={veste:"Veste", pantalon:"Pantalon"};
            references={veste:"100226901", pantalon:"100522101"};
        }

        document.getElementById('titrePack').textContent = titrePack;

        let keyToFieldMap={veste:'jacket_size', pantalon:'pants_size', kit:'kit_size', bas:'bas_size', short:'short_size', polo:'polo_size', jersey:'jersey_size', souspull:'under_shirt_size', kway:'option_kway'};
        let itemsHTML='';

        for(let key in titles){
            let size = data[keyToFieldMap[key]] || (key==='kway'? data.option_kway:'Non défini');
            itemsHTML+=`<div class="item-box"><div class="item-title">${titles[key]}<div class="item-reference">${references[key]}</div></div><div class="item-size">${size}</div></div>`;
        }
        document.getElementById('itemsContainer').innerHTML = itemsHTML;

        let initialesHTML='';
        if(data.initials && data.initials.trim()!==''){
            initialesHTML=`<div class="grid-container" style="grid-template-columns: repeat(auto-fit,minmax(150px,1fr));max-width:400px;margin:0 auto;"><div class="item-box"><div class="item-title">Initiales</div><div class="item-size">${data.initials}</div></div><div class="item-box" style="border:2px solid #c00;background-color:#ffe6e6;"><div class="item-title" style="background-color:#c00;">Prix à payer</div><div class="item-size" style="color:#900;font-weight:bold;">30€</div></div></div>`;
        } else {
            initialesHTML=`<div style="display:flex;justify-content:center;margin-top:15px;"><div class="item-box" style="min-width:160px;"><div class="item-title" style="font-size:16px;padding:10px;">Initiales</div><div class="item-size" style="font-size:16px;">NON</div></div></div>`;
        }
        document.getElementById('initialesContainer').innerHTML = initialesHTML;

        new QRCode(document.getElementById("qrcode"), { text: JSON.stringify({orderId:orderId,email:data.email}), width:128, height:128 });
        setTimeout(()=>window.print(),500);
    }).catch(err=>console.error(err));
}

// Exemple d'utilisation
printJSNReceipt(<?php echo isset($_GET['orderId'])?intval($_GET['orderId']):0; ?>);
</script>
</body>
</html>
