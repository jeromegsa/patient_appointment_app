# API de rendez-vous – Centre de Santé Communautaire de Ouèdo

API REST (Laravel, SQLite) qui remplace le cahier de rendez-vous du secrétariat : prise de rendez-vous, consultation par patient, suivi du statut selon le déroulement de la journée.

## Installation

Prérequis : PHP 8.3+, Composer.

```bash
composer install
cp .env.example .env        # Windows PowerShell : copy .env.example .env
php artisan key:generate
touch database/database.sqlite   # Windows PowerShell : New-Item database/database.sqlite
php artisan migrate
php artisan serve
```

L'API est alors disponible sur `http://127.0.0.1:8000/api`.

Le fuseau horaire est `Africa/Porto-Novo` (UTC+1) : la « date du jour » des règles R3 et R8 s'entend à l'heure du Bénin.

## Codes de réponse

| Situation | Code | Exemple |
|---|---|---|
| Succès (lecture, changement de statut) | 200 | |
| Rendez-vous créé | 201 | |
| Saisie invalide | 422 | téléphone mal formé, date hors bornes, motif trop court |
| Action interdite | 409 | doublon, transition de statut interdite, « terminé » avant le jour J |
| Ressource inexistante | 404 | identifiant de rendez-vous inconnu |

Toutes les erreurs renvoient du JSON avec une clé `message` lisible. Les erreurs 422 ajoutent `errors` (détail par champ).

**Envoyez toujours l'en-tête `Accept: application/json`.**

## Routes

### 1. Prendre un rendez-vous

`POST /api/appointments`

| Champ | Règle |
|---|---|
| `patient_phone` | 10 chiffres, commence par `01` |
| `service` | `MEDECINE_GENERALE`, `PEDIATRIE`, `GYNECOLOGIE` ou `DENTISTERIE` |
| `appointment_date` | `AAAA-MM-JJ`, entre aujourd'hui et +60 jours (inclus) |
| `slots` | entier de 1 à 3 (1 créneau = 30 min) |

Un patient ne peut pas avoir deux rendez-vous actifs (planifié ou confirmé) dans le même service le même jour.

Réponse : `201` avec l'identifiant et `"status": "planifie"`.

### 2. Lister les rendez-vous d'un patient

`GET /api/patients/{phone}/appointments?status=confirme`

- Tri : date de rendez-vous décroissante, puis création la plus récente d'abord.
- `status` est facultatif : `planifie`, `confirme`, `annule`, `termine`, `absent`.
- Un téléphone invalide renvoie `422`. Un patient sans rendez-vous renvoie `[]`.

### 3. Faire évoluer le statut

`PATCH /api/appointments/{id}/status`

Corps : `{ "status": "confirme" }`, ou pour annuler `{ "status": "annule", "cancel_reason": "Patient indisponible" }`.

Cycle de vie :

```
planifie ──► confirme ──► termine
   │            │  └────► absent
   └────────────┴───────► annule
```

- `termine`, `absent` et `annule` sont des états finaux.
- Le motif d'annulation est obligatoire : 5 à 200 caractères après suppression des espaces de début et de fin.
- `termine` et `absent` ne peuvent être posés qu'à partir du jour du rendez-vous (jour inclus), à l'heure du Bénin.

## Tester

### Tests automatisés

```bash
php artisan test
```

Les tests (`tests/Feature/AppointmentTest.php`) utilisent une base SQLite en mémoire : votre base de développement n'est pas touchée. Ils couvrent chaque règle R1 à R9, y compris les cas aux bornes (aujourd'hui, +60 jours, +61 jours, 30 février, motif de 4 et 5 caractères, changement de jour à l'heure du Bénin).

Pour lancer un seul test : `php artisan test --filter=test_r8`.

### Données de démonstration

```bash
php artisan migrate:fresh --seed
```

Attention : cette commande **vide** la base avant de la remplir. Elle crée :

| Patient | Contenu | Sert à tester |
|---|---|---|
| `0123456789` | 7 rendez-vous : planifié (id 1), confirmé (2), annulé (3), terminé (4), absent (5), deux planifiés le même jour (6 et 7) | la liste, le filtre par statut, le tri, les états finaux (409) |
| `0111111111` | un confirmé aujourd'hui (id 8), un confirmé demain (id 9) | R8 : `termine` accepté sur le 8, refusé (409) sur le 9 |
| 5 autres patients | rendez-vous aléatoires valides | volume |

Les identifiants indiqués supposent une base vide au départ.

La factory (`database/factories/AppointmentFactory.php`) fournit aussi des états réutilisables dans les tests ou dans `php artisan tinker` :

```php
Appointment::factory()->confirme()->aujourdhui()->create();
Appointment::factory()->annule('Patient malade')->pourPatient('0123456789')->create();
Appointment::factory()->count(10)->create();
```

### Tests manuels route par route

