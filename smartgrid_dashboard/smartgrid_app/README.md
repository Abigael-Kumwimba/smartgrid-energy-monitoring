# SmartGrid App

Application locale PHP/MySQL pour le prototype IoT Smart Energy Meter.

## Modules

- `dashboard.php` : vue admin principale.
- `clients.php` : gestion des clients.
- `compteurs.php` : gestion des compteurs.
- `consommations.php` : historique des mesures.
- `factures.php` : generation de facture et paiement simule.
- `alertes_predictions.php` : alertes, anomalies et resultats ML.
- `api/save_consommation.php` : endpoint appele par l'ESP32.

## Installation locale avec XAMPP

1. Copier le dossier `smartgrid_app` dans `htdocs`.
2. Importer `smartgrid_energy_recovered_20260526-220309.sql` dans phpMyAdmin.
3. Ouvrir `http://localhost/smartgrid_app/dashboard.php`.

## URL API pour l'ESP32

Dans le fichier Arduino, utiliser une URL de ce type :

```cpp
const char* API_URL = "http://ADRESSE_IP_DU_PC/smartgrid_app/api/save_consommation.php";
```

Exemple si le PC a l'adresse `192.168.1.10` :

```cpp
const char* API_URL = "http://192.168.1.10/smartgrid_app/api/save_consommation.php";
```

Le compteur `SG001` doit exister dans la table `compteurs`.

