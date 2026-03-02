# Vendored: SimplyIT Fattura24 SDK

Questa cartella contiene una copia della SDK Fattura24 con namespace rinominato
per evitare conflitti con altri plugin che potrebbero includere la stessa libreria.

## Origine

- **Repository:** https://github.com/simplyit/fattura24-sdk
- **Namespace originale:** `SimplyIT\Fattura24SDK`
- **Namespace in questo plugin:** `SimplyITF24\SDK`

## Come aggiornare

Quando rilasci una nuova versione della SDK:

```bash
# 1. Copia i nuovi sorgenti
cp -r /path/to/fattura24-sdk/src/* lib/sdk/src/

# 2. Rinomina il namespace
find lib/sdk/src -name "*.php" -exec \
    sed -i 's/SimplyIT\\Fattura24SDK/SimplyITF24\\SDK/g' {} +

# 3. Verifica che non rimangano riferimenti al vecchio namespace
grep -r "SimplyIT\\\\Fattura24SDK" lib/sdk/src/

# 4. Commit
git add lib/sdk/src/
git commit -m "Update vendored SDK to vX.Y.Z"
```

## Nota sul futuro

Quando il plugin avrà più dipendenze esterne (Freemius SDK, ecc.)
valuta la migrazione a `composer install --no-dev` con vendor/ nel repository.
In quel caso questo file e la cartella lib/ possono essere rimossi.