Démarrez le serveur avec `php artisan serve`, puis utilisez Postman, Insomnia, l'extension REST Client de VS Code, ou `curl`.

> **Windows PowerShell** : écrivez `curl.exe` (et non `curl`, alias de `Invoke-WebRequest`) et mettez le JSON dans un fichier avec `-d "@fichier.json"` pour éviter les problèmes de guillemets.
> Remplacez les dates des exemples par une date comprise entre aujourd'hui et +60 jours.

Fichiers JSON d'exemple :

`body.json`
```json
{
  "patient_phone": "0123456789",
  "service": "PEDIATRIE",
  "appointment_date": "2026-10-25",
  "slots": 2
}
```
`status.json` : `{ "status": "confirme" }`
`annule.json` : `{ "status": "annule", "cancel_reason": "Patient indisponible" }`

#### Route 1 : `POST /api/appointments`

| Test | Commande / corps | Attendu |
|---|---|---|
| Cas nominal | `curl.exe -X POST http://127.0.0.1:8000/api/appointments -H "Accept: application/json" -H "Content-Type: application/json" -d "@body.json"` | `201`, `status: planifie` |
| Doublon (R5) | relancer exactement la même commande | `409`, message sur le rendez-vous déjà actif |
| Téléphone invalide (R1) | `patient_phone` = `"0223456789"` ou `"012345"` | `422` sur `patient_phone` |
| Service inconnu (R2) | `service` = `"CHIRURGIE"` | `422` sur `service` |
| Date passée ou > 60 jours (R3) | `appointment_date` = hier, ou aujourd'hui + 61 jours | `422` sur `appointment_date` |
| Date inexistante (R3) | `appointment_date` = `"2026-02-30"` | `422` |
| Créneaux hors bornes (R4) | `slots` = `0` ou `4` | `422` sur `slots` |

#### Route 2 : `GET /api/patients/{phone}/appointments`

| Test | Commande | Attendu |
|---|---|---|
| Liste complète | `curl.exe http://127.0.0.1:8000/api/patients/0123456789/appointments -H "Accept: application/json"` | `200`, tableau trié (date décroissante, puis création décroissante) |
| Filtre | même URL avec `?status=confirme` | `200`, uniquement les rendez-vous confirmés |
| Statut inconnu | même URL avec `?status=nimporte` | `422` |
| Téléphone invalide | `/api/patients/123/appointments` | `422` |
| Patient sans rendez-vous | téléphone jamais utilisé | `200`, `[]` |

Pour vérifier le tri, créez plusieurs rendez-vous pour le même patient (dates et services différents) et comparez l'ordre renvoyé.

#### Route 3 : `PATCH /api/appointments/{id}/status`

| Test | Commande / corps | Attendu |
|---|---|---|
| planifié → confirmé | `curl.exe -X PATCH http://127.0.0.1:8000/api/appointments/1/status -H "Accept: application/json" -H "Content-Type: application/json" -d "@status.json"` | `200`, `status: confirme` |
| Transition interdite (R6) | sur un rendez-vous `planifie`, `{ "status": "termine" }` | `409` |
| État final (R6) | changer le statut d'un rendez-vous déjà `annule`, `termine` ou `absent` | `409` |
| Annulation valide (R7) | `-d "@annule.json"` | `200`, `cancel_reason` enregistré |
| Annulation sans motif (R7) | `{ "status": "annule" }` | `422` sur `cancel_reason` |
| Motif trop court (R7) | `cancel_reason` = `"  abcd  "` (4 caractères utiles) | `422` |
| Motif trop long (R7) | `cancel_reason` de 201 caractères | `422` |
| « Terminé » avant le jour J (R8) | rendez-vous `confirme` daté de demain, `{ "status": "termine" }` | `409` |
| « Terminé » le jour J (R8) | rendez-vous `confirme` daté d'aujourd'hui, `{ "status": "termine" }` | `200` |
| Rendez-vous inexistant (R9) | `/api/appointments/999/status` | `404` |
| Statut inconnu | `{ "status": "nimporte" }` | `422` |

Astuce pour tester R8 « le jour J » : créez un rendez-vous avec la date du jour, confirmez-le, puis passez-le à `termine` ou `absent`.

## Structure du code

| Fichier | Rôle |
|---|---|
| `routes/api.php` | les 3 routes |
| `app/Http/Controllers/AppointmentController.php` | couche HTTP, délègue au service |
| `app/Http/Requests/` | validation des saisies (R1 à R4, R7), erreurs `422` |
| `app/Services/AppointmentService.php` | règles de gestion (R5, R6, R8), erreurs `409` |
| `app/Exceptions/BusinessRuleException.php` | action interdite, réponse `409` |
| `app/AppointmentStatus.php`, `app/Service.php` | enums ; l'enum de statut porte les transitions autorisées |
| `bootstrap/app.php` | réponse `404` lisible |
| `tests/Feature/AppointmentTest.php` | tests de toutes les règles |
