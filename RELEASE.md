# Pre-Release Checklist per GitHub

## ✅ Fatto
- [x] PHPUnit test suite completa
- [x] PHP CS Fixer run
- [x] PHPStan level 6 pass
- [x] PdfManagerTest aggiunto
- [x] LICENSE file (MIT)
- [x] .gitignore configurato
- [x] Namespace originale ripristinato
- [x] composer.json completo

## ⚠️ Da fare PRIMA della pubblicazione

### 1. Test manuale con API reale
```bash
cp test-manual.php.example test-manual.php
# Aggiungi API key
php test-manual.php
```
**Verifica:** Invoice creato su Fattura24, nessun errore

### 2. Verifica esempi README
```bash
# Copia esempio Quick Start dal README
php -r "require 'vendor/autoload.php'; /* esempio qui */"
```
**Verifica:** Codice funziona senza modifiche

### 3. Test installazione pulita
```bash
cd /tmp
mkdir test-install && cd test-install
composer require simplyit/fattura24-sdk
```
**Verifica:** ❌ Non funzionerà finché non pubblichi su Packagist

## 📝 Opzionale ma consigliato

### GitHub Actions CI
Crea `.github/workflows/tests.yml`:
```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      - run: composer install
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpstan analyse
```

### CONTRIBUTING.md
Template base per contributor esterni.

### SECURITY.md
Policy per segnalare vulnerabilità.

## 🚀 Pubblicazione

### Step 1: GitHub
```bash
git init
git add .
git commit -m "Initial commit - Fattura24 SDK v2.0.0"
git tag v2.0.0
git remote add origin git@github.com:simplyit/fattura24-sdk.git
git push -u origin main
git push --tags
```

### Step 2: GitHub Release
- Vai su Releases → New Release
- Tag: v2.0.0
- Title: "v2.0.0 - First stable release"
- Copy-paste da CHANGELOG.md

### Step 3: Packagist
- Vai su packagist.org
- Submit package: https://github.com/simplyit/fattura24-sdk
- Configura webhook auto-update

### Step 4: Verifica
```bash
composer require simplyit/fattura24-sdk
```

## 🔍 Checklist finale

Prima di pushare, verifica:
- [ ] Nessun `TODO` nel codice
- [ ] Nessun `var_dump()` o `dd()`
- [ ] Nessuna API key hardcoded
- [ ] Tutti i test passano
- [ ] README aggiornato
- [ ] CHANGELOG completo
- [ ] Version number corretto (src/Version.php, composer.json)
- [ ] Email corretta in composer.json

## 📊 Dopo la pubblicazione

- [ ] Badge nel README (build status, version, license)
- [ ] Testare `composer require` da zero
- [ ] Aggiornare plugin WordPress per usare SDK da Packagist
- [ ] Annunciare su social/forum PHP/Fatturazione italiani
