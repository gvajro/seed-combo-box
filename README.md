# Seed Combo Box

Plugin WooCommerce di **Seed Media Agency** per prodotti combinati (box componibili) con accordion, compatibile Woodmart.

## Aggiornamenti automatici da GitHub

Il plugin usa [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker): WordPress mostra l'aggiornamento in *Plugin* e in *Bacheca > Aggiornamenti*, come per un plugin normale.

1. Crea il repository su GitHub e carica questa cartella.
2. Nel `wp-config.php` del sito cliente aggiungi:
   ```php
   define( 'SCB_UPDATE_REPO', 'https://github.com/UTENTE/seed-combo-box/' );
   // Solo se il repository è privato (token fine-grained, sola lettura su "Contents"):
   define( 'SCB_GITHUB_TOKEN', 'github_pat_xxx' );
   ```
3. Prima installazione: carica lo zip dalla pagina Plugin. Dopo, gli aggiornamenti arrivano da soli.

## Pubblicare un aggiornamento

1. Modifica il codice e aumenta `Version:` nell'header di `seed-combo-box.php` (es. 1.0.1).
2. Commit e push su `main`.
3. Crea una **Release** su GitHub con tag uguale alla versione (es. `v1.0.1`).
4. I siti vedono l'aggiornamento entro ~12 ore (o subito da *Bacheca > Aggiornamenti > Ricontrolla*).

Senza una nuova versione (header + tag/release) WordPress non rileva alcun aggiornamento, anche se il codice su `main` è cambiato.
