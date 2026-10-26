# Seed Combo Box

Plugin WooCommerce di **Seed Media Agency** per prodotti combinati (box componibili) con accordion, compatibile Woodmart.

## Aggiornamenti automatici da GitHub

Il plugin si aggiorna da solo dal repository pubblico https://github.com/gvajro/seed-combo-box tramite [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker): WordPress mostra l'aggiornamento in *Plugin* e in *Bacheca > Aggiornamenti*, come per un plugin normale. Non serve aggiungere nulla al `wp-config.php`.

Prima installazione: carica lo zip dalla pagina *Plugin*. Dopo, gli aggiornamenti arrivano da soli.

## Pubblicare un aggiornamento

1. Modifica il codice e aumenta `Version:` nell'header di `seed-combo-box.php` (e `SCB_VERSION`).
2. Commit e push su `main`.
3. La GitHub Action *Release* crea in automatico tag e release `vX.Y.Z`.
4. I siti vedono l'aggiornamento entro ~12 ore (o subito da *Bacheca > Aggiornamenti > Ricontrolla*).

Un push senza nuova versione non genera aggiornamenti: WordPress aggiorna solo se il numero di versione sale.

## Novità

### 1.0.4
- Aggiornamenti da GitHub senza configurazione: repository già impostato nel plugin, release create in automatico a ogni push con versione nuova.

### 1.0.3
- Menu *Prodotti > Box componibili*: elenco dei soli prodotti combinati.

### 1.0.2
- Se la box non è componibile con i prodotti disponibili, in negozio e in scheda non compare il prezzo "A partire da".

### 1.0.1
- Validazione lato server più rigida: la box entra nel carrello solo con una selezione valida, da qualsiasi percorso.
- Righe senza prodotti disponibili: messaggio dedicato; se una riga non può raggiungere il minimo la box viene segnalata come non componibile.
- Woodmart: add to cart ajax, sticky add to cart (porta al blocco) e quick view.
- Accessibilità dell'accordion (tastiera, aria, focus).
