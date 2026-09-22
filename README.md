# qr.gobyus.com

Jednoúčelový rozcestník (fork [calimerostv/qr](https://github.com/calimerostv/qr)) pre
**Gobyus s.r.o.** (počítačový obchod, Sereď). Koreň domény zobrazí rovno prevádzku —
žiadny výber z viacerých firiem (`assets/js/config.js: singleVenue`).

## Obsah

- `data/gobyus.json` — všetok obsah stránky (hodiny, odkazy, téma).
  Dá sa upravovať aj cez `/admin/`.
- `assets/js/config.js` — brand, GitHub repo pre commit-storage administrácie.
- Téma: preset `grafit` + `theme.accent`/`theme.deep` (`#ECB337` zlatá na
  `#202225` tmavej), písmo **Dosis**.

## Nasadenie (SFTP)

```
pip install paramiko
python tools/deploy.py
```

Heslo sa hľadá v `QR_DEPLOY_PW`, potom v `tools/.deploy_env` (kľúč
`SFTP_PASS=…`, tento súbor sa negituje), inak sa spýta interaktívne.
Cieľ hostingu (`QR_DEPLOY_HOST`, `QR_DEPLOY_USER`, `QR_DEPLOY_ROOT`) zatiaľ
nie je vyplnený — treba ho zadať cez premenné prostredia alebo priamo
v `tools/deploy.py`, keď bude známy hosting pre `gobyus.com`.

`data/`, `tools/` a `.claude/` sa nikdy nenasadzujú — `data/` je na serveri
živý obsah písaný administráciou.

## Súbory, ktoré treba na server nahrať ručne (negitujú sa)

- `api/config.php` — heslo do `/admin/` (skopírovať z `api/config.example.php`).
- `api/dbconfig.php` — MariaDB pre počítadlo návštev/klikov.
- `api/mailconfig.php` — SMTP pre `report.php` (štatistický e-mail),
  skopírovať z `api/mailconfig.example.php` a vyplniť skutočné heslo k
  `obchod@gobyus.com`.

Vzory sú `api/*.example.php`.

## Týždenný / mesačný e-mail so štatistikami

`api/report.php` pošle e-mail na `obchod@gobyus.com` cez SMTP
(bez závislostí — `api/smtp.php`).

```
php api/report.php weekly
php api/report.php monthly
```

Na serveri treba pridať cron (cez panel hostingu), napr.:

```
0 7 * * 1   php /cesta/k/qr/api/report.php weekly
0 7 1 * *   php /cesta/k/qr/api/report.php monthly
```

Ak panel vie spustiť len URL (nie príkazový riadok), nastavte
`cron_token` v `api/mailconfig.php` a volajte:

```
https://qr.gobyus.com/api/report.php?period=weekly&token=…
```
